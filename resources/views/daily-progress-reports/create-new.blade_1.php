

{{ Form::open(['route' => 'daily-progress-reports.store', 'class' => 'needs-validation', 'novalidate', 'files' => true, 'id' => 'dpr-form']) }}
{{ Form::hidden('activity_completed_id', $activity_completed_id ?? null) }}
{{ Form::hidden('site_id', $defaultSiteId ?? getActiveProject()) }}

{{-- Display validation errors --}}
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Validation Errors Found</h5>
    <hr>
    <ul class="mb-0">
        @foreach($errors->all() as $error)
            <li><strong>{{ $error }}</strong></li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- Display general error messages --}}
@if(Session::has('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="fas fa-exclamation-circle"></i> {{ __('Error') }}</h5>
    <p>{{ Session::get('error') }}</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- Display success messages --}}
@if(Session::has('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="fas fa-check-circle"></i> {{ __('Success') }}</h5>
    <p>{{ Session::get('success') }}</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="modal-body">
    <div class="row">

       {{-- Machinery Name --}}
<div class="form-group col-md-3">
    {{ Form::label('machinery_id', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
    {{ Form::select('machinery_id', ['' => __('Select Machinery')] + $machineryList->pluck('name', 'id')->toArray(), null, ['class' => 'form-control', 'required' => 'required', 'id' => 'machinery_id']) }}
</div>


        {{-- Owned By --}}
        <div class="form-group col-md-3">
            {{ Form::label('owned_by_new', __('Owned By'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('owned_by_new', [
                '' => __('Select Owned By'),
                'owned' => 'Owned',
                'rental' => 'Rental'
            ], null, ['class' => 'form-control', 'id' => 'owned_by_new', 'disabled'=>'disabled']) }}
            
             {{ Form::hidden('owned_by', '') }}
        </div>

        {{-- Current Site --}}
        <div class="form-group col-md-3">
            {{ Form::label('site_id_new', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id_new', ['' => __('Select Site')] + $sites->toArray(), $defaultSiteId ?? getActiveProject(), ['class' => 'form-control', 'id' => 'site_id_new', 'disabled'=>'disabled']) }}
            
             {{ Form::hidden('owned_by', '') }}
        </div>

        {{-- Date --}}
        <div class="form-group col-md-3">
            {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('date', now(), ['class' => 'form-control', 'required' => 'required']) }}
        </div>

        <hr>
        <h6 class="mb-3">{{ __('Machinery Details') }}</h6>

        {{-- Previous Reading Display --}}
        <div id="previousReadingInfo" class="alert alert-info mb-3" style="display: none;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Previous Reading:</strong> <span id="previousReadingValue">-</span>
                    <br>
                    <small class="text-muted">Last updated: <span id="previousReadingDate">-</span></small>
                </div>
                <div class="validation-badge" id="readingValidationBadge">
                    <i class="fas fa-question-circle"></i>
                </div>
            </div>
        </div>

        {{-- Machine Start Reading --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_start_reading', __('Machine Start Reading'), ['class' => 'form-label']) }}
            {{ Form::number('machine_start_reading', null, ['class' => 'form-control', 'min' => 0, 'step' => '0.01','placeholder' => 'e.g. 100.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="startReadingError"></div>
        </div>

        {{-- Machine End Reading --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_end_reading', __('Closing Reading'), ['class' => 'form-label']) }}
            {{ Form::number('machine_end_reading', null, ['class' => 'form-control', 'min' => 0, 'step' => '0.01','placeholder' => 'e.g. 100.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="endReadingError"></div>
        </div>

        {{-- Idle Hours --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_idle_reading', __('Idle Hours'), ['class' => 'form-label']) }}
            {{ Form::number('machine_idle_reading', null, ['class' => 'form-control', 'min' => 0, 'step' => '0.01','placeholder' => 'e.g. 1.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="idleHoursError"></div>
        </div>

        {{-- Number of Operators --}}
        <div class="form-group col-md-4">
            {{ Form::label('number_of_operators', __('No. of Operators'), ['class' => 'form-label']) }}
            {{ Form::number('number_of_operators', null, ['class' => 'form-control', 'min' => 0, 'id' => 'number_of_operators']) }}
        </div>

        {{-- Operator Names --}}
        <div class="form-group col-md-8">
            {{ Form::label('operator_names', __('Operator Names'), ['class' => 'form-label']) }}
            {{ Form::text('operator_names', null, ['class' => 'form-control', 'placeholder' => 'e.g. John Doe, Jane Smith']) }}
            <small class="text-muted">{{ __('Enter operator names separated by commas') }}</small>
        </div>

        {{-- Work Details --}}
        <div class="form-group col-md-6 d-none">
            {{ Form::label('work_details', __('Work Details'), ['class' => 'form-label']) }}
            {{ Form::textarea('work_details', null, ['class' => 'form-control', 'rows' => 3]) }}
        </div>
        
        {{-- Reference File --}}
        <div class="form-group col-md-6">
            {{ Form::label('consumption_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('consumption_file', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx']) }}
            <small class="text-muted">{{ __('Allowed: pdf, jpg, jpeg, png, doc, docx') }}</small>
        </div>

        {{-- Maintenance Notes --}}
        <div class="form-group col-md-6">
            {{ Form::label('maintenance_notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('maintenance_notes', null, ['class' => 'form-control', 'rows' => 3]) }}
        </div>

        {{ Form::hidden('consumption_type', 'fuel') }}
        {{ Form::hidden('activity_id', $activity_id ?? null) }}

        <hr>
        
        {{-- Rate Override Section --}}
        <div class="rate-override-section bg-light p-3 mb-3">
            <h6 class="text-muted"><i class="ti ti-calculator"></i> {{ __('Rate Configuration') }}</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2">
                        <strong>{{ __('Rate Type') }}:</strong> 
                        <span class="badge bg-primary" id="machinery-rate-type">-</span>
                    </div>
                    <label>{{ __('Standard Rate (Auto):') }}</label>
                    <div class="fw-bold" id="standard-rate">₹0.00</div>
                    <small class="text-muted" id="rate-description">{{ __('Based on machinery master and rate history') }}</small>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="enable-rate-override">
                        <label class="form-check-label" for="enable-rate-override">
                            {{ __('Override Rate') }}
                            <small class="text-muted">(Admin/Accounts only)</small>
                        </label>
                    </div>
                </div>
            </div>
            
            {{-- Override Fields (Hidden by default) --}}
            <div id="rate-override-fields" class="mt-3" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <label for="override-rate">{{ __('Override Rate:') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" id="override-rate" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label for="override-reason">{{ __('Override Reason:') }} <span class="text-danger">*</span></label>
                        <textarea id="override-reason" class="form-control" rows="2" placeholder="Please specify reason for rate override (e.g., Night shift, special conditions, etc.)"></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Enhanced Calculation Preview Panel --}}
        <div class="calculation-preview-panel bg-light p-3 mb-3">
            <h6 class="text-muted"><i class="ti ti-calculator"></i> {{ __('Billing Calculation Preview') }}</h6>
            <div class="row">
                <div class="col-md-3">
                    <label>{{ __('Total Progress:') }}</label>
                    <div class="fw-bold" id="preview-total-progress">0.00</div>
                    <small class="text-muted">Total reading difference</small>
                </div>
                <div class="col-md-3">
                    <label>{{ __('Working Hours:') }}</label>
                    <div class="fw-bold" id="preview-working-hours">0.00</div>
                    <small class="text-muted">After idle adjustment</small>
                </div>
                <div class="col-md-3">
                    <label>{{ __('Billable Hours:') }}</label>
                    <div class="fw-bold" id="preview-billable-hours">0.00</div>
                    <small class="text-muted" id="billable-hours-note">Based on rate type</small>
                </div>
                <div class="col-md-3">
                    <label>{{ __('Estimated Amount:') }}</label>
                    <div class="fw-bold text-primary" id="preview-amount">₹0.00</div>
                    <small class="text-muted d-block" id="rate-usage-note"></small>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="alert alert-info mb-0">
                        <h6><i class="ti ti-info-circle"></i> {{ __('Rate Type Logic') }}</h6>
                        <div id="rate-type-explanation">
                            <small class="text-muted">{{ __('Select machinery to see rate calculation logic') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning mb-0" id="validation-warnings" style="display: none;">
                        <h6><i class="ti ti-alert-triangle"></i> {{ __('Validation Warnings') }}</h6>
                        <div id="validation-messages"></div>
                    </div>
                </div>
            </div>
        </div>

        <hr>
        <div id="fuel-consumption-section">
            <h6 class="mb-3">{{ __('Fuel Consumption Details') }}</h6>
            <div class="alert alert-info d-none" id="rental-fuel-notice">
                <i class="fas fa-info-circle"></i> 
                <strong> Rental Machinery:</strong> Diesel costs will be recovered from supplier as per rental agreement.
            </div>
            <div class="form-group col-md-12" id="fuel-consumption-form">
                <button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">{{ __('Add Item') }}</button>
                <table class="table table-bordered mt-2" id="consumption-items-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">{{ __('Material') }}</th>
                            <th>{{ __('Current Stock') }}</th>
                            <th>{{ __('Quantity | Unit') }}</th>
                            <th>{{ __('Remarks') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>

{{ Form::close() }}

{{-- CSS for Validation Badges and Warnings --}}
<style>
.validation-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.validation-badge.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.validation-badge.warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
}

.validation-badge.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.alert.alert-info .validation-badge {
    background-color: rgba(255, 255, 255, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.is-invalid {
    border-color: #dc3545 !important;
    background-color: #fff8f8 !important;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.calculation-preview-panel {
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
}

.calculation-preview-panel .alert {
    margin-bottom: 0;
    padding: 0.75rem;
}

.rate-type-explanation {
    font-size: 0.875rem;
}

#validation-warnings {
    border-left: 4px solid #ffc107;
}

#validation-warnings h6 {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

#validation-messages small {
    display: block;
    margin-bottom: 0.25rem;
}

.form-control.is-invalid:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

/* Animation for validation badge */
.validation-badge {
    transition: all 0.3s ease;
}

.validation-badge.success:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
}

.validation-badge.error:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
}

/* Enhanced calculation preview */
.calculation-preview-panel .row > div {
    border-right: 1px solid #e9ecef;
    padding: 0.75rem;
}

.calculation-preview-panel .row > div:last-child {
    border-right: none;
}

.calculation-preview-panel .fw-bold {
    font-size: 1.1rem;
}

.calculation-preview-panel .text-primary {
    font-size: 1.2rem;
    font-weight: 600;
}
</style>

{{-- JavaScript Validation for Machine Readings --}}
<script src="{{ asset('js/dpr-validation-simple.js') }}"></script>

<script>
$(document).ready(function () {
    // Use raw JSON encoding to avoid Blade directive issues
    let materials = {!! $materials ? json_encode($materials) : '[]' !!};
    let machineryList = {!! $machineryList ? json_encode($machineryList->keyBy('id')) : '{}' !!};
    
    let rowIndex = 0;
    let currentRate = 0;
    let minimumBillingHours = 0;
    let isRental = false;
    let currentMachinery = null;
    let previousReading = 0;

    
    // Load initial fuel stock if site_id is already selected
    const initialSiteId = $('#site_id').val();
    if (initialSiteId) {
        loadFuelStock(initialSiteId);
    }

    // Function to load and display machinery rate information
    function loadMachineryRateInfo(machineryId) {
        if (!machineryId || !machineryList[machineryId]) {
            resetRateInfo();
            return;
        }
        
        const machinery = machineryList[machineryId];
        currentMachinery = machinery;
        
        // Update rate type badge
        const rateType = machinery.rate_type || 'hourly';
        const rate = parseFloat(machinery.rate) || 0;
        const displayRateType = rateType.charAt(0).toUpperCase() + rateType.slice(1);
        
        $('#machinery-rate-type').text(displayRateType);
        $('#standard-rate').text('₹' + rate.toFixed(2));
        
        // Update rate description
        let description = '';
        switch(rateType) {
            case 'hourly':
                description = 'Charged per hour of operation';
                break;
            case 'daily':
                description = 'Fixed daily rate (minimum ' + (machinery.minimum_billing_hours || 8) + ' hours)';
                break;
            case 'monthly':
                description = 'Monthly rate with activity-based billing';
                break;
            default:
                description = 'Based on machinery master configuration';
        }
        
        $('#rate-description').text(description);
        
        // Update rate type badge color
        $('#machinery-rate-type').removeClass('bg-primary bg-success bg-warning bg-info');
        switch(rateType) {
            case 'hourly': $('#machinery-rate-type').addClass('bg-primary'); break;
            case 'daily': $('#machinery-rate-type').addClass('bg-success'); break;
            case 'monthly': $('#machinery-rate-type').addClass('bg-info'); break;
            default: $('#machinery-rate-type').addClass('bg-primary'); break;
        }
        
        // Load previous reading for validation
        loadPreviousReading(machineryId);
        
        // Update rate type explanation
        updateRateTypeExplanation(rateType, machinery);
    }

    function resetRateInfo() {
        $('#machinery-rate-type').text('Hourly');
        $('#standard-rate').text('₹0.00');
        $('#rate-description').text('Please select machinery');
        $('#rate-type-explanation').html('<small class="text-muted">Select machinery to see rate calculation logic</small>');
        $('#previousReadingInfo').hide();
    }

    function loadPreviousReading(machineryId) {
        const selectedDate = $('#date').val();
        if (!selectedDate) return;
        
        // AJAX call to get previous reading
        $.ajax({
            url: '{{ route("daily-progress-reports.get-previous-reading") }}',
            method: 'GET',
            data: {
                machinery_id: machineryId,
                date: selectedDate,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success && response.previous_reading) {
                    $('#previousReadingValue').text(response.previous_reading.end_reading || '0');
                    $('#previousReadingDate').text(response.previous_reading.date || 'Unknown');
                    $('#previousReadingInfo').show();
                    
                    // Store previous reading for validation
                    previousReading = parseFloat(response.previous_reading.end_reading) || 0;
                } else {
                    $('#previousReadingInfo').hide();
                    previousReading = 0;
                }
            },
            error: function() {
                $('#previousReadingInfo').hide();
                previousReading = 0;
            }
        });
    }

    function updateRateTypeExplanation(rateType, machinery) {
        let explanation = '';
        
        switch(rateType) {
            case 'hourly':
                explanation = `
                    <strong>Hourly Billing:</strong><br>
                    • Rate: ₹${machinery.rate}/hour<br>
                    • Calculation: Working Hours × Rate<br>
                    • No minimum billing requirement<br>
                    • Idle hours excluded from billing
                `;
                break;
            case 'daily':
                explanation = `
                    <strong>Daily Billing:</strong><br>
                    • Rate: ₹${machinery.rate}/day<br>
                    • Any usage = Full day charge<br>
                    • Minimum: ${machinery.minimum_billing_hours || 8} hours<br>
                    • Even 1 hour = Full day rate
                `;
                break;
            case 'monthly':
                explanation = `
                    <strong>Monthly Billing:</strong><br>
                    • Rate: ₹${machinery.rate}/month<br>
                    • Prorated: (Rate ÷ Days in Month) × Active Days<br>
                    • Calculated at month-end<br>
                    • Partial deployments supported
                `;
                break;
        }
        
        $('#rate-type-explanation').html(explanation);
    }

    // Fallback validateReadings function for compatibility
    function validateReadings() {
        // Use centralized jQuery validation if available
        if (typeof DPRValidationJQuery !== 'undefined' && DPRValidationJQuery.validateReadings) {
            return DPRValidationJQuery.validateReadings();
        }
        return true;
    }

    // Previous reading validation using centralized jQuery validation
    $(document).on('dpr:validated', function(e, data) {
        // Handle previous reading validation
        if (previousReading > 0 && data.readings.start < previousReading) {
            $('#machine_start_reading').addClass('is-invalid');
            $('#startReadingError').text(`Must be ≥ previous reading (${previousReading})`);
        }
        
        // Handle fraud detection for large reading jumps
        if (previousReading > 0) {
            const dailyProgress = data.readings.end - previousReading;
            const maxDailyProgress = 1000; // Configurable threshold
            
            if (dailyProgress > maxDailyProgress) {
                DPRValidationJQuery.showWarnings([`Large reading jump detected: ${dailyProgress.toFixed(2)} units. Please verify readings.`]);
            }
        }
        
        // Update validation badge
        const badge = $('#readingValidationBadge');
        badge.removeClass('success warning error');
        
        if (data.isValid) {
            badge.addClass('success').html('<i class="fas fa-check-circle text-success"></i>');
        } else {
            badge.addClass('error').html('<i class="fas fa-exclamation-circle text-danger"></i>');
        }
    });

    // Enhanced calculation preview with rate type logic
    function updateCalculationPreview() {
        const startReading = parseFloat($('#machine_start_reading').val()) || 0;
        const endReading = parseFloat($('#machine_end_reading').val()) || 0;
        const idleHours = parseFloat($('#machine_idle_reading').val()) || 0;
        
        if (!currentMachinery) {
            resetCalculationPreview();
            return;
        }
        
        const rateType = currentMachinery.rate_type || 'hourly';
        const rate = parseFloat(currentMachinery.rate) || 0;
        const minBillingHours = parseFloat(currentMachinery.minimum_billing_hours) || 0;
        
        // Calculate values
        const totalProgress = endReading - startReading;
        const workingHours = Math.max(0, totalProgress - idleHours);
        let billableHours = workingHours;
        let estimatedAmount = 0;
        
        // Apply rate type logic
        switch(rateType) {
            case 'hourly':
                billableHours = workingHours;
                estimatedAmount = billableHours * rate;
                $('#billable-hours-note').text('Direct hourly calculation');
                break;
                
            case 'daily':
                // Any usage = full day
                billableHours = workingHours > 0 ? (minBillingHours || 8) : 0;
                estimatedAmount = workingHours > 0 ? rate : 0;
                $('#billable-hours-note').text('Any usage = full day');
                break;
                
            case 'monthly':
                // Monthly handled at month-end, show 0 for now
                billableHours = 0;
                estimatedAmount = 0;
                $('#billable-hours-note').text('Calculated at month-end');
                break;
        }
        
        // Apply rate override if enabled
        if (isOverrideEnabled && overrideRate > 0) {
            if (rateType === 'hourly') {
                estimatedAmount = billableHours * overrideRate;
            } else if (rateType === 'daily' && billableHours > 0) {
                estimatedAmount = overrideRate;
            }
        }
        
        // Update display
        $('#preview-total-progress').text(totalProgress.toFixed(2));
        $('#preview-working-hours').text(workingHours.toFixed(2));
        $('#preview-billable-hours').text(billableHours.toFixed(2));
        $('#preview-amount').text('₹' + estimatedAmount.toFixed(2));
        
        // Update rate usage note
        if (isOverrideEnabled) {
            $('#rate-usage-note').text('Using override rate').addClass('text-warning');
        } else {
            $('#rate-usage-note').text('Using standard rate').removeClass('text-warning');
        }
        
        // Apply minimum billing preview
        let minimumBillingApplied = false;
        if (isRental && minimumBillingHours > 0) {
            const adjustedBillableHours = Math.max(billableHours, minimumBillingHours);
            minimumBillingApplied = adjustedBillableHours > workingHours;
        }
        
        if (minimumBillingApplied) {
            $('#minimum-billing-note').text('⚠️ Minimum billing applied: ' + minimumBillingHours + ' hours');
        } else {
            $('#minimum-billing-note').text('');
        }
    }

    function resetCalculationPreview() {
        $('#preview-total-progress').text('0.00');
        $('#preview-working-hours').text('0.00');
        $('#preview-billable-hours').text('0.00');
        $('#preview-amount').text('₹0.00');
        $('#billable-hours-note').text('Based on rate type');
    }

    // Function to toggle fuel consumption section based on ownership
    function toggleFuelConsumptionSection(isRental) {
        // Always show fuel consumption form for all machinery types
        $('#fuel-consumption-form').show();
        
        if (isRental) {
            // Show rental notice for rental machinery
            $('#rental-fuel-notice').removeClass('d-none');
        } else {
            // Hide rental notice for owned machinery
            $('#rental-fuel-notice').addClass('d-none');
        }
    }

    // When machinery is selected, auto-populate owned_by and site_id and load fuel stock
    $('#machinery_id').on('change', function() {
        const machineryId = $(this).val();
        if (machineryId && machineryList[machineryId]) {
            const machinery = machineryList[machineryId];
            $('#owned_by').val(machinery.owned_by);
            $('#site_id').val(machinery.site_id);
            $('#owned_by_new').val(machinery.owned_by);
            
            // Load and display machinery rate information
            loadMachineryRateInfo(machineryId);
            $('#site_id_new').val(machinery.site_id);
            
            // Set calculation variables for preview
            currentRate = parseFloat(machinery.rate) || 0;
            standardRate = currentRate; // Store for override display
            minimumBillingHours = parseFloat(machinery.minimum_billing_hours) || 0;
            isRental = machinery.owned_by === 'rental';
            
            // Toggle fuel consumption section based on ownership
            toggleFuelConsumptionSection(isRental);
            
            // Validate readings if already entered
            validateReadings();
            
            // Update standard rate display
            $('#standard-rate').text('₹' + standardRate.toFixed(2));
            
            // Update preview with new rate
            updateCalculationPreview();
            
            // Load fuel stock for the selected site (for all machinery types)
            if (machinery.site_id) {
                loadFuelStock(machinery.site_id);
            }
        } else {
            $('#owned_by').val('');
            $('#site_id').val('');
            $('#owned_by_new').val('');
            $('#site_id_new').val('');
            currentRate = 0;
            standardRate = 0;
            minimumBillingHours = 0;
            isRental = false;
            $('#standard-rate').text('₹0.00');
            
            // Hide fuel consumption section when no machinery selected
            toggleFuelConsumptionSection(false);
            $('#consumption-items-table tbody').empty();
            materials = {};
            
            updateCalculationPreview();
        }
    });
    
    // Bind calculation preview to input changes
    $('#machine_start_reading, #machine_end_reading, #machine_idle_reading').on('input', updateCalculationPreview);
    
    // Validate operator names count matches number of operators
    $('#number_of_operators, #operator_names').on('input', function() {
        const operatorCount = parseInt($('#number_of_operators').val()) || 0;
        const operatorNames = $('#operator_names').val().trim();
        const namesArray = operatorNames ? operatorNames.split(',').filter(name => name.trim()) : [];
        const namesCount = namesArray.length;
        
        // Clear previous validation
        $('#number_of_operators, #operator_names').removeClass('is-invalid');
        $('#operator-names-error').remove();
        
        // Validate count matches
        if (operatorCount > 0 && namesCount > 0 && namesCount !== operatorCount) {
            $('#number_of_operators').addClass('is-invalid');
            $('#operator_names').addClass('is-invalid');
            $('#operator_names').after('<div id="operator-names-error" class="invalid-feedback">Number of operator names (' + namesCount + ') must match number of operators (' + operatorCount + ')</div>');
        }
    });
    
    // Rate override functionality
    let standardRate = 0;
    let overrideRate = 0;
    let isOverrideEnabled = false;
    
    // Handle rate override checkbox
    $('#enable-rate-override').on('change', function() {
        isOverrideEnabled = $(this).is(':checked');
        
        if (isOverrideEnabled) {
            $('#rate-override-fields').show();
            $('#override-rate').val(standardRate);
            $('#override-rate').focus();
        } else {
            $('#rate-override-fields').hide();
            $('#override-rate').val('');
            $('#override-reason').val('');
            overrideRate = 0;
        }
        
        updateCalculationPreview();
    });
    
    // Handle override rate changes
    $('#override-rate, #override-reason').on('input', function() {
        overrideRate = parseFloat($('#override-rate').val()) || 0;
        updateCalculationPreview();
    });
    
    // Validate override fields
    function validateRateOverride() {
        if (!isOverrideEnabled) return true;
        
        const rate = parseFloat($('#override-rate').val()) || 0;
        const reason = $('#override-reason').val().trim();
        
        // Clear previous validation
        $('#override-rate, #override-reason').removeClass('is-invalid');
        $('.rate-override-error').remove();
        
        let isValid = true;
        
        if (rate <= 0) {
            $('#override-rate').addClass('is-invalid');
            $('#override-rate').after('<div class="invalid-feedback rate-override-error">Override rate must be greater than 0</div>');
            isValid = false;
        }
        
        if (!reason) {
            $('#override-reason').addClass('is-invalid');
            $('#override-reason').after('<div class="invalid-feedback rate-override-error">Override reason is required</div>');
            isValid = false;
        }
        
        return isValid;
    }

    function addItemRow(item = {}, materialsList = materials) {
        rowIndex++;
        
        // Create material options
        const materialOptions = Object.entries(materialsList).map(([id, material]) =>
            `<option value="${id}">${material.name}</option>`
        ).join('');

        // Get stock info from materials if available
        let stockVal = '0';
        let unitVal = 'unit';
        if (item.material_id && materialsList[item.material_id]) {
            stockVal = materialsList[item.material_id].total_qty || '0';
            unitVal = materialsList[item.material_id].unit || 'unit';
        }

        const row = $('<tr>');
        row.append(`<td>
            <select name="items[${rowIndex}][material_id]" class="form-control item-material" required>
                <option value="">Select Material</option>
                ${materialOptions}
            </select>
        </td>`);
        row.append(`<td>
            <div class="input-group">
                <input type="text" class="form-control item-stock" readonly value="${stockVal}"/>
                <span class="input-group-text item-stock-unit">${unitVal}</span>
            </div>
        </td>`);
        row.append(`<td>
            <div class="input-group">
                <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-quantity" min="1" value="0" required>
                <input type="hidden" name="items[${rowIndex}][unit]" class="item-unit" value="${unitVal}">
                <span class="input-group-text item-unit-label">${unitVal}</span>
            </div>
        </td>`);
        row.append(`<td><input type="text" name="items[${rowIndex}][remarks]" class="form-control"></td>`);
        row.append(`<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>`);

        $('#consumption-items-table tbody').append(row);
        rowIndex++;
    }

    // Add row button with validation
    $('#add-item-row').on('click', function () {
        // Prevent adding items for rental machinery
        if (isRental) {
            return;
        }
        
        const $lastRow = $('#consumption-items-table tbody tr').last();
        let canAdd = true;

        if ($lastRow.length) {
            const materialId = $lastRow.find('.item-material').val();
            const quantity = parseFloat($lastRow.find('.item-quantity').val()) || 0;

            $lastRow.find('.item-material, .item-quantity').removeClass('is-invalid');

            if (!materialId) {
                $lastRow.find('.item-material').addClass('is-invalid');
                canAdd = false;
            }
            if (quantity <= 0) {
                $lastRow.find('.item-quantity').addClass('is-invalid');
                canAdd = false;
            }
            if (materialId && materials[materialId]) {
                const available = materials[materialId].total_qty || 0;
                if (quantity > available) {
                    $lastRow.find('.item-quantity').addClass('is-invalid');
                    $lastRow.find('.item-quantity')[0].setCustomValidity(`Quantity exceeds available stock (${available})`);
                    canAdd = false;
                } else {
                    $lastRow.find('.item-quantity')[0].setCustomValidity('');
                }
            }
        }

        if (canAdd) {
            addItemRow();
        }
    });

    // Remove row
    $('#consumption-items-table').on('click', '.remove-item-row', function () {
        $(this).closest('tr').remove();
    });

    // Load stock by site
    function loadFuelStock(siteId) {
        if (!siteId) return;
        $.ajax({
            url: '{{ route("ajax.getStockBySiteForDailyConsumption") }}',
            method: 'GET',
            data: {site_id: siteId},
            success: function (response) {
                // Update materials object with new stock data
                materials = {};
                response.forEach(item => {
                    if (parseInt(item.category_id) === 2) { // Only Fuel
                        materials[item.material_id] = {
                            name: item.material_name,
                            unit: item.unit_name || 'unit',
                            price: item.material_price,
                            total_qty: parseFloat(item.total_qty) || 0,
                            category_id: item.category_id,
                            category_name: item.category_name,
                        };
                    }
                });

                // Update stock display for existing rows without clearing them
                $('#consumption-items-table tbody tr').each(function() {
                    const row = $(this);
                    const materialId = row.find('.item-material').val();
                    if (materialId && materials[materialId]) {
                        const material = materials[materialId];
                        row.find('.item-stock').val(material.total_qty);
                        row.find('.item-stock-unit').text(material.unit);
                        row.find('.item-unit').val(material.unit);
                        row.find('.item-unit-label').text(material.unit);
                    }
                });

                // Add a fresh row only if table is empty
                if ($('#consumption-items-table tbody tr').length === 0) {
                    addItemRow({}, materials);
                }
            },
            error: function (xhr) {
                console.error('Error fetching stock:', xhr.responseText);
                // Keep existing materials on error, don't wipe them
            }
        });
    }

    // Bind change events
    $('#site_id').on('change', function () {
        loadFuelStock($(this).val());
    });
    
    // When date changes, reload previous reading for validation
    $('#date').on('change', function() {
        if (currentMachinery) {
            loadPreviousReading(currentMachinery.id);
            validateReadings();
        }
    });

    // Quantity validation against stock
    $('#consumption-items-table').on('input', '.item-quantity', function () {
        const row = $(this).closest('tr');
        const qtyInput = row.find('.item-quantity');
        const materialId = row.find('.item-material').val();

        const available = materials[materialId]?.total_qty || 0;
        const enteredQty = parseFloat(qtyInput.val()) || 0;

        if (enteredQty > available) {
            qtyInput.addClass('is-invalid');
            qtyInput[0].setCustomValidity(`Quantity exceeds available stock (${available})`);
            qtyInput.val(available > 0 ? available : 1);
        } else {
            qtyInput.removeClass('is-invalid');
            qtyInput[0].setCustomValidity('');
        }
    });

    // Update stock/unit when material changes
    $('#consumption-items-table').on('change', '.item-material', function () {
        const materialId = $(this).val();
        const row = $(this).closest('tr');
        const unitInput = row.find('.item-unit');
        const unitLabel = row.find('.item-unit-label');
        const itemStock = row.find('.item-stock');
        const itemStockUnit = row.find('.item-stock-unit');

        if (materialId && materials[materialId]) {
            const material = materials[materialId];
            unitInput.val(material.unit);
            unitLabel.text(material.unit);
            itemStock.val(material.total_qty);
            itemStockUnit.text(material.unit);
        } else {
            unitInput.val('');
            unitLabel.text('unit');
            itemStock.val(0);
            itemStockUnit.text('unit');
        }
    });

    function cleanConsumptionRows() {
        const $itemsTable = $('#consumption-items-table tbody');
        $itemsTable.find('tr').each(function() {
            const row = $(this);
            const materialId = row.find('.item-material').val();
            const quantity = parseFloat(row.find('.item-quantity').val()) || 0;

            if (!materialId && quantity <= 0) {
                row.remove();
            }
        });
    }

    // Function to display validation errors
    function displayValidationErrors(errors) {
        // Clear previous error alerts
        $('.alert-danger').not('.duplicate-dpr-alert').remove();
        
        let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        errorHtml += '<h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Validation Errors Found</h5>';
        errorHtml += '<hr>';
        errorHtml += '<ul class="mb-0">';
        
        if (typeof errors === 'object') {
            Object.values(errors).flat().forEach(error => {
                errorHtml += `<li><strong>${error}</strong></li>`;
            });
        } else if (typeof errors === 'string') {
            errorHtml += `<li><strong>${errors}</strong></li>`;
        }
        
        errorHtml += '</ul>';
        errorHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        errorHtml += '</div>';
        
        $('.modal-body').prepend(errorHtml);
        $('.modal-body').scrollTop(0);
    }

    // Form submission validation - AJAX-based to prevent modal close on errors
    $('form.needs-validation').on('submit', function(e) {
        e.preventDefault();
        
        const machineryId = $('#machinery_id').val();
        const date = $('#date').val();
        
        if (!machineryId || !date) {
            return true; // Let server-side validation handle missing fields
        }
        
        cleanConsumptionRows();

        const $itemsTable = $('#consumption-items-table tbody');
        let invalidRowFound = false;

        $itemsTable.find('tr').each(function() {
            const row = $(this);
            const materialId = row.find('.item-material').val();
            const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
            const hasItemData = materialId || quantity > 0;

            if (!hasItemData) {
                row.remove();
                return;
            }

            if (!materialId || quantity <= 0) {
                invalidRowFound = true;
                row.find('.item-material, .item-quantity').addClass('is-invalid');
            }
        });

        if (invalidRowFound) {
            if (!$('.fuel-consumption-error').length) {
                const alertHtml = `
                    <div class="alert alert-danger fuel-consumption-error" role="alert">
                        <strong>Error:</strong> Please correct or remove invalid fuel consumption rows before saving.
                    </div>
                `;
                $('.modal-body').prepend(alertHtml);
            }
            $('.modal-body').scrollTop(0);
            return false;
        }

        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Checking for duplicates...');
        
        // Check for duplicates via AJAX
        $.ajax({
            url: '{{ route("daily-progress-reports.check-duplicate") }}',
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: {
                machinery_id: machineryId,
                date: date,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.exists) {
                    // Show duplicate warning
                    const machineryName = $('#machinery_id option:selected').text();
                    const message = `Duplicate daily reading detected. A DPR for machinery "${machineryName}" on ${date} already exists (DPR #${response.dpr_id}). Please use the existing record or choose a different date.`;
                    
                    // Show error message
                    if (!$('.duplicate-dpr-alert').length) {
                        const alertHtml = `
                            <div class="alert alert-danger duplicate-dpr-alert" role="alert">
                                <strong>Error:</strong> ${message}
                            </div>
                        `;
                        $('.modal-body').prepend(alertHtml);
                    }
                    
                    // Scroll to top to show error
                    $('.modal-body').scrollTop(0);
                } else {
                    // No duplicate, submit via AJAX to keep modal open on errors
                    submitDPRForm();
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).text(originalText);
                
                let errorMessage = 'Error checking for duplicates';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    if (xhr.responseText) {
                        errorMessage = xhr.responseText;
                    }
                }
                
                console.error('Error checking for duplicates:', errorMessage);
                
                const alertHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Error</h5>
                        <hr>
                        <p><strong>${errorMessage}</strong></p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('.modal-body').prepend(alertHtml);
                $('.modal-body').scrollTop(0);
            }
        });
    });

    // Submit DPR form via AJAX to keep modal open on validation errors
    function submitDPRForm() {
        // Use the specific form ID
        const form = document.getElementById('dpr-form');
        if (!form) {
            console.error('Form not found');
            return;
        }

        const formData = new FormData(form);

        // Remove _method field if present (browser extensions may inject it)
        if (formData.has('_method')) {
            formData.delete('_method');
        }

        // Debug: Log what's being sent
        console.log('Form data entries:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ':', value);
        }

        // Find submit button within this form
        const submitBtn = $(form).find('button[type="submit"]');
        const originalText = submitBtn.text();

        submitBtn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '{{ route("daily-progress-reports.store") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success !== false) {
                    // Success - close modal and reload page
                    const modalEl = document.getElementById('dpCreateModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    
                    // Show success message
                    if (response.message) {
                        showToast('success', response.message);
                    } else {
                        showToast('success', 'Daily Progress Report saved successfully');
                    }
                    
                    // Reload page to show updated data
                    setTimeout(() => location.reload(), 1000);
                } else {
                    // Server returned success=false (validation error occurred)
                    displayValidationErrors(response.message || 'Validation failed');
                    $('.modal-body').scrollTop(0);
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).text(originalText);
                
                // Parse and display error response
                let response = null;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (e) {}
                
                if (xhr.status === 422 && response && response.errors) {
                    displayValidationErrors(response.errors);
                } else if (response && response.message) {
                    displayValidationErrors(response.message);
                } else if (response && response.error) {
                    displayValidationErrors(response.error);
                } else {
                    displayValidationErrors('An error occurred while saving. Please try again.');
                }
                
                $('.modal-body').scrollTop(0);
            }
        });
    }

    // Display validation errors in the modal
    function displayValidationErrors(errors) {
        // Clear previous error alerts (except duplicate alerts)
        $('.alert-danger').not('.duplicate-dpr-alert').remove();
        
        let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        errorHtml += '<h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Validation Errors Found</h5>';
        errorHtml += '<hr>';
        errorHtml += '<ul class="mb-0">';
        
        if (typeof errors === 'object') {
            Object.values(errors).flat().forEach(error => {
                errorHtml += `<li><strong>${error}</strong></li>`;
            });
        } else if (typeof errors === 'string') {
            errorHtml += `<li><strong>${errors}</strong></li>`;
        }
        
        errorHtml += '</ul>';
        errorHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        errorHtml += '</div>';
        
        $('.modal-body').prepend(errorHtml);
    }

    // Show toast notification
    function showToast(type, message) {
        const toastHtml = `
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
                <div class="toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>
        `;
        $('body').append(toastHtml);
        const toastEl = new bootstrap.Toast($('.toast').last()[0]);
        toastEl.show();
        setTimeout(() => toastEl.hide(), 3000);
    }
});
</script>
