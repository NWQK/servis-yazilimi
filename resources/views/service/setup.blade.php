@extends('layouts.app')
@section('page-title', 'Servis oluşturma rehberi')
@section('breadcrumb')<li class="breadcrumb-item">Servis oluşturma rehberi</li>@endsection
@section('content')
<div class="card"><div class="card-body">
    <h5>Servis kaydı nasıl oluşturulur?</h5>
    <p class="text-muted">Müşteri ve araç → Çalışan ve servis türü → İşlemler ve tutarlar → Servis ve fatura</p>
    <ol class="row g-3 list-unstyled mb-0">
        <li class="col-12 col-md-6"><div class="border rounded p-3 h-100">
            <span class="badge bg-light-secondary mb-2">1. Müşteri ve araç</span>
            <p class="small text-muted">{{ $clientCount }} müşteri · {{ $vehicleCount }} araç mevcut</p>
            <p>Müşteri seçildikten sonra o müşterinin aracı seçilir. Yalnızca iletişim bilgilerini kaydetmek için “Müşteri ekle”; müşteri, araç ve servisi birlikte kaydetmek için “Servisle müşteri ekle” seçeneğini kullanın.</p>
            <p class="small text-muted">QR seçmek zorunlu değildir. QR boş bırakıldığında araca otomatik bir takip bağlantısı verilir. Marka/model listeniz boşsa hazır araç listelerini yükleyebilirsiniz.</p>
            <div class="d-flex flex-wrap gap-2">
                @can('manage client')<a class="btn btn-outline-secondary" href="{{ route('client.index') }}">Müşteri listesi</a>@endcan
                @can('create client')
                    @can('create vehicle')
                        <a class="btn btn-outline-secondary" href="{{ route('client.create') }}">Servisle müşteri ekle</a>
                    @endcan
                @endcan
                @can('manage vehicle')<a class="btn btn-outline-secondary" href="{{ route('vehicle.index') }}">Araç listesi</a>@endcan
            </div>
        </div></li>
        <li class="col-12 col-md-6"><div class="border rounded p-3 h-100">
            <span class="badge bg-light-secondary mb-2">2. Çalışan ve servis türleri</span>
            <p class="small text-muted">{{ $employeeCount }} çalışan · {{ $typeCount }} servis türü mevcut</p>
            <p>Servisi atayacağınız çalışanı kaydedin. Yağ değişimi, fren bakımı gibi yaptığınız işler için servis türleri oluşturun; standart işçilik tutarlarını belirleyin. Servis formunda bu türleri seçip tutarlarını ve vergilerini kontrol edin.</p>
            <p class="small text-muted">Servis formunda çalışan seçimi gerekir. Servis türlerini işletmenizin verdiği hizmetlere göre siz düzenlersiniz.</p>
            <div class="d-flex flex-wrap gap-2">
                @can('manage employee')<a class="btn btn-outline-secondary" href="{{ route('employee.index') }}">Çalışanlar</a>@endcan
                @can('manage service type')<a class="btn btn-outline-secondary" href="{{ route('service-type.index') }}">Servis türleri</a>@endcan
                @can('manage tax')<a class="btn btn-outline-secondary" href="{{ route('tax.index') }}">Vergiler</a>@endcan
            </div>
        </div></li>
        <li class="col-12 col-md-6"><div class="border rounded p-3 h-100">
            <span class="badge bg-light-secondary mb-2">3. İşlemler ve tutarlar</span>
            <p>Müşteri, araç, çalışan ve servis durumunu seçin. Yapılacak işlemleri servis türü satırlarına ekleyin; her satırın tutarını, vergisini ve açıklamasını kontrol edin. Ek işçilik için “Harici işçilik tutarı” alanını ve bu tutarın vergi seçimini kullanabilirsiniz.</p>
            <p class="small text-muted">Başlangıç ve bitiş tarihleri ile saatleri isteğe bağlıdır. Harici işçilik müşteriye yansıtılan ek ücrettir. Stok ürünlerini faturaya eklediğinizde miktar stoktan düşer.</p>
            @can('create service')<a class="btn btn-secondary" href="{{ route('service.create') }}">Servis oluştur</a>@endcan
        </div></li>
        <li class="col-12 col-md-6"><div class="border rounded p-3 h-100">
            <span class="badge bg-light-secondary mb-2">4. Fatura ve takip</span>
            <p>Servis kaydedildiğinde işlemlerine ait fatura da oluşturulur. Faturadan ürünleri, işçilikleri ve vergileri kontrol edin; gerekirse indirim uygulayın ve ödeme ekleyin. İşletme bilgileriniz ve logonuz ayarlardan alınır.</p>
            <p class="small text-muted">Burada oluşturulan belgeler temsili faturadır. Servis durumunu işlem ilerledikçe güncelleyin; tahsilatı fatura üzerinden kaydedin.</p>
            <div class="d-flex flex-wrap gap-2">
                @can('manage service')<a class="btn btn-outline-secondary" href="{{ route('service.index') }}">Servis listesi</a>@endcan
                @can('manage invoice')<a class="btn btn-outline-secondary" href="{{ route('invoice.index') }}">Faturalar</a>@endcan
            </div>
        </div></li>
    </ol>
</div></div>
@endsection
