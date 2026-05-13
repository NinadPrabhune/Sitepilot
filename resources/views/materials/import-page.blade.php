@extends('layouts.main')

@section('page-title', __('Import Materials'))
@section('page-breadcrumb', __('Import Materials'))

@section('page-action')
<div class="d-flex gap-2">
    <a href="{{ route('material.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-8">
        {{-- Import Form Card --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">{{ __('Upload File') }}</h5>
            </div>
            <div class="card-body">
                {{ Form::open(['route' => 'materials.import.process', 'class' => 'needs-validation', 'novalidate', 'enctype' => 'multipart/form-data', 'id' => 'importForm']) }}
                
                <div class="row">
                    <div class="form-group col-md-12">
                        {{ Form::label('file', __('Upload Excel/CSV File'), ['class' => 'form-label']) }}<x-required></x-required>
                        {{ Form::file('file', ['class' => 'form-control', 'accept' => '.xlsx,.xls,.csv', 'required' => 'required', 'id' => 'materialFile']) }}
                        <small class="text-muted">{{ __('Supported file formats: xlsx, xls, csv (Max size: 5MB)') }}</small>
                        @error('file')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary" id="importBtn">
                            <i class="ti ti-file-import me-2"></i>
                            {{ __('Import Materials') }}
                        </button>
                        <a href="{{ route('material.index') }}" class="btn btn-light ms-2">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </div>

                {{ Form::close() }}
            </div>
        </div>

        {{-- Import Instructions Card --}}
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">{{ __('Import Instructions') }}</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        {{ __('Download the template file and fill in the material details.') }}
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        {{ __('Category should be an existing category name in the system.') }}
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        {{ __('Rows with duplicate name + SKU combination will be skipped.') }}
                    </li>
                    <li class="mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        {{ __('Price must be a valid number.') }}
                    </li>
                    <li class="mb-0">
                        <i class="ti ti-check text-success me-2"></i>
                        {{ __('Required fields: name, category, price') }}
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        {{-- Template Download Card --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">{{ __('Download Template') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    {{ __('Download the Excel template to see the expected format.') }}
                </p>
                <a href="{{ route('materials.import.template') }}" class="btn btn-success w-100">
                    <i class="ti ti-download me-2"></i>
                    {{ __('Download Template') }}
                </a>
            </div>
        </div>

        {{-- Import Errors Card --}}
        <div id="importErrorsCard" class="card shadow-sm border-0 mt-4 border-danger" style="display: none;">
            <div class="card-header bg-danger text-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Import Errors') }}</h5>
                <span class="badge bg-white text-danger" id="errorCount">0</span>
            </div>
            <div class="card-body" id="importErrorsBody">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="errorsTable">
                        <thead>
                            <tr>
                                <th>{{ __('Row') }}</th>
                                <th>{{ __('Error Message') }}</th>
                            </tr>
                        </thead>
                        <tbody id="errorsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if there are errors from server-side session
    @if(session('import_errors') && count(session('import_errors')) > 0)
        showErrors(@json(session('import_errors')));
    @endif
    
    // Handle form submission
    const form = document.getElementById('importForm');
    const importBtn = document.getElementById('importBtn');
    const fileInput = document.getElementById('materialFile');
    
    // Client-side validation for file size
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                alert('File size exceeds 5MB limit. Please choose a smaller file.');
                this.value = '';
            }
        }
    });
    
    form.addEventListener('submit', function(e) {
        // Show loading state
        importBtn.disabled = true;
        importBtn.innerHTML = '<i class="ti ti-loader ti-spin me-2"></i> {{ __("Importing...") }}';
        
        // Allow form to submit normally
        return true;
    });
    
    // Function to display errors
    function showErrors(errors) {
        const errorsCard = document.getElementById('importErrorsCard');
        const errorCount = document.getElementById('errorCount');
        const errorsTableBody = document.getElementById('errorsTableBody');
        
        // Clear previous errors
        errorsTableBody.innerHTML = '';
        
        // Set error count
        errorCount.textContent = errors.length;
        
        // Add each error to the table
        errors.forEach(function(error) {
            const row = document.createElement('tr');
            const parts = error.split(': ');
            const rowNum = parts[0] || '';
            const message = parts.slice(1).join(': ') || error;
            
            row.innerHTML = '<td>' + rowNum + '</td><td>' + message + '</td>';
            errorsTableBody.appendChild(row);
        });
        
        // Show the errors card
        errorsCard.style.display = 'block';
        
        // Scroll to errors section
        errorsCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Expose showErrors function globally for AJAX calls
    window.showImportErrors = showErrors;
});
</script>
@endsection
