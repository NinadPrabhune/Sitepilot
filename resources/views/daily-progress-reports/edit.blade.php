{{ Form::model($report, [
    'route' => ['daily-progress-reports.update', $report->id],
    'method' => 'PUT',
    'class' => 'needs-validation',
    'novalidate' => 'novalidate',
    'files' => true
]) }}

{{-- Display validation errors --}}
@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="modal-body">
    <div class="row">
        {{-- Machinery Name --}}
        <div class="form-group col-md-3">
            {{ Form::label('machinery_name', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('machinery_name', $machinery->name ?? '', ['class' => 'form-control', 'readonly' => true]) }}
            {{ Form::hidden('machinery_id', $machinery->id) }}
        </div>

        {{-- Owned By --}}
        <div class="form-group col-md-3">
            {{ Form::label('owned_by', __('Owned By'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('owned_by', ['owned'=>'Owned','rental'=>'Rental'], $machinery->owned_by, ['class'=>'form-control','disabled'=>true]) }}
            {{ Form::hidden('owned_by', $machinery->owned_by) }}
        </div>

        {{-- Current Site --}}
        <div class="form-group col-md-3">
            {{ Form::label('site_id', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, $report->site_id, ['class'=>'form-control','disabled'=>true]) }}
            {{ Form::hidden('site_id', $report->site_id) }}
        </div>

        {{-- Date --}}
        <div class="form-group col-md-3">
            {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('date', $report->date, ['class'=>'form-control','required'=>'required']) }}
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

        {{-- Machine Readings --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_start_reading', __('Machine Start Reading'), ['class'=>'form-label']) }}
            {{ Form::number('machine_start_reading', $report->machine_start_reading, ['class'=>'form-control','min'=>0,'step'=>'0.01','id'=>'machine_start_reading']) }}
            <div class="invalid-feedback" id="startReadingError"></div>
        </div>
        <div class="form-group col-md-4">
            {{ Form::label('machine_end_reading', __('Machine End Reading'), ['class'=>'form-label']) }}
            {{ Form::number('machine_end_reading', $report->machine_end_reading, ['class'=>'form-control','min'=>0,'step'=>'0.01','id'=>'machine_end_reading']) }}
            <div class="invalid-feedback" id="endReadingError"></div>
        </div>
        <div class="form-group col-md-4">
            {{ Form::label('machine_idle_reading', __('Idle Hours'), ['class'=>'form-label']) }}
            {{ Form::number('machine_idle_reading', $report->machine_idle_reading, ['class'=>'form-control','min'=>0,'step'=>'0.01','id'=>'machine_idle_reading']) }}
            <div class="invalid-feedback" id="idleHoursError"></div>
        </div>

        {{-- Operators --}}
        <div class="form-group col-md-4">
            {{ Form::label('number_of_operators', __('No. of Operators'), ['class'=>'form-label']) }}
            {{ Form::number('number_of_operators', $report->number_of_operators, ['class'=>'form-control','min'=>0,'id'=>'number_of_operators']) }}
        </div>
        <div class="form-group col-md-8">
            {{ Form::label('operator_names', __('Operator Names'), ['class'=>'form-label']) }}
            {{ Form::text('operator_names', $report->operator_names, ['class'=>'form-control','placeholder'=>'e.g. John Doe, Jane Smith']) }}
            <small class="text-muted">{{ __('Enter operator names separated by commas') }}</small>
        </div>

        {{-- Work and Maintenance --}}
        <div class="form-group col-md-6">
            {{ Form::label('work_details', __('Work Details'), ['class'=>'form-label']) }}
            {{ Form::textarea('work_details', $report->work_details, ['class'=>'form-control','rows'=>3]) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('maintenance_notes', __('Maintenance Notes'), ['class'=>'form-label']) }}
            {{ Form::textarea('maintenance_notes', $report->maintenance_notes, ['class'=>'form-control','rows'=>3]) }}
        </div>

        {{-- Hidden diesel/advance fields --}}
        <div class="form-group col-md-3 d-none">
            {{ Form::label('diesel_consumption', __('Diesel Consumption (L)'), ['class'=>'form-label']) }}
            {{ Form::hidden('diesel_consumption', $report->diesel_consumption, ['class'=>'form-control','step'=>'0.01','min'=>0]) }}
        </div>
        <div class="form-group col-md-3 d-none">
            {{ Form::label('machinery_advances', __('Machinery Advances'), ['class'=>'form-label']) }}
            {{ Form::number('machinery_advances', $report->machinery_advances, ['class'=>'form-control','step'=>'1.00','min'=>0]) }}
        </div>

        <hr>
        
        {{-- Rate Override Section --}}
        <div class="rate-override-section bg-light p-3 mb-3">
            <h6 class="text-muted"><i class="ti ti-calculator"></i> {{ __('Rate Configuration') }}</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2">
                        <strong>{{ __('Rate Type') }}:</strong> 
                        <span class="badge bg-primary" id="machinery-rate-type">{{ ucfirst($machinery->rate_type ?? 'hourly') }}</span>
                    </div>
                    <label>{{ __('Standard Rate (Auto):') }}</label>
                    <div class="fw-bold" id="standard-rate">₹{{ number_format($machinery->rate ?? 0, 2) }}</div>
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

        {{-- Fuel Consumption Section --}}
        <div id="fuel-consumption-section" class="col-12">
            <h6 class="mb-3">{{ __('Fuel Consumption Details') }}</h6>
            <div class="alert alert-info d-none" id="rental-fuel-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Fuel Consumption Not Required:</strong> Rental machinery—supplier bears fuel costs.
            </div>
            <div class="form-group" id="fuel-consumption-form">
                <button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">{{ __('Add Item') }}</button>
                <table class="table table-bordered mt-2" id="consumption-items-table">
                    <thead>
                        <tr>
                            <th>{{ __('Material') }}</th>
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
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <em>No fuel consumption items found. Click "Add Item" to add fuel consumption details.</em>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Reference File --}}
        <div class="form-group col-md-3">
            {{ Form::label('consumption_file', __('Reference File'), ['class'=>'form-label']) }}
            {{ Form::file('consumption_file', ['class'=>'form-control']) }}
            @if($report->file_path)
                <small><a href="{{ asset('storage/'.$report->file_path) }}" target="_blank">{{ __('View File') }}</a></small>
            @endif
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
</style>

@php
    // Alternative method: Get consumption details directly from consumptionMaster
    $consumptionItems = [];
    if ($report->consumptionMaster && $report->consumptionMaster->details) {
        $consumptionItems = $report->consumptionMaster->details->load('material.unit');
    }
    
    // Debug: Check if items exist and log detailed information
    \Log::info('DPR Edit - Items Debug:', [
        'dpr_id' => $report->id,
        'is_rental' => $isRental,
        'items_count' => $report->items->count(),
        'consumption_master_exists' => !!$report->consumptionMaster,
        'consumption_master_id' => $report->consumptionMaster?->id,
        'items_data' => $report->items->toArray(),
        'consumption_master_details' => $report->consumptionMaster?->details?->toArray(),
        'alternative_consumption_items_count' => count($consumptionItems),
        'alternative_consumption_items' => is_object($consumptionItems) ? $consumptionItems->toArray() : $consumptionItems
    ]);

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
    
    // Debug: Log data being passed to JavaScript
    \Log::info('DPR Edit Data:', [
        'machinery' => $machinery,
        'report' => $report,
        'materials' => $materials,
        'items_count' => count($itemsForJs),
        'is_rental' => $isRental
    ]);
@endphp
<div id="dpr-data" style="display:none"
     data-materials='{!! json_encode($materials) !!}'
     data-machinery='{!! json_encode($machinery) !!}'
     data-is-rental='{!! json_encode($isRental) !!}'
     data-report-items='{!! json_encode($itemsForJs) !!}'
     data-report='{!! json_encode([
         'machine_start_reading' => $report->machine_start_reading ?? 0,
         'machine_end_reading' => $report->machine_end_reading ?? 0,
         'machine_idle_reading' => $report->machine_idle_reading ?? 0,
         'date' => $report->date,
         'billable_hours' => $report->billable_hours ?? 0,
         'calculated_amount' => $report->calculated_amount ?? 0
     ]) !!}'>
