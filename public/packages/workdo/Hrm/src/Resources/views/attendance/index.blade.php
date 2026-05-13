@extends('layouts.main')
@section('page-title')
    {{ __('Manage Attendance List') }}
@endsection
@section('page-breadcrumb')
    {{ __('Attendance List') }}
@endsection
@push('css')
    @include('layouts.includes.datatable-css')
@endpush
@php
    $company_settings = getCompanyAllSetting();
@endphp


@section('page-action')
    <div>
        @permission('attendance create')
        
        @if (\Auth::check() && isset(\Auth::user()->type) && \Auth::user()->type != 'company')
<!--            <a class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md" data-title="{{ __('Create Attendance') }}"
                data-url="{{ route('attendance.create') }}" data-toggle="tooltip" title="{{ __('Create') }}">
                <i class="ti ti-plus"></i>
            </a>-->
        @endif
        
        @endpermission
    </div>
@endsection
@section('content')
    <div class="row">
        <div class="mt-2" id="multiCollapseExample1">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-end">

                        {{-- Type filter --}}
                        <div style="width: auto">
                            {{ Form::label('type', __('Type'), ['class' => 'form-label']) }}<br>
                            <div class="form-check form-check-inline form-group">
                                <input type="radio" id="monthly" value="monthly" name="type"
                                    class="form-check-input pointer"
                                    {{ request('type','monthly') == 'monthly' ? 'checked' : '' }}>
                                <label class="form-check-label pointer" for="monthly">{{ __('Monthly') }}</label>
                            </div>
                            <div class="form-check form-check-inline form-group">
                                <input type="radio" id="daily" value="daily" name="type"
                                    class="form-check-input pointer"
                                    {{ request('type') == 'daily' ? 'checked' : '' }}>
                                <label class="form-check-label pointer" for="daily">{{ __('Daily') }}</label>
                            </div>
                        </div>

                        {{-- Month filter --}}
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12 month">
                            <div class="btn-box me-2">
                                {{ Form::label('month', __('Month'), ['class' => 'form-label']) }}
                                {{ Form::month('month', request('month', date('Y-m')), ['class' => 'month-btn form-control']) }}
                            </div>
                        </div>

                        {{-- Date filter --}}
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12 date d-none">
                            <div class="btn-box me-2">
                                {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}
                                {{ Form::date('date', request('date'), ['class' => 'form-control', 'placeholder' => 'Select Date']) }}
                            </div>
                        </div>

                        {{-- Employee filter (only for admin/HR) --}}
                            @if (in_array(Auth::user()->type, Auth::user()->not_emp_type))
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                    <div class="btn-box me-2">
                                        {{ Form::label('employee', __('Employee'), ['class' => 'form-label']) }}
                                        {{ Form::select('employee', $employees, request('employee'), [
                                            'id' => 'employee',   // 👈 important
                                            'class' => 'form-control select',
                                            'placeholder' => __('All Employees'),
                                        ]) }}
                                    </div>
                                </div>
                            @endif


                        {{-- Apply / Reset buttons --}}
                        <div class="col-auto mt-4">
                            <div class="row">
                                <div class="col-auto">
                                    <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                                        id="applyfilter">
                                        <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                                    </a>
                                    <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-danger" data-bs-toggle="tooltip"
                                        title="{{ __('Reset') }}" id="clearfilter">
                                        <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    {{ $dataTable->table(['width' => '100%']) }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('layouts.includes.datatable-js')
    {{ $dataTable->scripts() }}

    <script>
        // Toggle month/date inputs based on type
        $('input[name="type"]:radio').on('change', function() {
            var type = $(this).val();
            if (type === 'monthly') {
                $('.month').removeClass('d-none').addClass('d-block');
                $('.date').removeClass('d-block').addClass('d-none');
            } else {
                $('.date').removeClass('d-none').addClass('d-block');
                $('.month').removeClass('d-block').addClass('d-none');
            }
        });
        $('input[name="type"]:radio:checked').trigger('change');

        // Apply filter reloads DataTable
        $('#applyfilter').on('click', function() {
            $('#dataTableBuilder').DataTable().ajax.reload();
        });

        // Pass filter values into DataTable AJAX
        $('#dataTableBuilder').on('preXhr.dt', function(e, settings, data) {
            data.type     = $('input[name="type"]:checked').val();
            data.month    = $('#month').val();
            data.date     = $('#date').val();
            data.employee = $('#employee').val();
        });
    </script>
@endpush
