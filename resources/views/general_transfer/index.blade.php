@extends('layouts.main')

@section('page-title')
    {{ __('Manage General Transfers') }}
@endsection

@push('script-page')
@endpush

@section('page-breadcrumb')
    {{ __('General Transfers') }}
@endsection

@section('page-action')
<div class="d-flex">
    @stack('addButtonHook')
    
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
                    
                    {{-- Transfer Type Filter --}}
                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('transfer_type', __('Transfer Type'), ['class' => 'form-label']) }}
                            {{ Form::select(
                                'transfer_type',
                                [
                                    '' => __('All'),
                                    'machinery' => __('Machinery'),
                                    'tools_and_equipment' => __('Tools & Equipment'),
                                    'employee' => __('Employee'),
                                ],
                                request('transfer_type'),
                                ['class' => 'form-control']
                            ) }}
                        </div>
                    </div>

                    {{-- Start Date --}}
                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                            {{ Form::date(
                                'start_date',
                                request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                                ['class' => 'form-control', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>

                    {{-- End Date --}}
                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                            {{ Form::date(
                                'end_date',
                                request('end_date') ?? \Carbon\Carbon::now()->toDateString(),
                                ['class' => 'form-control', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>

                    

                    {{-- Apply / Reset --}}
                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                           id="applyfilter" data-original-title="{{ __('apply') }}">
                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                        </a>
                        <a href="{{ route('general_transfer.index') }}" class="btn btn-sm btn-danger" 
                           data-bs-toggle="tooltip" title="{{ __('Reset') }}" id="clearfilter" 
                           data-original-title="{{ __('Reset') }}">
                            <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off"></i></span>
                        </a>
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
</div>
@endsection

@push('scripts')
    @include('layouts.includes.datatable-js')
    {{ $dataTable->scripts() }}

    <script>
        // Apply filter
        $('#applyfilter').on('click', function() {
            var start_date = $('#start_date').val();
            var end_date = $('#end_date').val();
            var transfer_type = $('#transfer_type').val();

            var url = "{{ route('general_transfer.index') }}?start_date=" + start_date + "&end_date=" + end_date + "&transfer_type=" + transfer_type;
            window.location.href = url;
        });

        // Reset filter
        $('#clearfilter').on('click', function() {
            window.location.href = "{{ route('general_transfer.index') }}";
        });
    </script>
@endpush
