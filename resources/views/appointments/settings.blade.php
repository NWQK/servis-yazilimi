@extends('layouts.app')
@section('page-title', 'Randevu ayarları')
@section('breadcrumb')<li class="breadcrumb-item">Randevu ayarları</li>@endsection
@push('css-page')<link rel="stylesheet" href="{{ asset('css/appointments.css') }}">@endpush
@push('script-page')<script src="{{ asset('js/appointments.js') }}"></script>@endpush
@section('content')
<div class="card"><div class="card-body">
    <h5>Müşterileriniz için randevu bağlantısı</h5>
    <p>Bu bağlantı yalnızca işletmenize aittir. İşletme adını veya çalışma saatlerini değiştirmeniz bağlantıyı değiştirmez.</p>
    <div class="booking-share"><input class="form-control" aria-label="Randevu bağlantısı" readonly value="{{ $profile->publicUrl() }}"><a class="btn btn-outline-secondary" href="{{ $profile->publicUrl() }}" target="_blank" rel="noopener">Müşteri sayfasını aç</a></div>
</div></div>
<form method="post" action="{{ route('appointments.settings.save') }}">@csrf
<div class="card"><div class="card-body">
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="row"><div class="form-group col-md-6"><label class="form-label" for="display_name">Randevu sayfasında görünen işletme adı</label><input class="form-control" id="display_name" name="display_name" required maxlength="150" value="{{ old('display_name', $profile->display_name) }}"></div></div>
    <input type="hidden" name="is_active" value="0">
    <label class="booking-switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $profile->is_active))> Müşterilerden randevu talebi al</label>
    <p class="text-muted mt-2">Kapalı olduğunda yeni talep alınmaz. Mevcut talepler silinmez.</p>
    <h5 class="mt-4">Haftalık çalışma saatleri</h5>
    <p>Her kutu bir saatlik randevuyu temsil eder. <strong class="booking-green">Yeşil saatler açık</strong>, diğer saatler kapalıdır. Örneğin 09:00 kutusu 09:00–10:00 aralığını açar. Tüm saatler İstanbul saatidir.</p>
    <p class="text-muted">Bir günde hiç saat seçmezseniz o gün kapalı olur. Saatleri kapatmak mevcut randevuları iptal etmez; onları Randevular sayfasından yönetin.</p>
    @php($selectedHours = old('hours', $errors->any() ? [] : $profile->weekly_hours))
    <div class="booking-hours-scroll"><table class="booking-hours"><caption class="visually-hidden">Günlere göre 24 saatlik randevu uygunluğu</caption><thead><tr><th>Gün / saat</th>@for ($hour = 0; $hour < 24; $hour++)<th>{{ sprintf('%02d', $hour) }}</th>@endfor</tr></thead><tbody>
    @foreach (\App\Models\AppointmentProfile::days() as $day => $label)
        <tr data-booking-day="{{ $day }}"><th><span>{{ $label }}</span><button type="button" class="booking-day-toggle" data-day="{{ $day }}">Tümünü aç / kapat</button></th>
        @for ($hour = 0; $hour < 24; $hour++)
            <td><label class="booking-hour"><input type="checkbox" name="hours[{{ $day }}][]" value="{{ $hour }}" @checked(in_array($hour, $selectedHours[$day] ?? [])) aria-label="{{ $label }} {{ sprintf('%02d:00', $hour) }}"><span>{{ sprintf('%02d', $hour) }}</span></label></td>
        @endfor</tr>
    @endforeach
    </tbody></table></div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-secondary" type="submit">Ayarları kaydet</button><a class="btn btn-outline-secondary" href="{{ route('appointments.index') }}">Randevulara git</a></div>
</div></div></form>
@endsection
