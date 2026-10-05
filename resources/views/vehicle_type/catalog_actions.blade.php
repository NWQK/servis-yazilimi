@if(Gate::check('create vehicle type') && Gate::check('create vehicle brand') && auth()->user()->type !== 'client')
    <div class="col-auto">
        <a class="btn btn-outline-secondary customModal" href="#" data-size="lg" data-url="{{ route('vehicle-catalog.create') }}" data-title="Otomatik marka ve modeller oluştur">
            <i class="ti ti-download align-text-bottom"></i> Otomatik marka ve modeller oluştur
        </a>
    </div>
@endif
@if(Gate::check('delete vehicle type') && Gate::check('delete vehicle brand') && auth()->user()->type !== 'client')
    @foreach($catalogImports as $import)
        <div class="col-auto">
            <form action="{{ route('vehicle-catalog.destroy',$import->id) }}" method="post" onsubmit="return confirm('Bu hazır listenin otomatik yüklemesi geri alınsın mı? Mevcut, araçlarda kullanılan ve düzenlenmiş kayıtlar korunur.');">
                @csrf @method('DELETE')
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-outline-danger"><i class="ti ti-arrow-back-up align-text-bottom"></i> Geri al otomatik yüklemeyi · {{ \App\Services\VehicleCatalog::CATEGORIES[$import->category] }}</button>
            </form>
        </div>
    @endforeach
@endif
