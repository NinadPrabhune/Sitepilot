@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-tachometer-alt"></i> 
                        {{ $dpr ? 'Edit Daily Progress Report' : 'Create Daily Progress Report' }}
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-info" onclick="showBillingPreview()">
                            <i class="fas fa-calculator"></i> Billing Preview
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Validation Alerts Container -->
                    <div id="validationAlerts" class="mb-3" style="display: none;"></div>
                    
                    <form method="POST" action="{{ $dpr ? route('daily-progress-report.update', $dpr->id) : route('daily-progress-report.store') }}" 
                          enctype="multipart/form-data" id="dprForm">
                        @csrf
                        @if($dpr)
                            @method('PUT')
                        @endif

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="date">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date') is-invalid @enderror" 
                                           id="date" name="date" value="{{ old('date', $dpr->date ?? '') }}" 
                                           required onchange="validateDate()">
                                    @error('date')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="machinery_id">Machinery <span class="text-danger">*</span></label>
                                    <select class="form-control @error('machinery_id') is-invalid @enderror" 
                                            id="machinery_id" name="machinery_id" required onchange="loadMachineryDetails()">
                                        <option value="">Select Machinery</option>
                                        @foreach($machineries ?? [] as $machinery)
                                            <option value="{{ $machinery->id }}" 
                                                data-rate-type="{{ $machinery->rate_type }}"
                                                data-rate="{{ $machinery->rate }}"
                                                data-diesel-by-company="{{ $machinery->diesel_by_company }}"
                                                {{ (old('machinery_id', $dpr->machinery_id ?? '') == $machinery->id) ? 'selected' : '' }}>
                                                {{ $machinery->name }} ({{ $machinery->machine_id }})
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
                                    <label for="site_id">Site <span class="text-danger">*</span></label>
                                    <select class="form-control @error('site_id') is-invalid @enderror" 
                                            id="site_id" name="site_id" required>
                                        <option value="">Select Site</option>
                                        @foreach($sites ?? [] as $site)
                                            <option value="{{ $site->id }}" 
                                                {{ (old('site_id', $dpr->site_id ?? '') == $site->id) ? 'selected' : '' }}>
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

                        <!-- Meter Reading Section -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-tachometer-alt"></i> Meter Reading Entry
                                    <small class="text-muted ml-2">Previous reading will be shown for validation</small>
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Previous Reading Display -->
                                <div id="previousReadingInfo" class="alert alert-info mb-3" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Previous Reading:</strong> 
                                            <span id="previousReadingValue">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Previous Date:</strong> 
                                            <span id="previousReadingDate">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Days Since:</strong> 
                                            <span id="daysSincePrevious">-</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="machine_start_reading">Start Reading <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('machine_start_reading') is-invalid @enderror" 
                                                   id="machine_start_reading" name="machine_start_reading" 
                                                   value="{{ old('machine_start_reading', $dpr->machine_start_reading ?? '') }}" 
                                                   required onchange="validateReadings()">
                                            @error('machine_start_reading')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="machine_idle_reading">Idle Reading</label>
                                            <input type="number" class="form-control @error('machine_idle_reading') is-invalid @enderror" 
                                                   id="machine_idle_reading" name="machine_idle_reading" 
                                                   value="{{ old('machine_idle_reading', $dpr->machine_idle_reading ?? '') }}" 
                                                   onchange="validateReadings()">
                                            @error('machine_idle_reading')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="machine_end_reading">End Reading <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('machine_end_reading') is-invalid @enderror" 
                                                   id="machine_end_reading" name="machine_end_reading" 
                                                   value="{{ old('machine_end_reading', $dpr->machine_end_reading ?? '') }}" 
                                                   required onchange="validateReadings()">
                                            @error('machine_end_reading')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="billable_hours">Billable Hours</label>
                                            <input type="number" class="form-control" id="billable_hours" 
                                                   value="{{ old('billable_hours', $dpr->billable_hours ?? '') }}" 
                                                   readonly style="background-color: #f8f9fa;">
                                            <small class="form-text text-muted">Auto-calculated</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reading Validation Display -->
                                <div id="readingValidation" class="mt-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="alert alert-success">
                                                <strong>Total Progress:</strong> 
                                                <span id="totalProgress">0</span> units
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="alert alert-info">
                                                <strong>Working Hours:</strong> 
                                                <span id="workingHours">0</span> hrs
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="alert alert-warning">
                                                <strong>Idle Hours:</strong> 
                                                <span id="idleHours">0</span> hrs
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Work Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="work_details">Work Details</label>
                                    <textarea class="form-control @error('work_details') is-invalid @enderror" 
                                              id="work_details" name="work_details" rows="3">{{ old('work_details', $dpr->work_details ?? '') }}</textarea>
                                    @error('work_details')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="number_of_operators">Number of Operators</label>
                                    <input type="number" class="form-control @error('number_of_operators') is-invalid @enderror" 
                                           id="number_of_operators" name="number_of_operators" 
                                           value="{{ old('number_of_operators', $dpr->number_of_operators ?? 1) }}" 
                                           min="1" max="10">
                                    @error('number_of_operators')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Diesel Section -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-gas-pump"></i> Diesel Consumption
                                    <small class="text-muted ml-2">Only if diesel was consumed</small>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="diesel_consumption">Diesel Consumed (Liters)</label>
                                            <input type="number" class="form-control @error('diesel_consumption') is-invalid @enderror" 
                                                   id="diesel_consumption" name="diesel_consumption" 
                                                   value="{{ old('diesel_consumption', $dpr->diesel_consumption ?? '') }}" 
                                                   step="0.1" min="0" onchange="calculateDieselCost()">
                                            @error('diesel_consumption')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="diesel_rate">Diesel Rate (₹/Liter)</label>
                                            <input type="number" class="form-control" id="diesel_rate" 
                                                   value="{{ config('machinery.default_diesel_rate', 90) }}" 
                                                   step="0.01" min="0" readonly style="background-color: #f8f9fa;">
                                            <small class="form-text text-muted">Default rate applied</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="diesel_total_cost">Total Cost (₹)</label>
                                            <input type="number" class="form-control" id="diesel_total_cost" 
                                                   value="0" step="0.01" readonly style="background-color: #f8f9fa;">
                                            <small class="form-text text-muted">Auto-calculated</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Diesel Recovery Notice -->
                                <div id="dieselRecoveryNotice" class="alert alert-warning mt-3" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>This diesel cost will be recovered from supplier billing.</strong>
                                    <br>Amount: ₹<span id="dieselRecoveryAmount">0</span>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="maintenance_notes">Maintenance Notes</label>
                                    <textarea class="form-control @error('maintenance_notes') is-invalid @enderror" 
                                              id="maintenance_notes" name="maintenance_notes" rows="2">{{ old('maintenance_notes', $dpr->maintenance_notes ?? '') }}</textarea>
                                    @error('maintenance_notes')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="machinery_advances">Machinery Advances</label>
                                    <textarea class="form-control @error('machinery_advances') is-invalid @enderror" 
                                              id="machinery_advances" name="machinery_advances" rows="2">{{ old('machinery_advances', $dpr->machinery_advances ?? '') }}</textarea>
                                    @error('machinery_advances')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="consumption_file">Supporting Document (Optional)</label>
                                    <input type="file" class="form-control @error('consumption_file') is-invalid @enderror" 
                                           id="consumption_file" name="consumption_file" 
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="form-text text-muted">PDF, JPG, PNG up to 2MB</small>
                                    @error('consumption_file')
                                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Billing Preview Modal -->
                        <div class="modal fade" id="billingPreviewModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-calculator"></i> Billing Preview
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="billingPreviewContent">
                                            <!-- Billing preview will be loaded here -->
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary" onclick="saveWithBillingPreview()">
                                            <i class="fas fa-save"></i> Save DPR
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-save"></i> {{ $dpr ? 'Update DPR' : 'Create DPR' }}
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="showBillingPreview()">
                                        <i class="fas fa-calculator"></i> Preview Billing
                                    </button>
                                    <a href="{{ route('daily-progress-report.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" name="workspace_id" value="{{ old('workspace_id', $dpr->workspace_id ?? auth()->user()->workspace_id) }}">
                        <input type="hidden" name="created_by" value="{{ old('created_by', $dpr->created_by ?? auth()->id()) }}">
                        <input type="hidden" name="billable_hours" id="hidden_billable_hours">
                        <input type="hidden" name="calculated_amount" id="hidden_calculated_amount">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let machineryData = {};
