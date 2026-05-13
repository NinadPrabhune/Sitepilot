@extends('layouts.main')
@section('page-title')
    {{__('Manage Machinery')}}
@endsection
@push('script-page')
@endpush
@section('page-breadcrumb')
  {{__('Machinery')}}
@endsection
@section('page-action')
    <div class="d-flex">
        @stack('addButtonHook')
       @permission('machinery import')--}}
<!--            <a href="#"  class="btn btn-sm btn-primary me-2" data-ajax-popup="true" data-title="{{__('Machinery Import')}}" data-url="{{ route('machineries.file.import') }}"  data-toggle="tooltip" title="{{ __('Import') }}"><i class="ti ti-file-import"></i>
            </a>-->
      @endpermission
      
    
      
       <a href="{{ route('projects.show', getActiveProject()) }}" class="btn btn-sm btn-light border me-2">
    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
</a>


      @permission('machinery create')
            <a data-size="xl" data-url="{{ route('machineries.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Create')}}" data-title="{{__('Create Machinery')}}"  class="btn btn-sm btn-primary">
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
        <div class="col-xl-12">
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
@endpush
