@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-gas-pump"></i> Diesel Issue Entry
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Diesel Recovery Notice -->
                    <div class="alert alert-info mb-4">
                        <h5><i class="fas fa-info-circle"></i> Diesel Recovery Information</h5>
                        <p><strong>Important:</strong> When diesel is issued to company-provided machinery, the cost will be automatically recovered from the supplier's payment through deduction.</p>
                        <p>This ensures accurate cost allocation and maintains financial integrity in the billing system.</p>
                    </div>

                    <form method="POST" action="{{ route('diesel.entry.store') }}" id="dieselEntryForm">
                        @csrf

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="machinery_id">Machinery <span class="text-danger">*</span></label>
                                    <select class="form-control @error('machinery_id') is-invalid @enderror" 
                                            id="machinery_id" name="machinery_id" required onchange="loadMachineryDetails()">
                                        <option value="">Select Machinery</option>
                                        @foreach($machineries ?? [] as $machinery)
                                            <option value="{{ $machinery->id }}" 
                                                data-diesel-by-company="{{ $machinery->diesel_by_company }}"
                                                data-supplier-id="{{ $machinery->supplier_id }}"
                                                {{ (old('machinery_id') == $machinery->id) ? 'selected' : '' }}>
                                                {{ $machinery->name }} ({{ $machinery->machine_id }})
                                                @if($machinery->diesel_by_company)
                                                    <span class="badge badge-info">Company Diesel</span>
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('machinery_id')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="issue_date">Issue Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('issue_date') is-invalid @enderror" 
                                           id="issue_date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required>
                                    @error('issue_date')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="site_id">Site <span class="text-danger">*</span></label>
                                    <select class="form-control @error('site_id') is-invalid @enderror" 
                                            id="site_id" name="site_id" required>
                                        <option value="">Select Site</option>
                                        @foreach($sites ?? [] as $site)
                                            <option value="{{ $site->id }}" 
                                                {{ (old('site_id') == $site->id) ? 'selected' : '' }}>
                                                {{ $site->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('site_id')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Diesel Quantity and Rate -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-calculator"></i> Diesel Quantity & Rate
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="diesel_quantity">Diesel Quantity (Liters) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('diesel_quantity') is-invalid @enderror" 
                                                   id="diesel_quantity" name="diesel_quantity" 
                                                   value="{{ old('diesel_quantity') }}" step="0.1" min="0" required
                                                   onchange="calculateTotal()">
                                            @error('diesel_quantity')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                            <small class="form-text text-muted">Enter diesel quantity in liters</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="diesel_rate">Diesel Rate (₹/Liter) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('diesel_rate') is-invalid @enderror" 
                                                   id="diesel_rate" name="diesel_rate" 
                                                   value="{{ old('diesel_rate', config('machinery.default_diesel_rate', 90)) }}" 
                                                   step="0.01" min="0" required onchange="calculateTotal()">
                                            @error('diesel_rate')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                            <small class="form-text text-muted">Rate per liter</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="total_amount">Total Amount (₹)</label>
                                            <input type="number" class="form-control" id="total_amount" 
                                                   value="0" step="0.01" readonly style="background-color: #f8f9fa; font-weight: bold;">
                                            <small class="form-text text-muted">Auto-calculated: Quantity × Rate</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="reference_number">Reference Number</label>
                                            <input type="text" class="form-control @error('reference_number') is-invalid @enderror" 
                                                   id="reference_number" name="reference_number" 
                                                   value="{{ old('reference_number') }}" placeholder="e.g., DO-2026-001">
                                            @error('reference_number')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                            <small class="form-text text-muted">Delivery order or reference number</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Diesel Recovery Preview -->
                                <div id="dieselRecoveryPreview" class="mt-3" style="display: none;">
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-exclamation-triangle"></i> Diesel Recovery Preview</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Amount to Recover:</strong> ₹<span id="recoveryAmount">0</span>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>From Supplier:</strong> <span id="supplierName">-</span>
                                            </div>
                                        </div>
                                        <p class="mb-0 mt-2">
                                            <small>This amount will be automatically deducted from the supplier's next payment request.</small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Issuer Information -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="issued_by">Issued By <span class="text-danger">*</span></label>
                                    <select class="form-control @error('issued_by') is-invalid @enderror" 
                                            id="issued_by" name="issued_by" required>
                                        <option value="">Select Person</option>
                                        @foreach($users ?? [] as $user)
                                            <option value="{{ $user->id }}" 
                                                {{ (old('issued_by') == $user->id) ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('issued_by')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="driver_name">Driver Name</label>
                                    <input type="text" class="form-control @error('driver_name') is-invalid @enderror" 
                                           id="driver_name" name="driver_name" value="{{ old('driver_name') }}">
                                    @error('driver_name')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Name of the driver who received diesel</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="vehicle_number">Vehicle Number</label>
                                    <input type="text" class="form-control @error('vehicle_number') is-invalid @enderror" 
                                           id="vehicle_number" name="vehicle_number" value="{{ old('vehicle_number') }}">
                                    @error('vehicle_number')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Vehicle/tanker number</small>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="notes">Notes/Remarks</label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" 
                                              id="notes" name="notes" rows="3" placeholder="Any additional notes about this diesel issue...">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Supporting Documents -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt"></i> Supporting Documents
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="delivery_document">Delivery Document (Optional)</label>
                                            <input type="file" class="form-control @error('delivery_document') is-invalid @enderror" 
                                                   id="delivery_document" name="delivery_document" 
                                                   accept=".pdf,.jpg,.jpeg,.png">
                                            <small class="form-text text-muted">Delivery challan, receipt, or other document</small>
                                            @error('delivery_document')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="photograph">Photograph (Optional)</label>
                                            <input type="file" class="form-control @error('photograph') is-invalid @enderror" 
                                                   id="photograph" name="photograph" 
                                                   accept=".jpg,.jpeg,.png">
                                            <small class="form-text text-muted">Photograph of diesel issue</small>
                                            @error('photograph')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Diesel Issues -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history"></i> Recent Diesel Issues for This Machinery
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="recentDieselIssues">
                                    <!-- Will be loaded via AJAX -->
                                    <div class="text-center">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Diesel Entry
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                    <a href="{{ route('diesel.index') }}" class="btn btn-info">
                                        <i class="fas fa-list"></i> View All Entries
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" name="workspace_id" value="{{ auth()->user()->workspace_id }}">
                        <input type="hidden" name="created_by" value="{{ auth()->id() }}">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentMachinery = null;

// Load machinery details
function loadMachineryDetails() {
    const machinerySelect = document.getElementById('machinery_id');
    const selectedOption = machinerySelect.options[machinerySelect.selectedIndex];
    
    if (selectedOption) {
        currentMachinery = {
            id: selectedOption.value,
            dieselByCompany: selectedOption.dataset.dieselByCompany === 'true',
            supplierId: selectedOption.dataset.supplierId
        };
        
        // Update diesel recovery preview
        updateDieselRecoveryPreview();
        
        // Load recent diesel issues
        loadRecentDieselIssues();
    }
}

// Calculate total amount
function calculateTotal() {
    const quantity = parseFloat(document.getElementById('diesel_quantity').value) || 0;
    const rate = parseFloat(document.getElementById('diesel_rate').value) || 0;
    const total = quantity * rate;
    
    document.getElementById('total_amount').value = total.toFixed(2);
    
    // Update diesel recovery preview
    updateDieselRecoveryPreview();
}

// Update diesel recovery preview
function updateDieselRecoveryPreview() {
    const preview = document.getElementById('dieselRecoveryPreview');
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    
    if (currentMachinery && currentMachinery.dieselByCompany && totalAmount > 0) {
        document.getElementById('recoveryAmount').textContent = totalAmount.toFixed(2);
        
        // Get supplier name
        if (currentMachinery.supplierId) {
            fetch(`/api/suppliers/${currentMachinery.supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('supplierName').textContent = data.supplier.name;
                    }
                })
                .catch(error => {
                    console.error('Error loading supplier:', error);
                });
        }
        
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// Load recent diesel issues
function loadRecentDieselIssues() {
    if (!currentMachinery) return;
    
    fetch(`/api/diesel/recent/${currentMachinery.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.issues.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-sm">';
                html += '<thead><tr><th>Date</th><th>Quantity</th><th>Rate</th><th>Total</th><th>Issued By</th></tr></thead><tbody>';
                
                data.issues.forEach(issue => {
                    html += `
                        <tr>
                            <td>${issue.issue_date}</td>
                            <td>${issue.diesel_quantity} L</td>
                            <td>₹${issue.diesel_rate}</td>
                            <td class="font-weight-bold">₹${issue.total_amount}</td>
                            <td>${issue.issued_by_name}</td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                document.getElementById('recentDieselIssues').innerHTML = html;
            } else {
                document.getElementById('recentDieselIssues').innerHTML = 
                    '<p class="text-muted text-center">No recent diesel issues found for this machinery.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading recent issues:', error);
            document.getElementById('recentDieselIssues').innerHTML = 
                '<p class="text-danger text-center">Error loading recent diesel issues.</p>';
        });
}

// Reset form
function resetForm() {
    document.getElementById('dieselEntryForm').reset();
    document.getElementById('total_amount').value = '0';
    document.getElementById('dieselRecoveryPreview').style.display = 'none';
    currentMachinery = null;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate on page load if values exist
    const quantity = document.getElementById('diesel_quantity').value;
    const rate = document.getElementById('diesel_rate').value;
    
    if (quantity && rate) {
        calculateTotal();
    }
});
</script>
@endsection
