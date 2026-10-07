@extends('layouts.app')
@section('page-title', 'Ürün ve stok başlangıç rehberi')
@section('breadcrumb')<li class="breadcrumb-item">Ürün ekleme rehberi</li>@endsection
@section('content')
<div class="card"><div class="card-body">
    <h5>Ürün listenizi nasıl hazırlarsınız?</h5>
    <p class="text-muted">Önce vergi ve birim seçeneklerini kontrol edin. Kategorilerle ürünlerinizi gruplandırabilirsiniz; kategori oluşturmak zorunlu değildir.</p>
    <ol class="row g-3 list-unstyled mb-0" aria-label="Ürün hazırlama adımları">
        @foreach([
            ['title'=>'Vergi ayarları','count'=>$taxCount,'text'=>'Ürün formunda uygulanacak vergiyi seçersiniz. Sistemde hazır KDV seçenekleri bulunur; ürününüze uygun olanı seçin.','permission'=>'manage tax','url'=>route('tax.index'),'label'=>'Vergileri görüntüle'],
            ['title'=>'Ürün birimleri','count'=>$unitCount,'text'=>'Ürün için birim seçmek zorunludur. Adet, litre, mililitre, kilogram gibi hazır birimleri kullanabilirsiniz.','permission'=>'manage unit','url'=>route('unit.index'),'label'=>'Birimleri görüntüle'],
            ['title'=>'Ürün kategorileri','count'=>$categoryCount,'text'=>'Yağ, filtre, fren parçaları gibi kendi kategorilerinizi oluşturabilirsiniz. İsterseniz ürünü kategorisiz de kaydedebilirsiniz. Hazır kategori yüklemesi bulunmaz.','permission'=>'manage item','url'=>route('item-category.index'),'label'=>'Kategorileri düzenle'],
            ['title'=>'Ürün ve stok kaydı','count'=>null,'text'=>'Ürün adı, kodu, birimi, vergisi, stok miktarı, alış fiyatı, satış fiyatı ve alış tarihini girin. Kategori oluşturduysanız ürüne atayın.','permission'=>'create item','url'=>route('item.create'),'label'=>'Ürün ekle','modal'=>true],
        ] as $step)
        <li class="col-12 col-md-6 col-xl-3">
            <div class="border rounded p-3 h-100 d-flex flex-column">
                <span class="badge bg-light-secondary align-self-start mb-2">{{ $loop->iteration }}. adım</span>
                <h6>{{ $step['title'] }}</h6>
                @if($step['count'] !== null)<small class="text-muted mb-2">{{ $step['count'] }} kayıt mevcut</small>@endif
                <p class="small flex-grow-1">{{ $step['text'] }}</p>
                @can($step['permission'])
                    @if(!empty($step['modal']))
                        <a href="#" class="btn btn-outline-secondary customModal" data-size="lg" data-url="{{ $step['url'] }}" data-title="Ürün ekle">{{ $step['label'] }}</a>
                    @else
                        <a href="{{ $step['url'] }}" class="btn btn-outline-secondary">{{ $step['label'] }}</a>
                    @endif
                @endcan
            </div>
        </li>
        @endforeach
    </ol>
</div></div>
<div class="card"><div class="card-body">
    <h5>Hazır vergi ve birim ayarları</h5>
    <p>KDV %20, %10 ve %1 ile adet, litre, mililitre, kilogram, gram, metre, santimetre, takım, çift, paket, kutu ve bidon seçenekleri hazırdır. Yeni işletmelerde otomatik oluşturulur. Eksikse aşağıdaki butonla ekleyebilirsiniz; mevcut ayarlarınız değiştirilmez, aynı ayarlar tekrar eklenmez.</p>
    @if(auth()->user()->can('create tax') && auth()->user()->can('create unit'))
    <form method="post" action="{{ route('inventory.setup.defaults') }}" onsubmit="return confirm('Eksik hazır vergi ve birim ayarları işletmenize eklensin mi? Mevcut kayıtlar korunur.');">
        @csrf <input type="hidden" name="confirm" value="1">
        <button class="btn btn-secondary" type="submit"><i class="ti ti-download" aria-hidden="true"></i> Hazır vergi ve birimleri ekle</button>
    </form>
    @endif
</div></div>
<div class="card"><div class="card-body">
    <h5>Stok nasıl çalışır?</h5>
    <p>Ürünü kaydettiğinizde girdiğiniz miktar stok olur. Ürün faturaya eklenince stoktan düşer; ödeme yapılıp yapılmaması bu işlemi etkilemez. Elden satış da stoktan düşer ve kâr-zarar hesabına yansır. Fatura silinirken ürünlerin stoklara geri eklenip eklenmeyeceğini siz seçersiniz.</p>
    <a href="{{ route('item.index') }}" class="btn btn-outline-secondary">Ürün listesine git</a>
</div></div>
@endsection
