@extends('layouts.main')

@section('page-title', __('Create Machinery Payment Request'))
@section('page-breadcrumb', __('Machinery,Payment Requests,Create'))

@section('page-action')
<div class="d-flex">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-light border me-2">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Create Machinery Payment Request') }}</h5>
            </div>
            <div class="card-body">
                <form id="paymentRequestForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                {{ Form::label('machinery_id', __('Machinery'), ['class' => 'form-label']) }}
                                <span class="text-danger">*</span>
                                <select class="form-select" id="machinery_id" name="machinery_id" required>
                                    <option value="">{{ __('Select Machinery') }}</option>
                                    @foreach($machineries ?? [] as $machinery)
                                        <option value="{{ $machinery->id }}" data-supplier="{{ $machinery->supplier_id }}">{{ $machinery->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}
                                <span class="text-danger">*</span>
                                <select class="form-select" id="supplier_id" name="supplier_id" required readonly style="background-color: #f8f9fa;">
                                    <option value="">{{ __('Select Machinery First') }}</option>
                                </select>
                                <small class="text-muted">{{ __('Supplier will be auto-selected based on machinery') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Period Selection -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">{{ __('Monthly Period Selection') }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                {{ Form::label('period_year', __('Year'), ['class' => 'form-label']) }}
                                                <span class="text-danger">*</span>
                                                <select class="form-select" id="period_year" required>
                                                    @php
                                                        $currentYear = date('Y');
                                                        for($year = $currentYear; $year >= $currentYear - 2; $year--) {
                                                            echo '<option value="'.$year.'"'.($year == $currentYear ? ' selected' : '').'>'.$year.'</option>';
                                                        }
                                                    @endphp
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                {{ Form::label('period_month', __('Month'), ['class' => 'form-label']) }}
                                                <span class="text-danger">*</span>
                                                <select class="form-select" id="period_month" required>
                                                    @php
                                                        $months = [
                                                            '01' => 'January', '02' => 'February', '03' => 'March',
                                                            '04' => 'April', '05' => 'May', '06' => 'June',
                                                            '07' => 'July', '08' => 'August', '09' => 'September',
                                                            '10' => 'October', '11' => 'November', '12' => 'December'
                                                        ];
                                                        $currentMonth = date('m');
                                                        foreach($months as $num => $name) {
                                                            echo '<option value="'.$num.'"'.($num == $currentMonth ? ' selected' : '').'>'.$name.'</option>';
                                                        }
                                                    @endphp
                                                </select>
                                            </div>
                                        </div>
                                   
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                {{ Form::label('period_start', __('Period Start'), ['class' => 'form-label']) }}
                                                <input type="text" class="form-control" id="period_start" name="period_start" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                {{ Form::label('period_end', __('Period End'), ['class' => 'form-label']) }}
                                                <input type="text" class="form-control" id="period_end" name="period_end" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 text-center">
                            <button type="button" id="calculateBtn" class="btn btn-primary">
                                <i class="ti ti-calculator me-2"></i> {{ __('Calculate from Ledger') }}
                            </button>
                            <div class="mt-2">
                                <small class="text-muted">{{ __('This will calculate the net payable amount based on ledger entries for the selected period') }}</small>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Calculation Results -->
                <div id="calculationResults" class="mt-4" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">{{ __('Calculation Results') }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="grossAmountValue" class="mb-1 text-success">Rs. 0.00</h5>
                                        <small class="text-muted">{{ __('Total Billing') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="creditsValue" class="mb-1">Rs. 0.00</h5>
                                        <small class="text-muted">{{ __('Credits') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="debitsValue" class="mb-1">Rs. 0.00</h5>
                                        <small class="text-muted">{{ __('Debits') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 border rounded bg-light">
                                        <h5 id="netPayableValue" class="mb-1 text-primary">Rs. 0.00</h5>
                                        <small class="text-muted">{{ __('Net Payable') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 border rounded">
                                        <h5 id="entryCountValue" class="mb-1">0</h5>
                                        <small class="text-muted">{{ __('Entry Count') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <strong>{{ __('Calculation Formula:') }}</strong>
                                        <span id="formulaDisplay">Net Payable = Credits - Debits</span>
                                        <hr class="my-2">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <small>
                                                    <strong>Credits:</strong> Work charges/earnings<br>
                                                    <strong>Debits:</strong> Advances, deductions<br>
                                                    <strong>Net:</strong> Outstanding balance
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <small id="calculationDebug"></small>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <small class="text-muted">Negative = Supplier owes us</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 text-center">
                                    <button type="button" id="submitBtn" class="btn btn-success">
                                        <i class="ti ti-check me-2"></i> {{ __('Submit Payment Request') }}
                                    </button>
                                    <div class="mt-2">
                                        <small class="text-muted">{{ __('Payment request will be created and submitted for approval') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Machinery change handler - Auto-select supplier
const machinerySelect = document.getElementById('machinery_id');
const supplierSelect = document.getElementById('supplier_id');
const periodYear = document.getElementById('period_year');
const periodMonth = document.getElementById('period_month');
const periodStart = document.getElementById('period_start');
const periodEnd = document.getElementById('period_end');
const calculateBtn = document.getElementById('calculateBtn');
const submitBtn = document.getElementById('submitBtn');

// Supplier data from server
const suppliers = @json($suppliers ?? []);

// Form state persistence
const FORM_STATE_KEY = 'machinery_payment_form_state';

// Save form state to localStorage
function saveFormState() {
    const formState = {
        machinery_id: machinerySelect.value,
        supplier_id: supplierSelect.value,
        period_year: periodYear.value,
        period_month: periodMonth.value
    };
    localStorage.setItem(FORM_STATE_KEY, JSON.stringify(formState));
}

// Load form state from localStorage
function loadFormState() {
    const savedState = localStorage.getItem(FORM_STATE_KEY);
    if (savedState) {
        try {
            const formState = JSON.parse(savedState);
            
            // Restore machinery selection
            if (formState.machinery_id) {
                machinerySelect.value = formState.machinery_id;
                // Trigger machinery change to update supplier
                machinerySelect.dispatchEvent(new Event('change'));
            }
            
            // Restore period selection
            if (formState.period_year) {
                periodYear.value = formState.period_year;
            }
            if (formState.period_month) {
                periodMonth.value = formState.period_month;
            }
            
            // Update period dates after restoring values
            updatePeriodDates();
            
        } catch (e) {
            console.error('Error loading form state:', e);
        }
    }
}

// Clear form state
function clearFormState() {
    localStorage.removeItem(FORM_STATE_KEY);

    // Explicitly reset form fields to defaults
    machinerySelect.value = '';
    supplierSelect.innerHTML = '<option value="">{{ __('Select Machinery First') }}</option>';
    periodYear.value = '{{ date("Y") }}';
    periodMonth.value = '{{ date("m") }}';
    updatePeriodDates();

    // Hide results if visible
    document.getElementById('calculationResults').style.display = 'none';
}

// Update period dates when month/year changes
function updatePeriodDates() {
    const year = periodYear.value;
    const month = periodMonth.value;
    if (year && month) {
        const startDate = `${year}-${month}-01`;
        const lastDay = new Date(year, parseInt(month), 0).getDate();
        const endDate = `${year}-${month}-${lastDay}`;
        periodStart.value = startDate;
        periodEnd.value = endDate;
    }
}

// Update supplier when machinery changes
machinerySelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const supplierId = selectedOption.getAttribute('data-supplier');
    
    // Clear and populate supplier dropdown
    supplierSelect.innerHTML = '';
    
    if (supplierId) {
        const supplier = suppliers.find(s => s.id == supplierId);
        if (supplier) {
            const option = document.createElement('option');
            option.value = supplier.id;
            option.textContent = supplier.name;
            option.selected = true;
            supplierSelect.appendChild(option);
        }
    } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = '{{ __('Select Machinery First') }}';
        supplierSelect.appendChild(option);
    }
    
    // Save form state after machinery change
    saveFormState();
});

// Listen for period changes
periodYear.addEventListener('change', function() {
    updatePeriodDates();
    saveFormState();
});

periodMonth.addEventListener('change', function() {
    updatePeriodDates();
    saveFormState();
});

// Initialize period dates on page load
updatePeriodDates();

// Clear form state on page load (reset form on refresh)
document.addEventListener('DOMContentLoaded', function() {
    clearFormState();
});

// Calculate button handler
calculateBtn.addEventListener('click', function() {
    const machineryId = machinerySelect.value;
    const supplierId = supplierSelect.value;
    const startDate = periodStart.value;
    const endDate = periodEnd.value;
    
    if (!machineryId || !supplierId || !startDate || !endDate) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: '{{ __('Please fill all required fields') }}'
        });
        return;
    }
    
    // Disable button during calculation
    this.disabled = true;
    this.innerHTML = '<i class="ti ti-loader-2 me-2"></i> {{ __('Calculating...') }}';
    
    fetch('{{ route('machinery-payment.store-ajax') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            machinery_id: machineryId,
            supplier_id: supplierId,
            period_start: startDate,
            period_end: endDate
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e, text);
            calculateBtn.disabled = false;
            calculateBtn.innerHTML = '<i class="ti ti-calculator me-2"></i> {{ __('Calculate from Ledger') }}';
            Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid JSON response' });
            return;
        }

        // Reset button
        calculateBtn.disabled = false;
        calculateBtn.innerHTML = '<i class="ti ti-calculator me-2"></i> {{ __('Calculate from Ledger') }}';

        console.log('Parsed data:', data);
        console.log('data.data:', data.data);
        console.log('credits:', data.data?.credits);
        console.log('debits:', data.data?.debits);
        console.log('net_payable:', data.data?.net_payable);

        if (data.success) {
            // Update results display
            const resultsContainer = document.getElementById('calculationResults');

            // Format currency values
            const grossAmount = parseFloat(data.data.gross_amount) || 0;
            const credits = parseFloat(data.data.credits) || 0;
            const debits = parseFloat(data.data.debits) || 0;
            const netPayable = parseFloat(data.data.net_payable) || 0;
            const entryCount = data.data.audit_snapshot?.entry_count || 0;

            // Client-side verification
            const calculatedNet = credits - debits;
            console.log('Verification: ' + credits + ' - ' + debits + ' = ' + calculatedNet);

            document.getElementById('grossAmountValue').textContent = 'Rs. ' + grossAmount.toFixed(2);
            document.getElementById('creditsValue').textContent = 'Rs. ' + credits.toFixed(2);
            document.getElementById('debitsValue').textContent = 'Rs. ' + debits.toFixed(2);
            document.getElementById('netPayableValue').textContent = 'Rs. ' + netPayable.toFixed(2);
            document.getElementById('entryCountValue').textContent = entryCount;

            // Update formula display with verification
            const formulaDisplay = document.getElementById('formulaDisplay');
            const calculationDebug = document.getElementById('calculationDebug');

            // Net Payable formula: Credits - Debits
            formulaDisplay.innerHTML = `
                <span class="text-success">Gross: Rs.${grossAmount.toFixed(2)}</span> -
                <span class="text-danger">Debits: Rs.${debits.toFixed(2)}</span> =
                <strong class="text-primary">Net: Rs.${netPayable.toFixed(2)}</strong>
            `;

            const balanceType = netPayable >= 0 ? 'Supplier is owed money' : 'Supplier overpaid (credit balance)';
            const mismatchWarning = Math.abs(calculatedNet - netPayable) > 0.01
                ? '<span class="text-danger">⚠️ MISMATCH!</span>'
                : '<span class="text-success">✓ Verified</span>';
            calculationDebug.innerHTML = `
                <strong>Balance:</strong> ${balanceType}<br>
                <strong>Entries:</strong> ${entryCount}<br>
                <strong>Formula:</strong> Credits (${credits}) - Debits (${debits}) = ${calculatedNet.toFixed(2)}
                ${mismatchWarning}
            `;

            resultsContainer.style.display = 'block';
            resultsContainer.scrollIntoView({ behavior: 'smooth' });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || '{{ __('Error creating payment request') }}'
            });
        }
    })
    .catch(error => {
        calculateBtn.disabled = false;
        calculateBtn.innerHTML = '<i class="ti ti-calculator me-2"></i> {{ __('Calculate from Ledger') }}';
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ __('Error') }}: ' + error.message
        });
    });
});

// Submit button handler
submitBtn.addEventListener('click', function() {
    const machineryId = machinerySelect.value;
    const supplierId = supplierSelect.value;
    const startDate = periodStart.value;
    const endDate = periodEnd.value;
    
    if (!machineryId || !supplierId || !startDate || !endDate) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: '{{ __('Please fill all required fields and calculate first') }}'
        });
        return;
    }
    
    // Disable button during submission
    this.disabled = true;
    this.innerHTML = '<i class="ti ti-loader-2 me-2"></i> {{ __('Submitting...') }}';
    
    fetch('{{ route('machinery-payment.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            machinery_id: machineryId,
            supplier_id: supplierId,
            period_start: startDate,
            period_end: endDate
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '{{ __('Payment request created successfully') }}'
            }).then(() => {
                clearFormState();
                window.location.href = '/machinery/payment-requests';
            });
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ti ti-check me-2"></i> {{ __('Submit Payment Request') }}';
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || '{{ __('Error creating payment request') }}'
            });
        }
    })
    .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="ti ti-check me-2"></i> {{ __('Submit Payment Request') }}';
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ __('Error') }}: ' + error.message
        });
    });
});
</script>
@endsection
