<li class="dropdown pc-h-item" id="appointment-notifications" data-url="{{ route('appointments.notifications') }}">
    <a href="#" id="appointment-notifications-toggle" class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0 position-relative"
        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Randevu bildirimleri" title="Randevu bildirimleri" role="button">
        <i class="ti ti-bell" aria-hidden="true"></i>
        <span id="appointment-notifications-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" hidden></span>
    </a>
    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown" aria-labelledby="appointment-notifications-toggle" style="width:350px;max-width:calc(100vw - 24px)">
        <div class="px-3 py-2 border-bottom"><strong>Randevu bildirimleri</strong><small class="d-block text-muted">Onay bekleyen talepler</small></div>
        <div id="appointment-notifications-list" style="max-height:340px;overflow-y:auto" aria-live="polite"><p class="p-3 mb-0 text-muted">Bildirimler yükleniyor…</p></div>
        <a class="dropdown-item border-top text-center" href="{{ route('appointments.index', ['status' => 'pending']) }}">Tüm talepleri görüntüle</a>
    </div>
</li>
<script src="{{ asset('js/appointment-notifications.js') }}" defer></script>
