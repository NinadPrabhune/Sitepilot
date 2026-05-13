@extends('layouts.main')

@section('page-title', __('Create DPR - ') . $machinery->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('machineries.index') }}">{{ __('Machinery') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machineries.show', $machinery) }}">{{ $machinery->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machinery.dpr.index', $machinery) }}">{{ __('DPRs') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Create Daily Progress Report') }}</h5>
                <div class="card-header-right">
                    <span class="badge bg-warning">{{ __('Direct Machinery Flow') }}</span>
                </div>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('machinery.dpr.store', $machinery) }}" method="POST">
                    @csrf
                    
                    <!-- Site Selection -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Site') }} <span class="text-danger">*</span></label>
                        <select name="site_id" class="form-select @error('site_id') is-invalid @enderror" required>
                            <option value="">{{ __('Select Site') }}</option>
                            @foreach(\Workdo\Taskly\Entities\Project::where('workspace', auth()->user()->workspace_id)->get() as $site)
                                <option value="{{ $site->id }}" 
                                    {{ old('site_id', $machinery->site_id) == $site->id ? 'selected' : '' }}>
                                    {{ $site->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('Machinery must belong to the selected site') }}</small>
                        @error('site_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Date -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" 
                               value="{{ old('date', now()->format('Y-m-d')) }}" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Machinery Rate Information -->
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="ti ti-info-circle"></i> {{ __('Machinery Rate Information') }}</h6>
                                <div class="mb-2">
                                    <strong>{{ __('Rate Type') }}:</strong> 
                                    <span class="badge bg-primary">{{ ucfirst($machinery->rate_type ?? 'hourly') }}</span>
                                </div>
                                <div class="mb-2">
                                    <strong>{{ __('Rate') }}:</strong> 
                                    ₹{{ number_format($machinery->rate ?? 0, 2) }}
                                    <span class="text-muted">
                                        {{ $machinery->rate_type === 'hourly' ? '/hour' : '/day' }}
                                    </span>
                                </div>
                                @if($machinery->minimum_billing_hours)
                                <div class="mb-2">
                                    <strong>{{ __('Minimum Billing') }}:</strong> 
                                    {{ $machinery->minimum_billing_hours }} {{ __('hours') }}
                                </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>{{ __('Diesel Provided By') }}:</strong> 
                                    <span class="badge {{ $machinery->diesel_by_company ? 'bg-success' : 'bg-warning' }}">
                                        {{ $machinery->diesel_by_company ? 'Company' : 'Supplier' }}
                                    </span>
                                </div>
                                @if($machinery->diesel_by_company)
                                <div class="mb-2">
                                    <strong>{{ __('Default Diesel Rate') }}:</strong> 
                                    ₹{{ number_format(config('machinery.default_diesel_rate', 90), 2) }}/L
                                </div>
                                @endif
                                <div class="text-muted">
                                    <small>
                                        {{ __('This information will be used for billing calculations.') }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Previous Reading Display -->
                    <div id="previousReadingInfo" class="alert alert-info mb-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Previous Reading:</strong> <span id="previousReadingValue">{{ $machinery->last_reading ?? 0 }}</span>
                                <br>
                                <small class="text-muted">Last updated: {{ $machinery->last_reading_date ?? 'Unknown' }}</small>
                            </div>
                            <div class="validation-badge success" id="readingValidationBadge">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Machine Readings -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Start Reading') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="machine_start_reading" id="startReading"
                                       class="form-control @error('machine_start_reading') is-invalid @enderror"
                                       value="{{ old('machine_start_reading', $machinery->last_reading ?? 0) }}" 
                                       required onchange="validateReadings()">
                                @error('machine_start_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('End Reading') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="machine_end_reading" id="endReading"
                                       class="form-control @error('machine_end_reading') is-invalid @enderror"
                                       value="{{ old('machine_end_reading') }}" 
                                       required onchange="validateReadings()">
                                @error('machine_end_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Idle Hours') }}</label>
                                <input type="number" step="0.01" name="machine_idle_reading" id="idleReading"
                                       class="form-control @error('machine_idle_reading') is-invalid @enderror"
                                       value="{{ old('machine_idle_reading', 0) }}" onchange="validateReadings()">
                                @error('machine_idle_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Reading Validation Display -->
                    <div id="readingValidation" class="mb-3">
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

                    <!-- Billing Preview -->
                    <div id="billingPreview" class="alert alert-light" style="display: none;">
                        <h6><i class="fas fa-calculator"></i> {{ __('Billing Preview') }}</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>{{ __('Rate Type') }}:</strong> 
                                <span id="rateType">{{ $machinery->rate_type ?? 'hourly' }}</span>
                            </div>
                            <div class="col-md-6 text-right">
                                <strong>{{ __('Estimated Amount') }}:</strong> 
                                <span id="estimatedAmount">₹0</span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                {{ __('This is an estimate based on current readings. Final amount will be calculated on approval.') }}
                            </small>
                        </div>
                    </div>

                    <!-- Diesel & Operators -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Diesel Consumption (Liters)') }}</label>
                                <input type="number" step="0.01" name="diesel_consumption" 
                                       class="form-control @error('diesel_consumption') is-invalid @enderror"
                                       value="{{ old('diesel_consumption', 0) }}">
                                @error('diesel_consumption')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Number of Operators') }}</label>
                                <input type="number" name="number_of_operators" 
                                       class="form-control @error('number_of_operators') is-invalid @enderror"
                                       value="{{ old('number_of_operators', 1) }}">
                                @error('number_of_operators')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Work Details -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Work Details') }}</label>
                        <textarea name="work_details" rows="3" 
                                  class="form-control @error('work_details') is-invalid @enderror">{{ old('work_details') }}</textarea>
                        @error('work_details')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Maintenance Notes -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Maintenance Notes') }}</label>
                        <textarea name="maintenance_notes" rows="2" 
                                  class="form-control @error('maintenance_notes') is-invalid @enderror">{{ old('maintenance_notes') }}</textarea>
                        @error('maintenance_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('machinery.dpr.index', $machinery) }}" class="btn btn-outline-secondary">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-plus"></i> {{ __('Create DPR') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Machinery data for calculations
const machineryData = {
    rateType: '{{ $machinery->rate_type ?? "hourly" }}',
    rate: {{ $machinery->rate ?? 0 }},
    lastReading: {{ $machinery->last_reading ?? 0 }},
    dieselByCompany: {{ $machinery->diesel_by_company ? 'true' : 'false' }}
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Show previous reading info
    if (machineryData.lastReading > 0) {
        document.getElementById('previousReadingInfo').style.display = 'block';
    }
    
    // Initialize readings
    validateReadings();
});

// Validate readings and update display
function validateReadings() {
    const startReading = parseFloat(document.getElementById('startReading').value) || 0;
    const endReading = parseFloat(document.getElementById('endReading').value) || 0;
    const idleReading = parseFloat(document.getElementById('idleReading').value) || 0;
    const prevReading = machineryData.lastReading;
    
    let isValid = true;
    let warningMessage = '';
    
    // Validation checks
    if (endReading < startReading) {
        isValid = false;
        warningMessage = 'End reading cannot be less than start reading';
    } else if (endReading < prevReading) {
        isValid = false;
        warningMessage = `End reading (${endReading}) cannot be less than previous reading (${prevReading})`;
    } else if (idleReading > 0 && idleReading < startReading) {
        isValid = false;
        warningMessage = 'Idle reading cannot be less than start reading';
    } else if (idleReading > 0 && idleReading > endReading) {
        isValid = false;
        warningMessage = 'Idle reading cannot be greater than end reading';
    }
    
    // Check for large jumps
    if (prevReading > 0 && endReading > 0) {
        const jump = endReading - prevReading;
        if (jump > 500) {
            warningMessage = `Large reading jump detected: ${jump} units`;
        }
    }
    
    // Update validation badge
    updateValidationBadge(isValid, warningMessage);
    
    // Calculate and display progress
    if (isValid) {
        updateProgressDisplay(startReading, endReading, idleReading);
        updateBillingPreview(startReading, endReading);
    }
}

// Update validation badge
function updateValidationBadge(isValid, message) {
    const badge = document.getElementById('readingValidationBadge');
    
    if (isValid) {
        badge.className = 'validation-badge success';
        badge.innerHTML = '<i class="fas fa-check"></i>';
    } else {
        badge.className = 'validation-badge error';
        badge.innerHTML = '<i class="fas fa-times"></i>';
    }
    
    // Show warning message if any
    if (message) {
        showWarning(message);
    }
}

// Update progress display
function updateProgressDisplay(startReading, endReading, idleReading) {
    const totalProgress = endReading - startReading;
    const workingHours = totalProgress / 100; // Assume 100 units = 1 hour
    const idleHours = idleReading > 0 ? (idleReading - startReading) / 100 : 0;
    
    // Update display
    document.getElementById('totalProgress').textContent = totalProgress.toFixed(2);
    document.getElementById('workingHours').textContent = workingHours.toFixed(2);
    document.getElementById('idleHours').textContent = idleHours.toFixed(2);
}

// Update billing preview
function updateBillingPreview(startReading, endReading) {
    const progress = endReading - startReading;
    const hours = progress / 100; // Assume 100 units = 1 hour
    
    let estimatedAmount = 0;
    
    switch (machineryData.rateType) {
        case 'hourly':
            estimatedAmount = hours * machineryData.rate;
            break;
        case 'daily':
            estimatedAmount = progress > 0 ? machineryData.rate : 0;
            break;
        case 'monthly':
            // Monthly is handled at payment request level
            estimatedAmount = 0;
            break;
    }
    
    // Update preview
    document.getElementById('estimatedAmount').textContent = '₹' + estimatedAmount.toFixed(2);
    document.getElementById('billingPreview').style.display = 'block';
}

// Show warning message
function showWarning(message) {
    // Remove existing warnings
    const existingWarning = document.querySelector('.reading-warning');
    if (existingWarning) {
        existingWarning.remove();
    }
    
    // Create new warning
    const warning = document.createElement('div');
    warning.className = 'alert alert-warning alert-dismissible fade show reading-warning mt-2';
    warning.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert after reading validation section
    const readingValidation = document.getElementById('readingValidation');
    readingValidation.parentNode.insertBefore(warning, readingValidation.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (warning.parentNode) {
            warning.remove();
        }
    }, 5000);
}
</script>

<style>
.validation-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.validation-badge.success {
    background: #28a745;
}

.validation-badge.error {
    background: #dc3545;
}

.validation-badge.warning {
    background: #ffc107;
    color: #212529;
}

.reading-warning {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endpush
@endsection
