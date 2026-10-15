@push('css-page')
<link rel="stylesheet" href="{{ asset('css/service-photos.css') }}">
@endpush
<section class="card sr-photos" aria-labelledby="acceptance-photos-title">
    <div class="card-body">
        <h2 id="acceptance-photos-title" class="h5">Araç kabul fotoğrafları</h2>
        <p class="text-muted">Teslim alınan aracın mevcut durumunu kaydedin. Fotoğraflar müşterinin özel araç bağlantısında da görünür.</p>
        <p class="text-muted small">Fotoğraflar servis tamamlandıktan sonra {{ \App\Services\PhotoRetention::days() }} gün saklanır ve ardından otomatik silinir. Saklamak istediğiniz fotoğrafları indirebilirsiniz.</p>
        @if(auth()->user()->type!=='client' && auth()->user()->can('edit service') && !\App\Services\PhotoRetention::expiredService($service))
            <form action="{{ route('service.photos.store',$service->id) }}" method="post" enctype="multipart/form-data" class="sr-photo-upload">
                @csrf
                <label for="acceptance-photos">Fotoğraf seçin</label>
                <input id="acceptance-photos" class="form-control" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" required aria-describedby="photo-limits">
                <small id="photo-limits">Servis başına en fazla 10 fotoğraf. JPG, PNG veya WebP; dosya başına 8 MB, tek yüklemede toplam 24 MB. Fotoğraflar oranları korunarak en fazla 1600 × 1600 piksele küçültülür. Kişisel belge veya yüz fotoğrafı yüklemeyin.</small>
                @foreach($errors->getMessages() as $field=>$messages)
                    @if(str_starts_with($field,'photos'))<p class="text-danger" role="alert">{{ $messages[0] }}</p>@endif
                @endforeach
                <button class="btn btn-primary" type="submit">Fotoğrafları ekle</button>
            </form>
        @endif
        <div class="sr-photo-grid">
            @forelse($service->photos as $photo)
                <figure class="sr-photo">
                    <a href="{{ route('service.photos.show',[$service->id,$photo->id]) }}" target="_blank" rel="noopener noreferrer" aria-label="Araç kabul fotoğrafını büyüt">
                        <img src="{{ route('service.photos.show',[$service->id,$photo->id]) }}" width="320" height="240" loading="lazy" alt="Araç kabul fotoğrafı {{ $loop->iteration }}">
                    </a>
                    <figcaption><span>{{ $photo->created_at->format('d.m.Y H:i') }}</span>
                        <a href="{{ route('service.photos.show',[$service->id,$photo->id]) }}" download="arac-kabul.jpg" class="btn btn-sm btn-outline-secondary">İndir</a>
                    @if(auth()->user()->type!=='client' && auth()->user()->can('edit service'))
                        <form action="{{ route('service.photos.destroy',[$service->id,$photo->id]) }}" method="post">
                            @csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Fotoğraf {{ $loop->iteration }} sil">Sil</button>
                        </form>
                    @endif
                    </figcaption>
                </figure>
            @empty <p class="text-muted">{{ \App\Services\PhotoRetention::expiredService($service) ? 'Saklama süresi dolduğu için bu servisin fotoğrafları artık gösterilmiyor. Servis ve fatura kayıtları korunur.' : 'Bu servis için henüz kabul fotoğrafı eklenmedi.' }}</p> @endforelse
        </div>
    </div>
</section>