let validationErrors = [];

// Load machinery details
function loadMachineryDetails() {
    const machinerySelect = document.getElementById('machinery_id');
    const selectedOption = machinerySelect.options[machinerySelect.selectedIndex];
    
    if (selectedOption) {
        machineryData = {
            rateType: selectedOption.dataset.rateType,
            rate: parseFloat(selectedOption.dataset.rate),
            dieselByCompany: selectedOption.dataset.dieselByCompany === 'true'
        };
        
        // Update diesel recovery notice
        updateDieselRecoveryNotice();
        
        // Load previous reading
        loadPreviousReading();
    }
}

// Load previous reading
function loadPreviousReading() {
    const machineryId = document.getElementById('machinery_id').value;
    const date = document.getElementById('date').value;
    
    if (!machineryId || !date) return;
    
    fetch(`/api/daily-progress-report/previous-reading/${machineryId}?date=${date}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.previous_reading) {
                document.getElementById('previousReadingValue').textContent = data.previous_reading;
                document.getElementById('previousReadingDate').textContent = data.previous_date;
                document.getElementById('daysSincePrevious').textContent = data.days_since;
                document.getElementById('previousReadingInfo').style.display = 'block';
            } else {
                document.getElementById('previousReadingInfo').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading previous reading:', error);
        });
}

// Validate readings
function validateReadings() {
    const startReading = parseFloat(document.getElementById('machine_start_reading').value) || 0;
    const endReading = parseFloat(document.getElementById('machine_end_reading').value) || 0;
    const idleReading = parseFloat(document.getElementById('machine_idle_reading').value) || 0;
    const previousReading = parseFloat(document.getElementById('previousReadingValue').textContent) || 0;
    
    validationErrors = [];
    
    // Clear previous validation alerts
    clearValidationAlerts();
    
    // Check end reading less than start reading
    if (endReading < startReading) {
        validationErrors.push({
            type: 'error',
            message: 'End reading cannot be less than start reading'
        });
    }
    
    // Check end reading less than previous reading
    if (endReading < previousReading) {
        validationErrors.push({
            type: 'error',
            message: `End reading (${endReading}) cannot be less than previous day's reading (${previousReading})`
        });
    }
    
    // Check idle reading validation
    if (idleReading > 0) {
        if (idleReading < startReading) {
            validationErrors.push({
                type: 'error',
                message: 'Idle reading cannot be less than start reading'
            });
        }
        
        if (idleReading > endReading) {
            validationErrors.push({
                type: 'error',
                message: 'Idle reading cannot be greater than end reading'
            });
        }
    }
    
    // Check for large jumps
    if (previousReading > 0) {
        const jump = endReading - previousReading;
        const threshold = getJumpThreshold();
        
        if (jump > threshold) {
            validationErrors.push({
                type: 'warning',
                message: `Unusually large meter jump detected: ${jump} units (threshold: ${threshold})`
            });
        }
    }
    
    // Calculate billable hours
    calculateBillableHours();
    
    // Show validation alerts
    showValidationAlerts();
}

