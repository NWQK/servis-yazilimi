@extends('layouts.app')
@section('page-title')
    {{ __('Role') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('role.index') }}">{{ __('Role') }}</a></li>
    <li class="breadcrumb-item" aria-current="Create"> {{ __('Create') }}</li>
@endsection

@section('content')

    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Create Role And Permissions') }}</h5>
                        </div>

                    </div>
                </div>
                <div class="card-body">
                    {{ Form::open(['url' => 'role']) }}
                    <div class="form-group">
                        {{ Form::label('title', __('Role Title'), ['class' => 'form-label']) }}
                        {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter role title') ]) }}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-12 col-md-12">
                                @foreach (\App\Services\RolePermissions::groups($permissionList) as $module => $permissions)
                                    <fieldset class="mb-4">
                                        <legend class="fs-6 fw-bold">{{ __(ucfirst($module)) }}</legend>
                                        <div class="row g-3">
                                            @foreach ($permissions as $permission)
                                                <div class="col-12 col-sm-6 col-xl-4">
                                                    <div class="form-check">
                                                        <input type="checkbox" name="user_permission[]" value="{{ $permission->id }}" id="permission-{{ $permission->id }}" class="form-check-input" @checked(in_array($permission->id, old('user_permission', $assignPermission ?? [])))>
                                                        <label for="permission-{{ $permission->id }}" class="form-check-label">{{ __(ucfirst($permission->name)) }}</label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach

                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-20 text-end">
                        {{ Form::submit(__('Create'), ['class' => 'btn btn-secondary btn-rounded']) }}
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
@endsection
