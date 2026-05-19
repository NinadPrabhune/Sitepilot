{{ Form::model($report, [
    'route' => ['daily-progress-reports.update', $report->id],
    'method' => 'PUT',
    'class' => 'needs-validation',
    'novalidate' => 'novalidate',
    'files' => true,
    'id' => 'dpr-form'
 ]) }}

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
            {{ Form::text('machinery_name', $machinery->name ?? '', ['class' => 'form-control', 'readonly' => true]) }}
            {{ Form::hidden('machinery_id', $report->machinery_id) }}
        </div>

        {{-- Owned By --}}
        <div class="form-group col-md-2">
            {{ Form::label('owned_by_new', __('Owned By'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('owned_by_new', [
                '' => __('Select Owned By'),
                'owned' => 'Owned',
                'rental' => 'Rental'
            ], $machinery->owned_by ?? null, ['class' => 'form-control', 'id' => 'owned_by_new', 'disabled'=>'disabled']) }}
            {{ Form::hidden('owned_by', $machinery->owned_by ?? '') }}
        </div>

        {{-- Current Site --}}
        <div class="form-group col-md-3">
            {{ Form::label('site_id_new', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id_new', ['' => __('Select Site')] + $sites->toArray(), $report->site_id, ['class' => 'form-control', 'id' => 'site_id_new', 'disabled'=>'disabled']) }}
            {{ Form::hidden('site_id', $report->site_id) }}
        </div>

        {{-- Date --}}
        <div class="form-group col-md-2">
            {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('date', $report->date, ['class' => 'form-control', 'required' => 'required']) }}
        </div>

        {{-- Reference File --}}
        <div class="form-group col-md-2">
            {{ Form::label('consumption_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('consumption_file', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx']) }}
            @if($report->file_path)
                <small><a href="{{ asset('storage/'.$report->file_path) }}" target="_blank">{{ __('View File') }}</a></small>
            @endif
        </div>
    </div>

    <hr>

    {{-- Single Row for Machinery Details, Operators, Rate & Billing --}}
    <div class="row">

       {{-- Machinery Details / Readings / Operators --}}
<div class="col-md-6">
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

    <div class="row">
        {{-- Start Reading --}}
        <div class="form-group col-md-4 mb-2">
            {{ Form::label('machine_start_reading', __('Machine Start Reading'), ['class' => 'form-label']) }}
            {{ Form::number('machine_start_reading', $report->machine_start_reading, ['class' => 'form-control', 'min' => 0, 'step' => '0.01','placeholder' => 'e.g. 100.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="startReadingError"></div>
        </div>

        {{-- End Reading --}}
        <div class="form-group col-md-4 mb-2">
            {{ Form::label('machine_end_reading', __('Closing Reading'), ['class' => 'form-label']) }}
            {{ Form::number('machine_end_reading', $report->machine_end_reading, ['class' => 'form-control', 'min' => 0, 'step' => '0.01','placeholder' => 'e.g. 100.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="endReadingError"></div>
        </div>

        {{-- Idle Hours --}}
        <div class="form-group col-md-4 mb-2">
            {{ Form::label('machine_idle_reading', __('Idle Hours'), ['class' => 'form-label']) }}
            {{ Form::number('machine_idle_reading', $report->machine_idle_reading ?? 0, ['class' => 'form-control', 'min' => 0, 'step' => '0.01','placeholder' => 'e.g. 1.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="idleHoursError"></div>
        </div>
    </div>

    <div class="row">
        {{-- Number of Operators --}}
        <div class="form-group col-md-4 mb-2">
            {{ Form::label('number_of_operators', __('No. of Operators'), ['class' => 'form-label']) }}
            {{ Form::number('number_of_operators', $report->number_of_operators, ['class' => 'form-control', 'min' => 0, 'id' => 'number_of_operators']) }}
        </div>

        {{-- Operator Names --}}
        <div class="form-group col-md-8 mb-2">
            {{ Form::label('operator_names', __('Operator Names'), ['class' => 'form-label']) }}
            {{ Form::text('operator_names', $report->operator_names, ['class' => 'form-control', 'placeholder' => 'e.g. John Doe, Jane Smith']) }}
            <small class="text-muted">{{ __('Enter operator names separated by commas') }}</small>
        </div>
        {{-- Work Details --}}
        <div class="form-group col-md-6 ">
            {{ Form::label('work_details', __('Work Details'), ['class' => 'form-label']) }}
            {{ Form::textarea('work_details', $report->work_details, ['class' => 'form-control', 'rows' => 2]) }}
        </div>

         {{-- Maintenance Notes --}}
        <div class="form-group col-md-6">
            {{ Form::label('maintenance_notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('maintenance_notes', $report->maintenance_notes, ['class' => 'form-control', 'rows' => 2]) }}
        </div>
    </div>
</div>

        {{-- Rate Configuration --}}
        <div class="col-md-2">
            <div class="rate-override-section bg-light p-3 h-100">
                <h6 class="text-muted"><i class="ti ti-calculator"></i> {{ __('Rate Configuration') }}</h6>
                <hr>
                <div class="mb-2">
                    <strong>{{ __('Rate Type') }}:</strong>
                    <span class="badge bg-primary" id="machinery-rate-type">{{ ucfirst($machinery->rate_type ?? 'hourly') }}</span>
                </div>

                <label>{{ __('Standard Rate (Auto):') }}</label>
                <div class="fw-bold" id="standard-rate">₹{{ number_format($machinery->rate ?? 0, 2) }}</div>
                <small class="text-muted" id="rate-description">{{ __('Based on machinery master and rate history') }}</small>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="enable-rate-override">
                    <label class="form-check-label" for="enable-rate-override">
                        {{ __('Override Rate') }} <small class="text-muted">(Admin/Accounts only)</small>
                    </label>
                </div>

                <div id="rate-override-fields" class="mt-3" style="display: none;">
                    <label>{{ __('Override Rate') }}</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text">₹</span>
                        <input type="number" id="override-rate" class="form-control" step="0.01">
                    </div>

                    <label>{{ __('Override Reason') }}</label>
                    <textarea id="override-reason" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>

        {{-- Billing Preview --}}
        <div class="col-md-2">
            <div class="calculation-preview-panel bg-light p-3 h-100">
                <h6 class="text-muted"><i class="ti ti-calculator"></i> {{ __('Billing Preview') }}</h6>
                <hr>
                <div class="mb-2">
                    <label>{{ __('Total Progress') }}</label>
                    <div class="fw-bold" id="preview-total-progress">0.00</div>
                </div>

                <div class="mb-2">
                    <label>{{ __('Working Hours') }}</label>
                    <div class="fw-bold" id="preview-working-hours">0.00</div>
                </div>

                <div class="mb-2">
                    <label>{{ __('Billable Hours') }}</label>
                    <div class="fw-bold" id="preview-billable-hours">0.00</div>
                </div>

                <div class="mb-2">
                    <label>{{ __('Estimated Amount') }}</label>
                    <div class="fw-bold text-primary" id="preview-amount">₹0.00</div>
                </div>
            </div>
        </div>

        {{-- Validation / Rate Logic --}}
        <div class="col-md-2">
            <div class="bg-light p-3 h-100">
                <h6 class="text-muted"><i class="ti ti-info-circle"></i> {{ __('Rate Logic & Validation') }}</h6>
                <hr>
                <div class="alert alert-info">
                    <small id="rate-type-explanation">
                        @php
                            $rateType = $machinery->rate_type ?? 'hourly';
                            $rate = $machinery->rate ?? 0;
                            $minBillingHours = $machinery->minimum_billing_hours ?? 8;

                            switch($rateType) {
                                case 'hourly':
                                    echo "<strong>Hourly Billing:</strong><br>
                                        • Rate: ₹" . number_format($rate, 2) . "/hour<br>
                                        • Calculation: Working Hours × Rate<br>
                                        • No minimum billing requirement<br>
                                        • Idle hours excluded from billing";
                                    break;
                                case 'daily':
                                    echo "<strong>Daily Billing:</strong><br>
                                        • Rate: ₹" . number_format($rate, 2) . "/day<br>
                                        • Any usage = Full day charge<br>
                                        • Minimum: " . $minBillingHours . " hours<br>
                                        • Even 1 hour = Full day rate";
                                    break;
                                case 'monthly':
                                    echo "<strong>Monthly Billing:</strong><br>
                                        • Rate: ₹" . number_format($rate, 2) . "/month<br>
                                        • Prorated: (Rate ÷ Days in Month) × Active Days<br>
                                        • Calculated at month-end<br>
                                        • Partial deployments supported";
                                    break;
                                default:
                                    echo "<strong>Hourly Billing:</strong><br>
                                        • Rate: ₹" . number_format($rate, 2) . "/hour<br>
                                        • Calculation: Working Hours × Rate<br>
                                        • No minimum billing requirement";
                            }
                        @endphp
                    </small>
                </div>

                <div class="alert alert-warning" id="validation-warnings" style="display:none;">
                    <div id="validation-messages"></div>
                </div>

                <small class="text-muted" id="rate-usage-note"></small>
            </div>
        </div>
    </div>

    <hr>

    {{-- Fuel Consumption Section --}}
<div id="fuel-consumption-section">
    <h6 class="mb-3">{{ __('Fuel Consumption Details') }}</h6>

    <div class="row mb-2">
        {{-- Rental Fuel Notice --}}
        <div class="col-md-8">
            <div class="alert alert-info d-none mb-0" id="rental-fuel-notice">
                <i class="fas fa-info-circle"></i>
                <strong> Rental Machinery:</strong> Diesel costs will be recovered from supplier as per rental agreement.
            </div>
        </div>

        {{-- Add Item Button --}}
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-sm btn-primary" id="add-item-row">{{ __('Add Item') }}</button>
        </div>
    </div>

    {{-- Fuel Consumption Table --}}
    <div class="form-group col-md-12" id="fuel-consumption-form">
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
            <tbody>
                @if(!$report->items->isEmpty() || !empty($consumptionItems))
                    @foreach(!empty($consumptionItems) ? $consumptionItems : $report->items as $index => $item)
                        <tr>
                            <td>
                                <select name="items[{{ $index }}][material_id]" class="form-control item-material" required>
                                    <option value="">{{ __('Select Material') }}</option>
                                    @foreach($materials as $id => $material)
                                        <option value="{{ $id }}" {{ $item->material_id == $id ? 'selected' : '' }}>
                                            {{ $material['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input type="text" class="form-control item-stock" readonly value="{{ $materials[$item->material_id]['total_qty'] ?? 0 }}">
                                    <span class="input-group-text item-stock-unit">{{ $materials[$item->material_id]['unit'] ?? 'unit' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input type="number" name="items[{{ $index }}][quantity]" class="form-control item-quantity" min="1" value="{{ $item->quantity }}" required>
                                    <input type="hidden" name="items[{{ $index }}][unit]" class="item-unit" value="{{ $item->unit_name ?? $item->unit ?? 'unit' }}">
                                    <span class="input-group-text item-unit-label">{{ $item->unit_name ?? $item->unit ?? 'unit' }}</span>
                                </div>
                            </td>
                            <td><input type="text" name="items[{{ $index }}][remarks]" class="form-control" value="{{ $item->remarks ?? '' }}"></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>
                        </tr>
                    @endforeach
                @else
                    {{-- Empty row will be added by JavaScript --}}
                @endif
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
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
.form-group {
  margin-bottom: 0!important;
}
</style>

@php
    // Alternative method: Get consumption details directly from consumptionMaster
    $consumptionItems = [];
    if ($report->consumptionMaster && $report->consumptionMaster->details) {
        $consumptionItems = $report->consumptionMaster->details->load('material.unit');
    }

    // Ensure items are properly serialized for JavaScript
    $itemsForJs = [];
    // Use the alternative method if available, otherwise fall back to original
    $itemsToUse = !empty($consumptionItems) ? $consumptionItems : $report->items;

    if (isset($itemsToUse) && !empty($itemsToUse)) {
        foreach ($itemsToUse as $item) {
            $itemsForJs[] = [
                'material_id' => $item->material_id,
                'quantity' => $item->quantity,
                'unit' => $item->unit_name ?? $item->unit ?? 'unit',
                'remarks' => $item->remarks ?? ''
            ];
        }
    }

    @endphp

{{-- JavaScript for Edit Form --}}
<script src="{{ asset('js/dpr-validation-simple.js') }}"></script>

<script>
$(document).ready(function () {
    // Use raw JSON encoding to avoid Blade directive issues
    let materials = {!! $materials ? json_encode($materials) : '[]' !!};
    const consumptionMasterId = {{ $consumptionMasterId ?? ($report->consumptionMaster?->id ?? 'null') }};
    // For edit, create machineryList from single machinery object - use toArray() to get all attributes
    let machineryList = {!! isset($machinery) ? json_encode([$machinery->id => $machinery->toArray()]) : '{}' !!};

    let rowIndex = {{ $report->items->count() > 0 ? $report->items->count() : 0 }};
    let currentRate = {!! $machinery->rate ?? 0 !!};
    let minimumBillingHours = {!! $machinery->minimum_billing_hours ?? 0 !!};
    let isRental = {{ $isRental ? 'true' : 'false' }};
    // Use toArray() to properly serialize the Eloquent model
    let currentMachinery = {!! $machinery ? $machinery->toJson() : 'null' !!};
    let previousReading = 0;

    console.log('Current Machinery:', currentMachinery);
    console.log('Machinery List:', machineryList);

    // Set initial values for the edit form
    $('#owned_by_new').val('{!! $machinery->owned_by ?? '' !!}');
    $('#site_id_new').val('{!! $report->site_id !!}');

    // Initial calculation preview - call after a small delay to ensure DOM is ready
    setTimeout(function() {
        updateCalculationPreview();
    }, 100);

    // Load initial fuel stock if site_id is already selected
    const initialSiteId = $('#site_id').val() || '{!! $report->site_id !!}';
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
        const startReading = parseFloat($('#machine_start_reading').val()) || {!! $report->machine_start_reading ?? 0 !!};
        const endReading = parseFloat($('#machine_end_reading').val()) || {!! $report->machine_end_reading ?? 0 !!};
        const idleHours = parseFloat($('#machine_idle_reading').val()) || {!! $report->machine_idle_reading ?? 0 !!};

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

    // Toggle fuel consumption section based on rental status
    toggleFuelConsumptionSection(isRental);

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
    let standardRate = {!! $machinery->rate ?? 0 !!};
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
        refreshRemainingStockDisplay();
    });

    function getMaterialStock(materialId) {
        if (!materialId) return null;
        return materials[String(materialId)] ?? materials[materialId] ?? null;
    }

    function getAvailableQtyForRow(row, materialId) {
        const base = getMaterialStock(materialId)?.total_qty || 0;
        const rowEl = row[0] || row;
        let otherQty = 0;
        $('#consumption-items-table tbody tr').each(function () {
            if (this === rowEl) return;
            if ($(this).find('.item-material').val() == materialId) {
                otherQty += parseFloat($(this).find('.item-quantity').val()) || 0;
            }
        });
        return Math.max(0, base - otherQty);
    }

    function refreshRemainingStockDisplay() {
        const usedByMaterial = {};
        $('#consumption-items-table tbody tr').each(function () {
            const materialId = $(this).find('.item-material').val();
            if (!materialId) return;
            usedByMaterial[materialId] = (usedByMaterial[materialId] || 0) + (parseFloat($(this).find('.item-quantity').val()) || 0);
        });
        $('#consumption-items-table tbody tr').each(function () {
            const row = $(this);
            const materialId = row.find('.item-material').val();
            const base = getMaterialStock(materialId)?.total_qty || 0;
            const remaining = materialId ? Math.max(0, base - (usedByMaterial[materialId] || 0)) : 0;
            row.find('.item-stock').val(remaining);
        });
    }

    function loadFuelStock(siteId) {
        if (!siteId) return;
        $.ajax({
            url: '{{ route("ajax.getStockBySiteForDailyConsumptionEdit") }}',
            method: 'GET',
            data: {
                site_id: siteId,
                daily_consumption_masters_id: consumptionMasterId
            },
            success: function (response) {
                const items = Array.isArray(response) ? response : (response.data || []);
                materials = {};
                items.forEach(item => {
                    if (parseInt(item.category_id) === 2) {
                        const mid = String(item.material_id);
                        materials[mid] = {
                            name: item.material_name,
                            unit: item.unit_name || 'unit',
                            price: item.material_price,
                            total_qty: parseFloat(item.total_qty) || 0,
                            category_id: item.category_id,
                            category_name: item.category_name,
                        };
                    }
                });

                $('#consumption-items-table tbody tr').each(function() {
                    const row = $(this);
                    const materialId = row.find('.item-material').val();
                    if (materialId && getMaterialStock(materialId)) {
                        const material = getMaterialStock(materialId);
                        row.find('.item-stock-unit').text(material.unit);
                        row.find('.item-unit').val(material.unit);
                        row.find('.item-unit-label').text(material.unit);
                    }
                });
                refreshRemainingStockDisplay();

                if ($('#consumption-items-table tbody tr').length === 0) {
                    addItemRow({}, materials);
                }
            },
            error: function (xhr) {
                console.error('Error fetching stock:', xhr.responseText);
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

    // Quantity validation against stock (remaining after other rows)
    $('#consumption-items-table').on('input', '.item-quantity', function () {
        const row = $(this).closest('tr');
        const qtyInput = row.find('.item-quantity');
        const materialId = row.find('.item-material').val();
        const available = getAvailableQtyForRow(row, materialId);
        const enteredQty = parseFloat(qtyInput.val()) || 0;

        if (enteredQty > available) {
            qtyInput.addClass('is-invalid');
            qtyInput[0].setCustomValidity(`Quantity exceeds available stock (${available})`);
            qtyInput.val(available > 0 ? available : 0);
        } else {
            qtyInput.removeClass('is-invalid');
            qtyInput[0].setCustomValidity('');
        }
        refreshRemainingStockDisplay();
    });

    // Update stock/unit when material changes
    $('#consumption-items-table').on('change', '.item-material', function () {
        const materialId = $(this).val();
        const row = $(this).closest('tr');
        const unitInput = row.find('.item-unit');
        const unitLabel = row.find('.item-unit-label');
        const itemStockUnit = row.find('.item-stock-unit');

        if (materialId && getMaterialStock(materialId)) {
            const material = getMaterialStock(materialId);
            unitInput.val(material.unit);
            unitLabel.text(material.unit);
            itemStockUnit.text(material.unit);
            row.find('.item-quantity').val(row.find('.item-quantity').val() || 0);
        } else {
            unitInput.val('');
            unitLabel.text('unit');
            row.find('.item-stock').val(0);
            itemStockUnit.text('unit');
        }
        refreshRemainingStockDisplay();
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

    // Add a default row if tbody is empty
    if ($('#consumption-items-table tbody tr').length === 0) {
        addItemRow({}, materials);
    }

    refreshRemainingStockDisplay();
    if ($('#site_id').val()) {
        loadFuelStock($('#site_id').val());
    }

    // Initial calculation
    updateCalculationPreview();
});
</script>