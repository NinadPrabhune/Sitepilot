{{ Form::open(['route' => 'materials.import.excel', 'class' => 'needs-validation', 'novalidate', 'enctype' => 'multipart/form-data']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12 mb-3">
            <h6>{{ __('Instructions') }}</h6>
            <ol class="small">
                <li>{{ __('Download the template file using the link below.') }}</li>
                <li>{{ __('Fill in the material details. Category and Unit should be existing names in the system.') }}</li>
                <li>{{ __('Upload the completed file to import materials.') }}</li>
                <li>{{ __('Rows with duplicate SKU or name will be skipped.') }}</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-6">
            <a href="{{ route('materials.import.template') }}" class="btn btn-success">
                <i class="ti ti-download"></i> {{ __('Download Template') }}
            </a>
        </div>
    </div>

    <hr>

    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('file', __('Upload Excel File'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::file('file', ['class' => 'form-control', 'required' => 'required', 'accept' => '.xlsx,.csv']) }}
            @error('file') <small class="text-danger">{{ $message }}</small> @enderror
            <small class="text-muted">{{ __('Accepted formats: .xlsx, .csv') }}</small>
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Import') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
