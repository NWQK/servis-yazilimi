@extends('layouts.app')
@php
    $profile = asset(Storage::url('upload/profile/'));
@endphp
@section('page-title')
    {{ __('Client') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        {{ __('Client') }}
    </li>
@endsection
@push('css-page')
    <style>
        .client-list-table .client-phone { white-space: nowrap; width: 1%; }
        .client-list-table .client-address { display: block; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .client-list-table .client-actions-cell { width: 1%; white-space: nowrap; }
        .client-list-table .client-actions,
        .client-list-table .client-actions form { display: inline-flex; align-items: center; flex-wrap: nowrap; gap: 4px; vertical-align: middle; margin: 0; }
        .client-list-table .client-actions .avtar { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: 0 0 36px; margin: 0; }
        .client-list-table .client-actions .ti { font-size: 22px; line-height: 1; }
        .client-list-table .client-actions svg { width: 22px; height: 22px; }
        @media (max-width: 767.98px) {
            .client-list-table th, .client-list-table td { padding-left: 10px; padding-right: 10px; }
            .client-list-table .client-address { max-width: 140px; }
            .client-list-table .client-actions .avtar { width: 40px; height: 40px; flex-basis: 40px; }
        }
    </style>
@endpush
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Client List') }}</h5>
                        </div>
                        @if (Gate::check('create client') && auth()->user()->type !== 'client')
                            <div class="col-auto d-flex flex-wrap gap-2">
                                @can('create vehicle')
                                <a class="btn btn-secondary" href="{{ route('client.create') }}"><i class="ti ti-circle-plus align-text-bottom"></i> Servisle müşteri ekle</a>
                                @endcan
                                <a class="btn btn-outline-secondary" href="{{ route('client.simple.create') }}"><i class="ti ti-user-plus align-text-bottom"></i> Müşteri ekle</a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable client-list-table">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th class="client-phone">{{ __('Phone Number') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    @if (Gate::check('show client') || Gate::check('edit client') || Gate::check('delete client'))
                                        <th class="text-right client-actions-cell">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clients as $client)
                                    <tr>
                                        <td>{{ !empty($client->clients) ? clientPrefix() . $client->clients->client_id : '-' }}
                                        </td>
                                        <td class="table-user">
                                            <img src="{{ !empty($client->avatar) ? asset(Storage::url('upload/profile')) . '/' . $client->avatar : asset(Storage::url('upload/profile')) . '/avatar.png' }}"
                                                alt="" class="mr-2 avatar-sm rounded-circle user-avatar">
                                            <a href="#"
                                                class="text-body font-weight-semibold">{{ $client->name }}</a>
                                        </td>
                                        <td>{{ $client->email }} </td>
                                        <td class="client-phone">{{ !empty($client->phone_number) ? $client->phone_number : '-' }} </td>
                                        <td><span class="client-address" title="{{ !empty($client->clients) ? $client->clients->address : '-' }}">{{ !empty($client->clients) ? $client->clients->address : '-' }}</span></td>
                                        @if (Gate::check('show client') || Gate::check('edit client') || Gate::check('delete client'))
                                            <td class="client-actions-cell">
                                                <div class="cart-action client-actions">
                                                    @php($whatsappPhone = \App\Support\TurkishPhone::mobile($client->phone_number))
                                                    @if ($whatsappPhone)
                                                        <a class="avtar avtar-xs btn-link-success text-success"
                                                            href="https://wa.me/{{ ltrim($whatsappPhone, '+') }}"
                                                            target="_blank" rel="noopener noreferrer"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="Müşteriye WhatsApp’tan ulaş"
                                                            aria-label="Müşteriye WhatsApp’tan ulaş">
                                                            <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
                                                        </a>
                                                    @endif
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['client.destroy', $client->id]]) !!}
                                                    @can('show client')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning" data-size="lg"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Show') }}"
                                                            href="{{ route('client.show', encrypt($client->id)) }}"
                                                            data-url="#" data-title="{{ __('Show') }}"> <i
                                                                data-feather="eye"></i></a>
                                                    @endcan

                                                    @can('edit client')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary "
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}"
                                                            href="{{ route('client.edit', encrypt($client->id)) }}"
                                                            data-url="#" data-title="{{ __('Edit') }}"> <i
                                                                data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete client')
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
