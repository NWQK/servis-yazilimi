@extends('layouts.app')

@section('page-title')
    {{ __('Quotation') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Quotation') }}
    </li>
@endsection



@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Quotation List') }}</h5>
                        </div>
                        @if (Gate::check('create quotation'))
                            <div class="col-auto">
                                <a class="btn btn-secondary"
                                    href="{{ route('quotation.create') }}"data-title="{{ __('Create Quotation') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Quotation') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Quotation') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Quotation Date') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    @if (Gate::check('show quotation') ||
                                            Gate::check('edit quotation') ||
                                            Gate::check('delete quotation') ||
                                            Gate::check('convert quotation'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotations as $quotation)
                                    {{-- @dd($quotation) --}}

                                    <tr>
                                        <td>{{ quotationPrefix() . $quotation->quotation_id }} </td>

                                        <td>{{ !empty($quotation->clients) ? $quotation->clients->name : '-' }} </td>
                                        <td><a class="customModal" href="#" data-size="lg" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Details') }}" href="#"
                                                data-url="{{ route('vehicle.show', $quotation->vehicle_id) }}"
                                                data-title="{{ __('Vehicle Details') }}">
                                                {{ vehiclePrefix() }}{{ !empty($quotation->vehicles) ? $quotation->vehicles->vehicle_id : '' }}
                                            </a> </td>
                                        <td>
                                            {{ dateFormat($quotation->quotation_date) }}

                                        </td>
                                        <td>
                                            @if ($quotation->status == 'draft')
                                                <span
                                                    class="badge bg-light-dark">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                            @elseif($quotation->status == 'sent')
                                                <span
                                                    class="badge bg-light-warning">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                            @elseif($quotation->status == 'accepted')
                                                <span
                                                    class="badge bg-light-success">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                            @elseif($quotation->status == 'rejected' || $quotation->status == 'cancelled')
                                                <span
                                                    class="badge bg-light-danger">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                            @else
                                                <span
                                                    class="badge bg-light-info">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                            @endif
                                        </td>
                                        @if (Gate::check('show quotation') ||
                                                Gate::check('edit quotation') ||
                                                Gate::check('delete quotation') ||
                                                Gate::check('convert quotation'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['quotation.destroy', $quotation->id]]) !!}
                                                    @if ($quotation->convert_service == 0)
                                                        @can('convert quotation')
                                                            <a class="avtar avtar-xs btn-link-info text-info"
                                                                href="{{ route('quotation.convert', $quotation->id) }}"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-original-title="{{ __('Convert to service') }}"
                                                                data-title="{{ __('Create Item') }}">
                                                                <i data-feather="arrow-right-circle"></i>
                                                            </a>
                                                        @endcan
                                                    @endif


                                                    @can('show quotation')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detail') }}"
                                                            href="{{ route('quotation.show', \Crypt::encrypt($quotation->id)) }}">
                                                            <i data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit quotation')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}"
                                                            href="{{ route('quotation.edit', \Crypt::encrypt($quotation->id)) }}"
                                                            data-title="{{ __('Edit Quotation') }}"> <i
                                                                data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete quotation')
                                                        <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                                data-feather="trash-2"></i></a>
                                                    @endcan
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
