@extends('layouts.main')

@section('page-title')
    {{ __('Manage Material Issues') }}
@endsection

@section('page-breadcrumb')
    {{ __('Material Issue') }}
@endsection

@section('page-action')
    <div class="d-flex">
        <a href="{{ url()->previous() }}" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
        </a>
        @permission('material-issue create')
        <a data-size="xl" data-url="{{ route('material-issues.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create Material Issue') }}" title="{{ __('Create Material Issue') }}" data-title="{{ __('Create Material Issue') }}" class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
        @endpermission
    </div>
@endsection

@push('css')
    @include('layouts.includes.datatable-css')
@endpush

@section('content')
    <div class="row">
        <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-end">
                        <div class="col-xl-3 col-lg-12 col-12">
                            <div class="btn-box me-2">
                                {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                                {{ Form::date('start_date', request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(), ['class' => 'form-control', 'placeholder' => 'Select Date']) }}
                            </div>
                        </div>

                        <div class="col-xl-3 col-lg-12 col-12">
                            <div class="btn-box me-2">
                                {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                                {{ Form::date('end_date', request('end_date') ?? \Carbon\Carbon::now()->toDateString(), ['class' => 'form-control', 'placeholder' => 'Select Date']) }}
                            </div>
                        </div>

                        <div class="col-auto float-end mt-4">
                            <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="{{ __('Apply') }}" id="applyfilter" data-original-title="{{ __('apply') }}">
                                <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                            </a>
                            <a href="#!" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="{{ __('Reset') }}" id="clearfilter" data-original-title="{{ __('Reset') }}">
                                <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off"></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-12">
            <x-data-table
                id="material-issues-table"
                :route="route('material-issues.index')"
                :columns="[
                    ['label' => 'Issue Number', 'data' => 'issue_number', 'name' => 'issue_number'],
                    ['label' => 'Issue Date', 'data' => 'issue_date', 'name' => 'issue_date'],
                    ['label' => 'Issue To', 'data' => 'issue_to', 'name' => 'issue_to'],
                    ['label' => 'Items', 'data' => 'items_count', 'name' => 'items_count'],
                    ['label' => 'Total Quantity', 'data' => 'total_quantity', 'name' => 'total_quantity'],
                    ['label' => 'Status', 'data' => 'status', 'name' => 'status'],
                    ['label' => 'Action', 'data' => 'action', 'name' => 'action', 'orderable' => false, 'searchable' => false]
                ]"
                :filters="['start_date', 'end_date']"
            />
        </div>
    </div>
@endsection

@push('scripts')
    @include('layouts.includes.datatable-js')
@endpush
