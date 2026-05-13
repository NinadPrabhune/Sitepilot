@extends('layouts.main')
@section('page-title', __('Import Opening Stock'))
@section('page-breadcrumb', __('Import Opening Stock'))

@section('page-action')
    <a href="{{ route('opening-stock.index') }}" class="btn btn-sm btn-primary">
        <i class="ti ti-arrow-left"></i> {{ __('Back to Opening Stock') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>{{ __('Instructions') }}</h5>
                        <ol class="mb-3">
                            <li>{{ __('Download the template file using the button below.') }}</li>
                            <li>{{ __('The template contains all active materials with their IDs and units.') }}</li>
                            <li>{{ __('Fill in the quantity column for the materials you want to add as opening stock.') }}</li>
                            <li>{{ __('Upload the completed file to import opening stock for the selected project.') }}</li>
                            <li>{{ __('Rows with empty quantity will be skipped.') }}</li>
                            <li>{{ __('Duplicate opening stock for the same project and material will be skipped.') }}</li>
                        </ol>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <a href="{{ route('opening-stock.import.download-template') }}" class="btn btn-success">
                            <i class="ti ti-download"></i> {{ __('Download Opening Stock Template') }}
                        </a>
                    </div>
                </div>

                <hr>

                {{ Form::open(['route' => 'opening-stock.import', 'class' => 'needs-validation', 'novalidate', 'enctype' => 'multipart/form-data']) }}
                
                <div class="row">
                    <div class="form-group col-md-6">
                        {{ Form::label('project_id', __('Select Project / Site'), ['class' => 'form-label']) }}<x-required></x-required>
                        {{ Form::select('project_id', $projects, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Project')]) }}
                        @error('project_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group col-md-6">
                        {{ Form::label('file', __('Upload Excel File'), ['class' => 'form-label']) }}<x-required></x-required>
                        {{ Form::file('file', ['class' => 'form-control', 'required' => 'required', 'accept' => '.xlsx,.csv']) }}
                        @error('file') <small class="text-danger">{{ $message }}</small> @enderror
                        <small class="text-muted">{{ __('Accepted formats: .xlsx, .csv') }}</small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-upload"></i> {{ __('Import Opening Stock') }}
                        </button>
                    </div>
                </div>

                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
@endsection
