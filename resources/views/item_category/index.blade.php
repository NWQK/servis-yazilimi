@extends('layouts.app')

@section('page-title')
    {{ __('Ürün Kategorileri') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Ürün Kategorileri') }}
    </li>
@endsection



@section('content')
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Ürün Kategorileri') }}</h5>
                        </div>
                        @if (Gate::check('create item'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="md"
                                    data-url="{{ route('item-category.create') }}" data-title="{{ __('Kategori Ekle') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Kategori Ekle') }}
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
                                    <th>{{ __('Kategori Adı') }}</th>
                                    <th>Ürün Sayısı</th>
                                    @if (Gate::check('edit item') || Gate::check('delete item'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>


                            </thead>
                            <tbody>
                                @foreach ($categories as $type)
                                    <tr>
                                        <td>{{ $type->name }} </td>
                                        <td><a href="{{ route('item.index', ['category' => $type->id]) }}">{{ $type->items_count }}</a></td>
                                        @if (Gate::check('edit item') || Gate::check('delete item'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['item-category.destroy', $type->id]]) !!}

                                                    @can('edit item')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="md" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('item-category.edit', $type) }}"
                                                            data-title="{{ __('Kategori Düzenle') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete item')
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
