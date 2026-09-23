@extends('layouts.app')

@section('page-title')
    {{ __('Item') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Item') }}
    </li>
@endsection



@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Item List') }}</h5>
                        </div>
                        @if (Gate::check('create item'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="{{ route('item.create') }}" data-title="{{ __('Create Item') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Item') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-0">
                    <form method="get" action="{{ route('item.index') }}" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label for="category-filter" class="form-label">Ürün Kategorisi</label>
                            <select id="category-filter" name="category" class="form-control select2">
                                <option value="">Tüm kategoriler</option>
                                <option value="uncategorized" @selected(request('category') === 'uncategorized')>Kategorisiz</option>
                                @foreach($categories as $id => $name)
                                    <option value="{{ $id }}" @selected((string) request('category') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto align-self-end"><button class="btn btn-secondary">Filtrele</button>
                            <a href="{{ route('item.index') }}" class="btn btn-light">Temizle</a></div>
                    </form>
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Title') }}</th>
                                    <th>Ürün Kategorisi</th>
                                    <th>{{ __('Item Code') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Units') }}</th>
                                    <th>{{ __('Purchase Price') }}</th>
                                    <th>{{ __('Sales Price') }}</th>
                                    <th>{{ __('Purchase Date') }}</th>
                                    @if (Gate::check('edit item') || Gate::check('delete item') || Gate::check('show item'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td>{{ $item->title }}</td>
                                        <td>{{ $item->category?->name ?? 'Kategorisiz' }}</td>
                                        <td>{{ $item->item_code }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ !empty($item->unit) ? $item->unit->unit : '-' }} </td>
                                        <td>{{ priceFormat($item->purchase_price) }} </td>
                                        <td>{{ priceFormat($item->sales_price) }} </td>
                                        <td>{{ dateFormat($item->purchase_date) }} </td>
                                        @if (Gate::check('edit item') || Gate::check('delete item') || Gate::check('show item'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['item.destroy', $item->id]]) !!}
                                                    @can('show item')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Show') }}" href="#"
                                                            data-url="{{ route('item.show', $item->id) }}"
                                                            data-title="{{ __('Details') }}"> <i data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit item')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('item.edit', $item->id) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
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