// Calculate billable hours
function calculateBillableHours() {
    const startReading = parseFloat(document.getElementById('machine_start_reading').value) || 0;
    const endReading = parseFloat(document.getElementById('machine_end_reading').value) || 0;
    const idleReading = parseFloat(document.getElementById('machine_idle_reading').value) || 0;
    
    let totalProgress = endReading - startReading;
    let idleHours = idleReading > 0 ? (idleReading - startReading) / 10 : 0; // Assuming 10 units = 1 hour
    let workingHours = (totalProgress / 10) - idleHours;
    
    // Validate hours
    if (workingHours > 24) {
        validationErrors.push({
            type: 'error',
            message: 'Billable hours cannot exceed 24 hours'
        });
    }
    
    if (workingHours < 0) {
        workingHours = 0;
    }
    
    // Update display
    document.getElementById('totalProgress').textContent = totalProgress;
    document.getElementById('workingHours').textContent = workingHours.toFixed(2);
    document.getElementById('idleHours').textContent = idleHours.toFixed(2);
    document.getElementById('billable_hours').value = workingHours.toFixed(2);
    document.getElementById('hidden_billable_hours').value = workingHours.toFixed(2);
}

// Calculate diesel cost
function calculateDieselCost() {
    const liters = parseFloat(document.getElementById('diesel_consumption').value) || 0;
    const rate = parseFloat(document.getElementById('diesel_rate').value) || 90;
    const totalCost = liters * rate;
    
    document.getElementById('diesel_total_cost').value = totalCost.toFixed(2);
    
    // Update diesel recovery notice
    updateDieselRecoveryAmount(totalCost);
}

// Update diesel recovery notice
function updateDieselRecoveryNotice() {
    const dieselByCompany = machineryData.dieselByCompany;
    const notice = document.getElementById('dieselRecoveryNotice');
    
    notice.style.display = dieselByCompany ? 'block' : 'none';
}

// Update diesel recovery amount
function updateDieselRecoveryAmount(amount) {
    if (machineryData.dieselByCompany) {
        document.getElementById('dieselRecoveryAmount').textContent = amount.toFixed(2);
    }
}

