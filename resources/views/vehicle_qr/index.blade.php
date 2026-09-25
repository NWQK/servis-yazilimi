@extends('layouts.app')
@section('page-title', 'QR Etiketleri')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vehicle.index') }}">Araçlar</a></li>
    <li class="breadcrumb-item">QR Etiketleri</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-header"><h5>Hazır QR havuzu</h5></div>
        <div class="card-body">
            <p><strong>{{ $codes->count() }} boş QR</strong> · {{ $codes->whereNotNull('printed_at')->count() }} basılmış · {{ $codes->whereNull('printed_at')->count() }} basılmayı bekliyor</p>
            <p>Etiketleri önceden yazdırın, baskıyı kontrol edin ve ardından “Basılmış olarak işaretle”ye basın. Araç kaydında teslim ettiğiniz etiketin numarasını seçin. Her atamadan sonra sistem bir yeni QR üretir; yeni etiketin ayrıca basılması gerekir.</p>
            <p>Basılmış ama henüz araca atanmamış etiketleri tekrar yazdırabilir veya PDF olarak kaydedebilirsiniz. Bu işlem QR kodlarını ve baskı durumlarını değiştirmez.</p>
            @if ($codes->whereNotNull('printed_at')->isNotEmpty())
                <p><a class="btn btn-outline-secondary" href="{{ route('vehicle-qr.print', ['ids' => $codes->whereNotNull('printed_at')->pluck('id')->all()]) }}" target="_blank" rel="noopener">Araca atanmaya hazır QR’ları yazdır / PDF kaydet</a></p>
            @endif
            <p class="text-muted">Baskıda kullanılacak adres: <strong>{{ rtrim(config('app.url'), '/') }}</strong>. Etiketler dağıtıldıktan sonra bu adresin çalışmaya devam etmesi gerekir.</p>
            @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('vehicle-qr.print') }}" target="_blank">
                @csrf
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Seç</th><th>Etiket numarası</th><th>Baskı durumu</th><th>Oluşturulma</th><th>Yazdırma</th></tr></thead>
                    <tbody>
                    @foreach ($codes as $code)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $code->id }}" aria-label="{{ $code->label }} seç" @checked(!$code->printed_at)></td>
                            <td><strong>{{ $code->label }}</strong></td>
                            <td>{{ $code->printed_at ? 'Basılmış / teslim edilmeye hazır' : 'Basılmayı bekliyor' }}</td>
                            <td>{{ dateFormat($code->created_at) . ' ' . timeFormat($code->created_at) }}</td>
                            <td>@if ($code->printed_at)<a href="{{ route('vehicle-qr.print', ['ids' => [$code->id]]) }}" target="_blank" rel="noopener" aria-label="{{ $code->label }} etiketini tekrar yazdır / PDF kaydet">Tekrar yazdır / PDF kaydet</a>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <button type="submit" class="btn btn-secondary">Seçilenleri yazdır / PDF kaydet</button>
                <button type="submit" class="btn btn-outline-secondary" formaction="{{ route('vehicle-qr.printed') }}" formmethod="post" formtarget="_self">Seçilenleri basılmış olarak işaretle</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h5>Atanan etiketler</h5></div>
        <div class="card-body table-responsive">
            <table class="table"><thead><tr><th>Etiket</th><th>Araç</th><th>Atama tarihi</th><th></th></tr></thead><tbody>
            @forelse ($assigned as $code)
                <tr><td>{{ $code->label }}</td><td>{{ $code->vehicle->license_plate ?? 'Araç silinmiş — etiket kullanılamaz' }}</td><td>{{ dateFormat($code->assigned_at) . ' ' . timeFormat($code->assigned_at) }}</td>
                    <td>@if ($code->vehicle)<a href="{{ route('vehicle-qr.print', ['ids' => [$code->id]]) }}" target="_blank" rel="noopener">Tekrar yazdır</a>@endif</td></tr>
            @empty
                <tr><td colspan="4">Henüz bir araca QR atanmadı.</td></tr>
            @endforelse
            </tbody></table>
            {{ $assigned->links() }}
        </div>
    </div>
@endsection
