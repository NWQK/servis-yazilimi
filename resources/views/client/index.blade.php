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
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Client List') }}</h5>
                        </div>
                        @if (Gate::check('create client'))
                            <div class="col-auto">
                                <a class="btn btn-secondary" href="{{ route('client.create') }}" data-size="lg"
                                    data-url="#" data-title="{{ __('Create Client') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Client') }}
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
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Phone Number') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    @if (Gate::check('edit client') || Gate::check('delete client'))
                                        <th class="text-right">{{ __('Action') }}</th>
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
                                        <td>{{ !empty($client->phone_number) ? $client->phone_number : '-' }} </td>
                                        <td>{{ !empty($client->clients) ? $client->clients->address : '-' }} </td>
                                        @if (Gate::check('edit client') || Gate::check('delete client'))
                                            <td>
                                                <div class="cart-action">
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