// Get jump threshold (simplified)
function getJumpThreshold() {
    // This would be based on machinery type and historical patterns
    return 1000; // Default threshold
}

// Show validation alerts
function showValidationAlerts() {
    const container = document.getElementById('validationAlerts');
    
    if (validationErrors.length === 0) {
        container.style.display = 'none';
        return;
    }
    
    let html = '';
    validationErrors.forEach(error => {
        const alertClass = error.type === 'error' ? 'alert-danger' : 'alert-warning';
        html += `
            <div class="alert ${alertClass} alert-dismissible fade show">
                <i class="fas fa-${error.type === 'error' ? 'exclamation-triangle' : 'exclamation-circle'}"></i>
                ${error.message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.style.display = 'block';
}

// Clear validation alerts
function clearValidationAlerts() {
    document.getElementById('validationAlerts').innerHTML = '';
    document.getElementById('validationAlerts').style.display = 'none';
}

// Show billing preview
function showBillingPreview() {
    const billableHours = parseFloat(document.getElementById('billable_hours').value) || 0;
    
    if (!machineryData.rateType || billableHours <= 0) {
        alert('Please select machinery and enter valid readings first');
        return;
    }
    
    // Calculate estimated amount
    let estimatedAmount = 0;
    let billingDescription = '';
    
    switch (machineryData.rateType) {
        case 'hourly':
            estimatedAmount = billableHours * machineryData.rate;
            billingDescription = `${billableHours} hours × ₹${machineryData.rate}/hour`;
            break;
        case 'daily':
            estimatedAmount = billableHours > 0 ? machineryData.rate : 0;
            billingDescription = billableHours > 0 ? 'Full day rate (any usage)' : 'No work - No charge';
            break;
        case 'monthly':
            // Monthly is handled at payment request level
            estimatedAmount = 0;
            billingDescription = 'Handled in monthly payment request';
            break;
    }
    
    // Get diesel cost
    const dieselCost = parseFloat(document.getElementById('diesel_total_cost').value) || 0;
    
    // Build preview content
    const previewContent = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-info-circle"></i> Billing Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Rate Type:</strong></td>
                        <td>${machineryData.rateType.charAt(0).toUpperCase() + machineryData.rateType.slice(1)}</td>
                    </tr>
                    <tr>
                        <td><strong>Rate:</strong></td>
                        <td>₹${machineryData.rate}</td>
                    </tr>
                    <tr>
                        <td><strong>Billable Hours:</strong></td>
                        <td>${billableHours}</td>
                    </tr>
                    <tr>
                        <td><strong>Calculation:</strong></td>
                        <td>${billingDescription}</td>
                    </tr>
                    <tr>
                        <td><strong>Estimated Amount:</strong></td>
                        <td class="font-weight-bold">₹${estimatedAmount.toFixed(2)}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-gas-pump"></i> Diesel Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Consumption:</strong></td>
                        <td>${document.getElementById('diesel_consumption').value || 0} liters</td>
                    </tr>
                    <tr>
                        <td><strong>Rate:</strong></td>
                        <td>₹${document.getElementById('diesel_rate').value}/liter</td>
                    </tr>
                    <tr>
                        <td><strong>Total Cost:</strong></td>
                        <td>₹${dieselCost.toFixed(2)}</td>
                    </tr>
                    ${machineryData.dieselByCompany ? `
                    <tr>
                        <td colspan="2" class="text-warning">
                            <i class="fas fa-exclamation-triangle"></i> This will be recovered from supplier
                        </td>
                    </tr>
                    ` : ''}
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('billingPreviewContent').innerHTML = previewContent;
    document.getElementById('hidden_calculated_amount').value = estimatedAmount;
    
    // Show modal
    $('#billingPreviewModal').modal('show');
}

// Save with billing preview
function saveWithBillingPreview() {
    $('#billingPreviewModal').modal('hide');
    document.getElementById('submitBtn').click();
}

// Validate date
function validateDate() {
    const dateInput = document.getElementById('date');
    const selectedDate = new Date(dateInput.value);
    const today = new Date();
    
    if (selectedDate > today) {
        validationErrors.push({
            type: 'error',
            message: 'Date cannot be in the future'
        });
        showValidationAlerts();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load machinery details if editing
    const machinerySelect = document.getElementById('machinery_id');
    if (machinerySelect.value) {
        loadMachineryDetails();
    }
    
    // Initialize readings if editing
    const startReading = document.getElementById('machine_start_reading').value;
    if (startReading) {
        validateReadings();
    }
});
</script>
@endsection
