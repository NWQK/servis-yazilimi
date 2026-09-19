@extends('layouts.app')
@section('page-title')
    {{ __('N8N Template') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('N8N Template') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('N8N Template') }}</h5>
                        </div>
                        @if (Gate::check('create n8n'))
                            <div class="col-auto">
                                <a href="#" class="btn btn-secondary customModal" data-size="md"
                                    data-url="{{ route('n8n.create') }}" data-title="{{ __('Create n8n') }}">
                                    <i class="ti ti-circle-plus align-text-bottom"></i> {{ __('Create n8n') }}
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
                                    <th>{{ __('Module') }}</th>
                                    <th>{{ __('URL') }}</th>
                                    <th>{{ __('Enable N8n') }}</th>

                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($n8ns as $n8n)
                                    <tr>
                                        <td>{{ \App\Models\N8n::module()[$n8n->module] }}</td>
                                        <td>{{ $n8n->url }}</td>
                                        <td>
                                            @if ($n8n->status == 1)
                                                <span class="d-inline badge text-bg-success">{{ __('Enable') }}</span>
                                            @else
                                                <span class="d-inline badge text-bg-danger">{{ __('Disable') }}</span>
                                            @endif

                                        </td>

                                        <td>
                                            <div class="cart-action">
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['n8n.destroy', $n8n->id]]) !!}
                                                @can('edit n8n')
                                                    <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                        data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"
                                                        href="#" data-title="{{ __('Edit N8n') }}"
                                                        data-url="{{ route('n8n.edit', $n8n->id) }}"> <i
                                                            data-feather="edit"></i></a>
                                                @endcan

                                                @can('delete n8n')
                                                    <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog"
                                                        data-bs-toggle="tooltip" data-bs-original-title="{{ __('Detete') }}"
                                                        href="#"> <i data-feather="trash-2"></i></a>
                                                @endcan

                                                {!! Form::close() !!}
                                            </div>

                                        </td>
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
