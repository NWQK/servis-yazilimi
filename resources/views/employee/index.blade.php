@extends('layouts.app')
@php
    $profile = asset(Storage::url('upload/profile/'));
@endphp
@section('page-title')
    {{ __('Employee') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Employee') }}
    </li>
@endsection
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Employee List') }}</h5>
                        </div>
                        @if (Gate::check('create employee'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="{{ route('employee.create') }}" data-title="{{ __('Create Employee') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Employee') }}
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
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Phone Number') }}</th>
                                    @if (Gate::check('manage employee') || Gate::check('create employee') || Gate::check('show employee'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    <tr>
                                        <td>{{ !empty($employee->employees) ? employeePrefix() . $employee->employees->employee_id : '-' }}
                                        </td>
                                        <td class="table-user">
                                            <img src="{{ !empty($employee->avatar) ? asset(Storage::url('upload/profile')) . '/' . $employee->avatar : asset(Storage::url('upload/profile')) . '/avatar.png' }}"
                                                alt="" class="mr-2 avatar-sm rounded-circle user-avatar">
                                            <a href="#"
                                                class="text-body font-weight-semibold">{{ $employee->name }}</a>
                                        </td>
                                        <td>{{ $employee->email }} </td>
                                        <td>{{ !empty($employee->phone_number) ? $employee->phone_number : '-' }} </td>
                                        @if (Gate::check('edit employee') || Gate::check('delete employee') || Gate::check('show employee'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['employee.destroy', $employee->id]]) !!}
                                                    @can('show employee')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning" data-size="lg"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Show') }}"
                                                            href="{{ route('employee.show', encrypt($employee->id)) }}"
                                                            data-url="#" data-title="{{ __('Details') }}"> <i
                                                                data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit employee')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('employee.edit', $employee->id) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete employee')
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
