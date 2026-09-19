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
@push('css-page')
    <style>
        #employeewizard .wizard-nav .nav-link.active {
            background: var(--bs-secondary) !important;
            color: #fff !important;
            border-color: var(--bs-secondary) !important;
        }

        #employeewizard #theme-progress-bar {
            background: var(--bs-secondary) !important;
            transition: width .3s ease, background-color .3s ease;
        }

        #employeewizard .wizard-nav .nav-link.done {
            background: var(--bs-success) !important;
            color: #fff !important;
        }

        #employeewizard .wizard-nav .nav-link {
            transition: all .3s ease;
        }
    </style>
@endpush
@section('content')
    <div class="row">
        <div class="col-12">
            <div id="employeewizard" class="form-wizard row justify-content-center">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-3">
                            <ul class="nav nav-pills nav-justified wizard-nav">
                                <li class="nav-item" data-target-form="#personalPane">
                                    <a href="#personalPane" data-bs-toggle="tab" class="nav-link active">
                                        <i class="ph-duotone ph-user-circle"></i>
                                        <span class="d-none d-sm-inline">{{ __('Info') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item" data-target-form="#attendancePane">
                                    <a href="#attendancePane" data-bs-toggle="tab" class="nav-link">
                                        <i class="ph-duotone ph-clock"></i>
                                        <span class="d-none d-sm-inline">{{ __('Service') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content">
                                <div id="bar" class="progress mb-3" style="height:7px;">
                                    <div class="bar progress-bar progress-bar-striped progress-bar-animated"
                                        id="theme-progress-bar" style="background-color: var(--bs-secondary) !important;">
                                    </div>
                                </div>
                                <div class="tab-pane show active" id="personalPane">
                                    <h1 class="text-muted text-uppercase small mb-3">
                                        <i class="ph-duotone ph-user me-1"></i> {{ __('Personal Information') }}
                                    </h1>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('ID') }}</h6>
                                                <p class="mb-20">
                                                    {{ !empty($employee) ? employeePrefix() . $employee->employee_id : '-' }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Name') }}</h6>
                                                <p class="mb-20">{{ $user->name ?? '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Email') }}</h6>
                                                <p class="mb-20">{{ $user->email ?? '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Phone Number') }}</h6>
                                                <p class="mb-20">{{ $user->phone_number ?? '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Gender') }}</h6>
                                                <p class="mb-20">{{ !empty($employee) ? $employee->gender : '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Age') }}</h6>
                                                <p class="mb-20">{{ !empty($employee) ? $employee->age : '-' }}</p>
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Joining Date') }}</h6>
                                                <p class="mb-20">
                                                    {{ !empty($employee) ? dateFormat($employee->joining_date) : '-' }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Document') }}</h6>
                                                <p class="mb-20">
                                                    @if (!empty($employee) && !empty($employee->document))
                                                        <a href="{{ asset(Storage::url('upload/document' . '/' . $employee->document)) }}"
                                                            target="_blank">{{ $employee->document ?? '-' }}</a>
                                                    @else
                                                        -
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Reference') }}</h6>
                                                <p class="mb-20">{{ !empty($employee) ? $employee->reference : '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('Address') }}</h6>
                                                <p class="mb-20">{{ !empty($employee) ? $employee->address : '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-group">
                                                <h6>{{ __('notes') }}</h6>
                                                <p class="mb-20">{{ !empty($employee) ? $employee->notes : '-' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="attendancePane">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('Service Id') }}</th>
                                                    <th>{{ __('Vehicle') }}</th>
                                                    <th>{{ __('Client') }}</th>
                                                    <th>{{ __('Service Date') }}</th>
                                                    <th>{{ __('Due Date') }}</th>
                                                    <th>{{ __('Status') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($services ?? [] as $service)
                                                    <tr>
                                                        <td>
                                                            <a
                                                                href="{{ route('service.show', \Crypt::encrypt($service->id)) }}">
                                                                {{ servicePrefix() . $service->service_id ?? '-' }}
                                                            </a>
                                                        </td>
                                                        <td>{{ $service->vehicles->license_plate ?? '-' }}
                                                        </td>
                                                        <td>{{ $service->clients->name ?? '-' }}</td>
                                                        <td>{{ dateFormat($service->service_date) }}</td>
                                                        <td>{{ dateFormat($service->due_date) }}</td>
                                                        <td>
                                                            @php
                                                                $statuses = \App\Models\Service::status();
                                                                $badgeMap = [
                                                                    'scheduled' => 'badge bg-info',
                                                                    'in_progress' => 'badge bg-warning text-dark',
                                                                    'completed' => 'badge bg-success',
                                                                    'pending_parts' => 'badge bg-secondary',
                                                                    'on_hold' => 'badge bg-secondary',
                                                                    'cancelled' => 'badge bg-danger',
                                                                ];
                                                            @endphp
                                                            <span
                                                                class="{{ $badgeMap[$service->status] ?? 'badge bg-secondary' }}">
                                                                {{ $statuses[$service->status] ?? $service->status }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            {{ __('No service records found.') }}
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="d-flex wizard justify-content-between flex-wrap gap-2 mt-3">
                                    <div class="first">
                                        <a href="javascript:void(0);" class="btn btn-secondary">{{ __('First') }}</a>
                                    </div>
                                    <div class="d-flex">
                                        <div class="previous me-2">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('Back To Previous') }}</a>
                                        </div>
                                        <div class="next">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('Next Step') }}</a>
                                        </div>
                                    </div>
                                    <div class="last">
                                        <a href="javascript:void(0);" class="btn btn-secondary">{{ __('Finish') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script-page')
    <script src="{{ asset('assets/js/plugins/wizard.min.js') }}"></script>
    <script>
        new Wizard('#employeewizard', {
            validate: false,
            progress: true
        });
    </script>
@endpush
