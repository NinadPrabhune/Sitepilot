@extends('layouts.main')
@section('page-title')
{{__('Manage Material Issues')}}
@endsection
@push('script-page')
@endpush
@section('page-breadcrumb')
{{__('Material Issue')}}
@endsection
@section('page-action')
<div class="d-flex">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
       </a>
    @permission('material-issue create')
    <a data-size="xl" data-url="{{ route('material-issues.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" data-bs-original-title="{{__('Create Material Issue')}}" title="{{__('Create Material Issue')}}" data-title="{{__('Create Material Issue')}}" class="btn btn-sm btn-primary">
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
                            {{ Form::date(
                            'start_date',
                            request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                            ['class' => 'form-control', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>

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

                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                           id="applyfilter" data-original-title="{{ __('apply') }}">
                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                        </a>
                        <a href="#!" class="btn btn-sm btn-danger " data-bs-toggle="tooltip"
                           title="{{ __('Reset') }}" id="clearfilter" data-original-title="{{ __('Reset') }}">
                            <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off "></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
// Filter functionality
$(document).on('click', '#applyfilter', function() {
    $('#material-issues-table').DataTable().draw();
});

// Reload table when filters change
$('#start_date,#end_date').change(function(){
    $('#material-issues-table').DataTable().ajax.reload();
});

// Handle select all checkbox
$(document).on('change','#select-all-rows',function(){
    $('.row-checkbox').prop('checked',$(this).prop('checked'));
});



// Update clear filter to also clear filters
$(document).on('click', '#clearfilter', function() {
    $('input[name=start_date]').val('');
    $('input[name=end_date]').val('');
    $('#material-issues-table').DataTable().draw();
});

</script>

@endpush