</div>

{{-- JS Scripts --}}
<script>
// Simple calculation preview - GUARANTEED TO WORK
(function() {
    // Wait for page to load
    function waitForElements() {
        const startEl = document.getElementById('machine_start_reading');
        const endEl = document.getElementById('machine_end_reading');
        const idleEl = document.getElementById('machine_idle_reading');
        const totalEl = document.getElementById('preview-total-progress');
        const workEl = document.getElementById('preview-working-hours');
        const billEl = document.getElementById('preview-billable-hours');
        const amountEl = document.getElementById('preview-amount');
        
        if (startEl && endEl && idleEl && totalEl && workEl && billEl && amountEl) {
            startCalculation();
        } else {
            setTimeout(waitForElements, 100);
        }
    }
    
    function startCalculation() {
        // Get values directly from Laravel data
        const startValue = {!! $report->machine_start_reading ?? 0 !!} || 0;
        const endValue = {!! $report->machine_end_reading ?? 0 !!} || 0;
        const idleValue = {!! $report->machine_idle_reading ?? 0 !!} || 0;
        const rate = {!! $machinery->rate ?? 6000 !!} || 6000;
        const rateType = '{!! $machinery->rate_type ?? 'daily' !!}' || 'daily';
        const minHours = {!! $machinery->minimum_billing_hours ?? 8 !!} || 8;
        const existingAmount = {!! $report->calculated_amount ?? 0 !!} || 0;
        const existingBillable = {!! $report->billable_hours ?? 0 !!} || 0;
        
        function calculate() {
            const start = parseFloat(document.getElementById('machine_start_reading').value) || startValue;
            const end = parseFloat(document.getElementById('machine_end_reading').value) || endValue;
            const idle = parseFloat(document.getElementById('machine_idle_reading').value) || idleValue;
            
            const totalProgress = end - start;
            const workingHours = Math.max(0, totalProgress - idle);
            
            let billableHours = workingHours;
            let amount = existingAmount;
            
            // Use existing values if available
            if (existingBillable > 0) {
                billableHours = existingBillable;
            } else if (rateType === 'daily') {
                billableHours = workingHours > 0 ? minHours : 0;
                amount = workingHours > 0 ? rate : 0;
            } else {
                amount = billableHours * rate;
            }
            
            // Update display
            document.getElementById('preview-total-progress').textContent = totalProgress.toFixed(2);
            document.getElementById('preview-working-hours').textContent = workingHours.toFixed(2);
            document.getElementById('preview-billable-hours').textContent = billableHours.toFixed(2);
            document.getElementById('preview-amount').textContent = '₹' + amount.toFixed(2);
        }
        
        // Bind events
        document.getElementById('machine_start_reading').addEventListener('input', calculate);
        document.getElementById('machine_end_reading').addEventListener('input', calculate);
        document.getElementById('machine_idle_reading').addEventListener('input', calculate);
        
        // Always show fuel consumption form (like create blade)
        const fuelForm = document.getElementById('fuel-consumption-form');
        if (fuelForm) {
            fuelForm.classList.remove('d-none');
        }
        
        // Add fuel consumption functionality
        let materials = {!! json_encode($materials) !!};
        let rowIndex = document.querySelectorAll('#consumption-items-table tbody tr').length;
        
        // Add a default row if tbody is empty
        const tbody = document.querySelector('#consumption-items-table tbody');
        if (tbody && tbody.children.length === 0) {
            addItemRow();
        }
        
        function addItemRow(item = {}) {
            const tbody = document.querySelector('#consumption-items-table tbody');
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td>
                    <select name="items[${rowIndex}][material_id]" class="form-control item-material" required>
                        <option value="">Select Material</option>
                        ${Object.entries(materials).map(([id, mat]) => 
                            parseInt(mat.category_id) === 2 ? 
                            `<option value="${id}">${mat.name}</option>` : ''
                        ).join('')}
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <input type="text" class="form-control item-stock" readonly value="0">
                        <span class="input-group-text item-stock-unit">unit</span>
                    </div>
                </td>
                <td>
                    <div class="input-group">
                        <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-quantity" min="1" value="1" required>
                        <input type="hidden" name="items[${rowIndex}][unit]" class="item-unit" value="">
                        <span class="input-group-text item-unit-label"></span>
                    </div>
                </td>
                <td><input type="text" name="items[${rowIndex}][remarks]" class="form-control"></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>
            `;
            
            tbody.appendChild(row);
            rowIndex++;
        }
        
        // Add row button
        document.getElementById('add-item-row').addEventListener('click', addItemRow);
        
        // Remove row
        document.getElementById('consumption-items-table').addEventListener('click', function (e) {
            if(e.target && e.target.matches('.remove-item-row')) {
                e.target.closest('tr').remove();
            }
        });
        
        // Update stock/unit on material change
        document.getElementById('consumption-items-table').addEventListener('change', function (e) {
            if(e.target && e.target.matches('.item-material')) {
                const row = e.target.closest('tr');
                const matId = e.target.value;
                const stockEl = row.querySelector('.item-stock');
                const unitLabelEl = row.querySelector('.item-unit-label');
                const hiddenUnitEl = row.querySelector('.item-unit');

                if(matId && materials[matId]){
                    stockEl.value = materials[matId].total_qty ?? 0;
                    unitLabelEl.textContent = materials[matId].unit ?? 'unit';
                    hiddenUnitEl.value = materials[matId].unit ?? '';
                } else {
                    stockEl.value = 0;
                    unitLabelEl.textContent = 'unit';
                    hiddenUnitEl.value = '';
                }
            }
        });
        
        // Initial calculation
        calculate();
    }
    
    // Start waiting for elements
    waitForElements();
})();
</script>