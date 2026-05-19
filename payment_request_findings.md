# Payment Request Creation Form Findings

## 1. Form/Controller File for Creating Payment Requests

**File Path:** `C:\wamp64\www\SitePilot\resources\views\machinery-payment\create.blade.php`

This is the Blade template file that contains the HTML form for creating payment requests at the URL pattern `machinery/payment-requests/create`.

**Relevant Code Snippet:**
```blade
@extends('layouts.main')

@section('page-title', __('Create Machinery Payment Request'))
@section('page-breadcrumb', __('Machinery,Payment Requests,Create'))

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
                    <!-- Form fields for machinery, supplier, period selection -->
                    <!-- ... -->
                    <div class="row">
                        <div class="col-12 text-center">
                            <button type="button" id="calculateBtn" class="btn btn-primary">
                                <i class="ti ti-calculator me-2"></i> {{ __('Calculate from Ledger') }}
                            </button>
                        </div>
                    </div>
                    
                    <!-- Calculation Results Section -->
                    <div id="calculationResults" class="mt-4" style="display: none;">
                        <!-- Results display -->
                        <div class="row mt-3">
                            <div class="col-12 text-center">
                                <button type="button" id="submitBtn" class="btn btn-success">
                                    <i class="ti ti-check me-2"></i> {{ __('Submit Payment Request') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

<script>
// JavaScript handlers for form interactions
// Calculate button handler (lines 344-467)
// Submit button handler (lines 469-533)
</script>
```

## 2. JavaScript/TypeScript File Handling "Submit Payment Request" Button

**File Path:** `C:\wamp64\www\SitePilot\resources\views\machinery-payment\create.blade.php` (contains inline JavaScript)

The JavaScript handling the submit button is located inline in the Blade template file.

**Relevant Code Snippet (Submit Button Handler):**
```javascript
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
```

## 3. Validation Logic for "Please fill all required fields and calculate first"

**File Path:** `C:\wamp64\www\SitePilot\resources\views\machinery-payment\create.blade.php`

The validation logic is contained within the submit button handler (lines 476-482) and calculate button handler (lines 351-358) in the same file.

**Relevant Code Snippets:**

**Submit Button Validation (line 476-482):**
```javascript
if (!machineryId || !supplierId || !startDate || !endDate) {
    Swal.fire({
        icon: 'warning',
        title: 'Validation Error',
        text: '{{ __('Please fill all required fields and calculate first') }}'
    });
    return;
}
```

**Calculate Button Validation (line 351-358):**
```javascript
if (!machineryId || !supplierId || !startDate || !endDate) {
    Swal.fire({
        icon: 'warning',
        title: 'Validation Error',
        text: '{{ __('Please fill all required fields') }}'
    });
    return;
}
```

## Additional Relevant Files

**Controller File:** `C:\wamp64\www\SitePilot\app\Http\Controllers\MachineryPaymentRequestController.php`

This file contains the backend logic for handling form submissions:
- `create()` method: Shows the form (line 40-45)
- `store()` method: Handles form submission to create payment request (line 75-105)
- `calculate()` method: Handles AJAX calculation requests (line 110-137)

**Route Definition:** `C:\wamp64\www\SitePilot\routes\web.php`

Contains the route definitions for payment requests:
```php
Route::prefix('machinery/payment-requests')->name('machinery-payment.')->group(function () {
    Route::get('/', [MachineryPaymentRequestController::class, 'index'])->name('index');
    Route::get('/create', [MachineryPaymentRequestController::class, 'create'])->name('create');
    Route::post('/store-ajax', [MachineryPaymentRequestController::class, 'calculate'])->name('store-ajax');
    Route::post('/store', [MachineryPaymentRequestController::class, 'store'])->name('store');
    // ... other routes
});
```