@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ $machinery ? 'Edit Machinery' : 'Add New Machinery' }}</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ $machinery ? route('machinery.update', $machinery->id) : route('machinery.store') }}" 
                          enctype="multipart/form-data" id="machineryForm">
                        @csrf
                        @if($machinery)
                            @method('PUT')
                        @endif

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Machine Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $machinery->name ?? '') }}" required>
                                    @error('name')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category_id">Category <span class="text-danger">*</span></label>
                                    <select class="form-control @error('category_id') is-invalid @enderror" 
                                            id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        @foreach($categories ?? [] as $category)
                                            <option value="{{ $category->id }}" 
                                                {{ (old('category_id', $machinery->category_id ?? '') == $category->id) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Ownership Configuration -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owned_by">Ownership <span class="text-danger">*</span></label>
                                    <select class="form-control @error('owned_by') is-invalid @enderror" 
                                            id="owned_by" name="owned_by" required onchange="toggleOwnershipFields()">
                                        <option value="">Select Ownership</option>
                                        <option value="owned" {{ (old('owned_by', $machinery->owned_by ?? '') == 'owned') ? 'selected' : '' }}>Owned</option>
                                        <option value="rental" {{ (old('owned_by', $machinery->owned_by ?? '') == 'rental') ? 'selected' : '' }}>Rental</option>
                                    </select>
                                    @error('owned_by')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6" id="supplier_field" style="display: none;">
                                <div class="form-group">
                                    <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                    <select class="form-control @error('supplier_id') is-invalid @enderror" 
                                            id="supplier_id" name="supplier_id">
                                        <option value="">Select Supplier</option>
                                        @foreach($suppliers ?? [] as $supplier)
                                            <option value="{{ $supplier->id }}" 
                                                {{ (old('supplier_id', $machinery->supplier_id ?? '') == $supplier->id) ? 'selected' : '' }}>
                                                {{ $supplier->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('supplier_id')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Rate Configuration -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">📊 Rate Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="rate_type">Rate Type <span class="text-danger">*</span></label>
                                            <select class="form-control @error('rate_type') is-invalid @enderror" 
                                                    id="rate_type" name="rate_type" required onchange="updateRateFields()">
                                                <option value="">Select Rate Type</option>
                                                <option value="hourly" {{ (old('rate_type', $machinery->rate_type ?? '') == 'hourly') ? 'selected' : '' }}>Hourly</option>
                                                <option value="daily" {{ (old('rate_type', $machinery->rate_type ?? '') == 'daily') ? 'selected' : '' }}>Daily</option>
                                                <option value="monthly" {{ (old('rate_type', $machinery->rate_type ?? '') == 'monthly') ? 'selected' : '' }}>Monthly</option>
                                            </select>
                                            @error('rate_type')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="rate">Rate (₹) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('rate') is-invalid @enderror" 
                                                   id="rate" name="rate" value="{{ old('rate', $machinery->rate ?? '') }}" 
                                                   step="0.01" min="0" required>
                                            @error('rate')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="minimum_billing_hours">Minimum Billing Hours</label>
                                            <input type="number" class="form-control @error('minimum_billing_hours') is-invalid @enderror" 
                                                   id="minimum_billing_hours" name="minimum_billing_hours" 
                                                   value="{{ old('minimum_billing_hours', $machinery->minimum_billing_hours ?? 8) }}" 
                                                   step="0.5" min="0" max="24">
                                            <small class="form-text text-muted">Minimum hours required for billing</small>
                                            @error('minimum_billing_hours')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Billing Rules Based on Rate Type -->
                                <div id="billing_rules" class="mt-3">
                                    <!-- Daily Billing Rules -->
                                    <div id="daily_billing_rules" style="display: none;" class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Daily Billing Rules</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="daily_billing_rule" 
                                                   id="daily_any_usage" value="any_usage" 
                                                   {{ (old('daily_billing_rule', 'any_usage') == 'any_usage') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="daily_any_usage">
                                                <strong>Any Usage = Full Day</strong> - Any work (even 30 minutes) counts as full day charge
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="daily_billing_rule" 
                                                   id="daily_threshold" value="threshold" 
                                                   {{ (old('daily_billing_rule') == 'threshold') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="daily_threshold">
                                                <strong>Threshold Based</strong> - Only bill if usage exceeds minimum hours
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Monthly Billing Rules -->
                                    <div id="monthly_billing_rules" style="display: none;" class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Monthly Billing Rules</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="monthly_billing_type" 
                                                   id="monthly_fixed" value="fixed" 
                                                   {{ (old('monthly_billing_type', 'prorated') == 'fixed') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="monthly_fixed">
                                                <strong>Fixed Monthly Rate</strong> - Same amount regardless of usage days
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="monthly_billing_type" 
                                                   id="monthly_prorated" value="prorated" 
                                                   {{ (old('monthly_billing_type', 'prorated') == 'prorated') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="monthly_prorated">
                                                <strong>Prorated Monthly</strong> - (Monthly Rate ÷ Days in Month) × Active Days
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Hourly Billing Info -->
                                    <div id="hourly_billing_info" style="display: none;" class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Hourly Billing</h6>
                                        <p>Standard hourly rate calculation: <strong>Billable Hours × Hourly Rate</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Diesel Configuration -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">⛽ Diesel Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="diesel_by_company">Diesel Provided By <span class="text-danger">*</span></label>
                                            <select class="form-control @error('diesel_by_company') is-invalid @enderror" 
                                                    id="diesel_by_company" name="diesel_by_company" required onchange="toggleDieselFields()">
                                                <option value="">Select Option</option>
                                                <option value="company" {{ (old('diesel_by_company', $machinery->diesel_by_company ?? '') == 'company') ? 'selected' : '' }}>Company</option>
                                                <option value="supplier" {{ (old('diesel_by_company', $machinery->diesel_by_company ?? '') == 'supplier') ? 'selected' : '' }}>Supplier</option>
                                            </select>
                                            @error('diesel_by_company')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="operator_by_supplier">Operator Provided By</label>
                                            <select class="form-control @error('operator_by_supplier') is-invalid @enderror" 
                                                    id="operator_by_supplier" name="operator_by_supplier">
                                                <option value="">Select Option</option>
                                                <option value="company" {{ (old('operator_by_supplier', $machinery->operator_by_supplier ?? '') == 'company') ? 'selected' : '' }}>Company</option>
                                                <option value="supplier" {{ (old('operator_by_supplier', $machinery->operator_by_supplier ?? '') == 'supplier') ? 'selected' : '' }}>Supplier</option>
                                            </select>
                                            @error('operator_by_supplier')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Diesel Recovery Notice -->
                                <div id="diesel_recovery_notice" style="display: none;" class="alert alert-warning mt-3">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Diesel Recovery Notice</h6>
                                    <p><strong>This amount will be deducted from supplier billing.</strong></p>
                                    <p>When company provides diesel, the cost will be recovered from the supplier's payment through automatic deduction.</p>
                                </div>

                                <!-- Operator Count -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="number_of_operators">Number of Operators</label>
                                            <input type="number" class="form-control @error('number_of_operators') is-invalid @enderror" 
                                                   id="number_of_operators" name="number_of_operators" 
                                                   value="{{ old('number_of_operators', $machinery->number_of_operators ?? 1) }}" 
                                                   min="1" max="10">
                                            <small class="form-text text-muted">Number of operators required for this machinery</small>
                                            @error('number_of_operators')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Operational Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="vehicle_number">Vehicle Number</label>
                                    <input type="text" class="form-control @error('vehicle_number') is-invalid @enderror" 
                                           id="vehicle_number" name="vehicle_number" value="{{ old('vehicle_number', $machinery->vehicle_number ?? '') }}">
                                    @error('vehicle_number')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="operational_status">Operational Status</label>
                                    <select class="form-control @error('operational_status') is-invalid @enderror" 
                                            id="operational_status" name="operational_status">
                                        <option value="active" {{ (old('operational_status', $machinery->operational_status ?? '') == 'active') ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ (old('operational_status', $machinery->operational_status ?? '') == 'inactive') ? 'selected' : '' }}>Inactive</option>
                                        <option value="maintenance" {{ (old('operational_status', $machinery->operational_status ?? '') == 'maintenance') ? 'selected' : '' }}>Under Maintenance</option>
                                    </select>
                                    @error('operational_status')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description', $machinery->description ?? '') }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- File Uploads -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rental_agreement_file">Rental Agreement (PDF/DOC)</label>
                                    <input type="file" class="form-control @error('rental_agreement_file') is-invalid @enderror" 
                                           id="rental_agreement_file" name="rental_agreement_file" 
                                           accept=".pdf,.doc,.docx">
                                    @if($machinery && $machinery->rental_agreement_file)
                                        <small class="form-text text-muted">
                                            Current: <a href="{{ asset('uploads/' . $machinery->rental_agreement_file) }}" target="_blank">View File</a>
                                        </small>
                                    @endif
                                    @error('rental_agreement_file')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ownership_documents_file">Ownership Documents (PDF/DOC/IMG)</label>
                                    <input type="file" class="form-control @error('ownership_documents_file') is-invalid @enderror" 
                                           id="ownership_documents_file" name="ownership_documents_file" 
                                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    @if($machinery && $machinery->ownership_documents_file)
                                        <small class="form-text text-muted">
                                            Current: <a href="{{ asset('uploads/' . $machinery->ownership_documents_file) }}" target="_blank">View File</a>
                                        </small>
                                    @endif
                                    @error('ownership_documents_file')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> {{ $machinery ? 'Update Machinery' : 'Save Machinery' }}
                                    </button>
                                    <a href="{{ route('machinery.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle ownership fields
function toggleOwnershipFields() {
    const ownedBy = document.getElementById('owned_by').value;
    const supplierField = document.getElementById('supplier_field');
    
    supplierField.style.display = ownedBy === 'rental' ? 'block' : 'none';
    
    // Make supplier required if rental
    const supplierSelect = document.getElementById('supplier_id');
    supplierSelect.required = ownedBy === 'rental';
}

// Update rate fields based on rate type
function updateRateFields() {
    const rateType = document.getElementById('rate_type').value;
    
    // Hide all rule sections
    document.getElementById('daily_billing_rules').style.display = 'none';
    document.getElementById('monthly_billing_rules').style.display = 'none';
    document.getElementById('hourly_billing_info').style.display = 'none';
    
    // Show relevant section
    if (rateType === 'daily') {
        document.getElementById('daily_billing_rules').style.display = 'block';
    } else if (rateType === 'monthly') {
        document.getElementById('monthly_billing_rules').style.display = 'block';
    } else if (rateType === 'hourly') {
        document.getElementById('hourly_billing_info').style.display = 'block';
    }
}

// Toggle diesel fields
function toggleDieselFields() {
    const dieselByCompany = document.getElementById('diesel_by_company').value;
    const dieselNotice = document.getElementById('diesel_recovery_notice');
    
    dieselNotice.style.display = dieselByCompany === 'company' ? 'block' : 'none';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleOwnershipFields();
    updateRateFields();
    toggleDieselFields();
});
</script>
@endsection
