@extends('layouts.app')

@section('page-title')
    {{ __('Vehicle') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Vehicle') }}
    </li>
@endsection



@push('css-page')
<style>
.vehicle-list-table .vehicle-actions-cell { width:1%; white-space:nowrap; }
.vehicle-list-table .vehicle-actions, .vehicle-list-table .vehicle-actions form { display:inline-flex; align-items:center; flex-wrap:nowrap; gap:4px; vertical-align:middle; margin:0; }
.vehicle-list-table .vehicle-actions .avtar { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 36px; margin:0; padding:0; border:0; }
.vehicle-list-table .vehicle-actions .ti { font-size:22px; line-height:1; }
.vehicle-list-table .vehicle-actions svg { width:22px; height:22px; }
.vehicle-list-table .vehicle-actions button:disabled { opacity:.4; cursor:not-allowed; }
@media(max-width:767.98px) {
 .vehicle-list-table th,.vehicle-list-table td { padding-left:10px; padding-right:10px; }
 .vehicle-list-table .vehicle-actions .avtar { width:40px; height:40px; flex-basis:40px; }
}
</style>
@endpush
@push('script-page')
<script>
document.addEventListener('click', async function(event) {
 const button=event.target.closest('[data-copy-vehicle-link]');
 if (!button || button.disabled) return;
 const url=button.dataset.copyVehicleLink;
 const status=document.getElementById('vehicle-copy-status');
 const announce=(text)=>{ status.textContent=text; };
 const fallback=()=>{
  const input=document.createElement('textarea');
  input.value=url; input.readOnly=true; input.style.position='fixed'; input.style.left='-9999px';
  document.body.appendChild(input); input.select(); input.setSelectionRange(0,input.value.length);
  let copied=false;
  try { copied=document.execCommand('copy'); } finally { input.remove(); button.focus(); }
  if(!copied) throw new Error('copy_failed');
 };
 try {
  if(navigator.clipboard && window.isSecureContext) {
   try { await navigator.clipboard.writeText(url); } catch(error) { fallback(); }
  } else { fallback(); }
  announce('Araç bağlantısı kopyalandı.');
 } catch(error) { announce('Bağlantı kopyalanamadı. Bağlantıyı seçip kopyalayabilirsiniz.'); window.prompt('Araç bağlantısı',url); }
});
</script>
@endpush
@section('content')
    <div id="vehicle-copy-status" class="small text-success mb-2" role="status" aria-live="polite"></div>
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Vehicle List') }}</h5>
                        </div>
                        @if(auth()->user()->type !== 'client' && Gate::check('delete vehicle') && Gate::check('delete client'))
                            <div class="col-auto"><a class="btn btn-outline-danger" href="{{ route('vehicle-cleanup.index') }}"><i class="ti ti-trash align-text-bottom"></i> Temizleme işlemi</a></div>
                        @endif
                        @if (Gate::check('create vehicle'))
                            @if (auth()->user()->type !== 'client')
                                <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('vehicle-qr.index') }}">QR Etiketleri</a></div>
                            @endif
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="{{ route('vehicle.create') }}" data-title="{{ __('Create Vehicle') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Vehicle') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable vehicle-list-table">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Brand') }}</th>
                                    <th>{{ __('Model') }}</th>
                                    <th>{{ __('License Plate') }}</th>
                                    <th>{{ __('Color') }}</th>
                                    <th>{{ __('Engine Type') }}</th>
                                    @if (Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle'))
                                        <th class="text-right vehicle-actions-cell">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vehicles as $vehicle)
                                    <tr>
                                        <td>{{ vehiclePrefix() . $vehicle->vehicle_id }} </td>
                                        <td>{{ !empty($vehicle->clients) ? $vehicle->clients->name : '-' }} </td>
                                        <td>{{ !empty($vehicle->types) ? $vehicle->types->type : '-' }} </td>
                                        <td>{{ !empty($vehicle->brands) ? $vehicle->brands->name : '-' }} </td>
                                        <td>{{ $vehicle->license_plate }} </td>
                                        <td>{{ $vehicle->color }} </td>
                                        <td>{{ $vehicle->engine_type }} </td>
                                        @if(Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle'))
                                        <td class="vehicle-actions-cell">
                                                <div class="cart-action vehicle-actions">
                                                    @if(auth()->user()->type !== 'client' && Gate::check('show vehicle'))
                                                        @php($whatsappPhone = \App\Support\TurkishPhone::mobile($vehicle->clients?->phone_number))
                                                        @if($whatsappPhone)
                                                            <a class="avtar avtar-xs btn-link-success text-success" href="https://wa.me/{{ ltrim($whatsappPhone, '+') }}" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" data-bs-original-title="Araç sahibine WhatsApp’tan ulaş" aria-label="Araç sahibine WhatsApp’tan ulaş"><i class="ti ti-brand-whatsapp" aria-hidden="true"></i></a>
                                                        @endif
                                                        <button type="button" class="avtar avtar-xs btn-link-secondary text-secondary" data-copy-vehicle-link="{{ $vehicle->qrCode?->publicUrl() ?? '' }}" @disabled(!$vehicle->qrCode) title="{{ $vehicle->qrCode ? 'Araç bağlantısını kopyala' : 'Araç bağlantısı henüz oluşturulmamış' }}" aria-label="Araç bağlantısını kopyala"><i data-feather="copy" aria-hidden="true"></i></button>
                                                    @endif
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['vehicle.destroy', $vehicle->id]]) !!}
                                                    @can('show vehicle')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Show') }}" href="#"
                                                            data-url="{{ route('vehicle.show', $vehicle->id) }}"
                                                            data-title="{{ __('Details') }}"> <i data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit vehicle')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('vehicle.edit', $vehicle->id) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @if(auth()->user()->type !== 'client' && Gate::check('delete vehicle') && Gate::check('delete client'))
                                                        <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                                data-feather="trash-2"></i></a>
                                                    @endif
                                                    {!! Form::close() !!}
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
