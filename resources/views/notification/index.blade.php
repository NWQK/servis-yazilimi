@extends('layouts.app')
@section('page-title')
    E-posta şablonları
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page">E-posta şablonları</li>
@endsection
@push('script-page')
    <script src="{{ asset('assets/js/plugins/ckeditor/classic/ckeditor.js') }}"></script>
    <script>
        if ($('#classic-editor').length > 0) {
            ClassicEditor.create(document.querySelector('#classic-editor')).catch((error) => {
                console.error(error);
            });
        }
        setTimeout(() => {
            feather.replace();
        }, 500);
    </script>
@endpush
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>E-posta şablonları</h5>
                            <p class="text-muted small mb-0 mt-2">Tüm işletmelerin e-postaları merkezi SMTP hesabından gönderilir. Kalem simgesinden ortak şablonları ve gönderim durumlarını düzenleyebilirsiniz. İşletme bilgileri mesajda ilgili dükkandan alınır. SMTP bağlantısı Ayarlar → E-posta bölümünden yapılandırılır.</p>
                        </div>

                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Module') }}</th>
                                    <th>{{ __('Subject') }}</th>
                                    <th>{{ __('Email Enable') }}</th>
                                    @if (auth()->user()->type === 'super admin' || Gate::check('edit notification') || Gate::check('delete notification'))
                                        <th>{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($notifications as $item)
                                    <tr>
                                        <td>{{ $item->name }} </td>
                                        <td>{{ $item->subject }}</td>
                                        <td>
                                            @if ($item->enabled_email == 1)
                                                <span class="d-inline badge text-bg-success">{{ __('Enable') }}</span>
                                            @else
                                                <span class="d-inline badge text-bg-danger">{{ __('Disable') }}</span>
                                            @endif
                                        </td>
                                        @if (auth()->user()->type === 'super admin' || Gate::check('edit notification') || Gate::check('delete notification'))
                                            <td>
                                                <div class="cart-action">

                                                    @if(auth()->user()->type === 'super admin' || Gate::check('edit notification'))
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-bs-toggle="tooltip" data-size="lg"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('notification.edit', $item->id) }}"
                                                            data-title="{{ __('Edit Notification') }}"> <i
                                                                data-feather="edit"></i></a>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
