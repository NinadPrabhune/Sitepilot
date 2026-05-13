@extends('layouts.main')

@section('page-title', __('Machinery Payment Requests'))
@section('page-breadcrumb', __('Machinery,Payment Requests'))

@section('page-action')
<div class="d-flex">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-light border me-2">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
    <a href="{{ route('machinery-payment.create') }}" class="btn btn-sm bg-primary text-white">
        <i class="ti ti-plus text-white"></i> {{ __('Create Request') }}
    </a>
</div>
@endsection

@push('css')
@include('layouts.includes.datatable-css')
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-end">
                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                            <select class="form-select" name="status_filter" id="statusFilter">
                                <option value="">{{ __('All Statuses') }}</option>
                                <option value="draft">{{ __('Draft') }}</option>
                                <option value="submitted">{{ __('Submitted') }}</option>
                                <option value="verified">{{ __('Verified') }}</option>
                                <option value="approved">{{ __('Approved') }}</option>
                                <option value="locked">{{ __('Locked') }}</option>
                                <option value="paid">{{ __('Paid') }}</option>
                                <option value="rejected">{{ __('Rejected') }}</option>
                                <option value="hold">{{ __('Hold') }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('machinery', __('Machinery'), ['class' => 'form-label']) }}
                            <select class="form-select" name="machinery_filter" id="machineryFilter">
                                <option value="">{{ __('All Machinery') }}</option>
                                @foreach($machineries ?? [] as $machinery)
                                    <option value="{{ $machinery->id }}">{{ $machinery->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                            {{ Form::date(
                            'start_date',
                            request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                            ['class' => 'form-control', 'id' => 'dateFrom', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>

                    <div class="col-xl-2 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                            {{ Form::date(
                            'end_date',
                            request('end_date') ?? \Carbon\Carbon::now()->toDateString(),
                            ['class' => 'form-control', 'id' => 'dateTo', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>
                    
                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm bg-primary me-1" data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                           id="applyfilter" data-original-title="{{ __('apply') }}">
                            <span class="btn-inner--icon"><i class="ti ti-search text-white"></i></span>
                        </a>
                        <a href="#!" class="btn btn-sm bg-danger" data-bs-toggle="tooltip"
                           title="{{ __('Reset') }}" id="clearfilter" data-original-title="{{ __('Reset') }}">
                            <span class="btn-inner--icon"><i class="ti ti-trash-off text-white"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    {{ $dataTable->table(['width' => '100%']) }}
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('layouts.includes.datatable-js')
{{ $dataTable->scripts() }}
<script>
// Action functions for DataTable buttons
async function submitRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Submit Payment Request?',
        text: 'Do you want to submit this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/submit`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Submitted!',
                        'Request submitted successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function verifyRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Verify Payment Request?',
        text: 'Do you want to verify this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, verify it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/verify`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Verified!',
                        'Request verified successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function approveRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Approve Payment Request?',
        text: 'This will lock period and link ledger entries. Do you want to approve this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/approve`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Approved!',
                        'Request approved successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function lockRequest(paymentRequestId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Lock Payment Request?',
        text: 'This will freeze the payment period and lock all linked ledger entries to prevent any modifications. The payment amounts will become final and ready for processing. Do you want to lock this payment request?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, lock it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/machinery/payment-requests/${paymentRequestId}/lock`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swalWithBootstrapButtons.fire(
                        'Locked!',
                        'Request locked successfully',
                        'success'
                    );
                    $('#payment-requests-table').DataTable().ajax.reload();
                } else {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Error: ' + JSON.stringify(data),
                        'error'
                    );
                }
            })
            .catch(error => {
                swalWithBootstrapButtons.fire(
                    'Error!',
                    'Error: ' + error.message,
                    'error'
                );
            });
        }
    });
}

async function createMachineryPayment(paymentRequestId) {
    // Load modal content via AJAX (same as createErpPayment)
    try {
        const response = await fetch(`/machinery/payment-requests/${paymentRequestId}/payment-modal`, {
            headers: {
                'Accept': 'text/html',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const modalHtml = await response.text();
        
        // Create and show modal
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = `
            <div class="modal fade" id="machineryPaymentModal" tabindex="-1" aria-labelledby="machineryPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="machineryPaymentModalLabel">
                                <i class="ti ti-building-factory-2 me-2"></i>Create Machinery Payment
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        ${modalHtml}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modalContainer);
        
        // Initialize and show modal
        const modal = new bootstrap.Modal(document.getElementById('machineryPaymentModal'));
        modal.show();
        
        // Clean up modal after hidden
        document.getElementById('machineryPaymentModal').addEventListener('hidden.bs.modal', function () {
            document.body.removeChild(modalContainer);
        });
        
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load payment form. Please try again.'
        });
    }
}

// Global function for submitting machinery payment
window.submitMachineryPayment = function() {
    const form = document.getElementById('machineryPaymentForm');
    if (!form) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Payment form not found'
        });
        return;
    }
    
    const formData = new FormData(form);
    
    // Basic validation
    const amount = formData.get('amount');
    const paymentDate = formData.get('payment_date');
    const paymentMode = formData.get('payment_mode');
    const paymentProof = formData.get('payment_proof');
    
    if (!amount || !paymentDate || !paymentMode || !paymentProof || paymentProof.size === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Please fill in all required fields'
        });
        return;
    }
    
    // Validate amount
    if (parseFloat(amount) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Amount must be greater than 0'
        });
        return;
    }
    
    // Get remaining balance from hidden input
    const remainingBalanceInput = document.getElementById('remaining-balance');
    const remainingBalance = remainingBalanceInput ? parseFloat(remainingBalanceInput.value) : 0;
    
    // Validate amount against remaining balance
    if (parseFloat(amount) > remainingBalance) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: `Amount cannot be greater than Remaining Balance (${remainingBalance.toFixed(2)})`
        });
        return;
    }
    
    // Additional server-side validation check
    if (parseFloat(amount) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Amount must be greater than 0'
        });
        return;
    }
    
    // Show loading
    const submitBtn = document.querySelector('#machineryPaymentModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';
    
    // Get payment request ID from form
    const paymentRequestId = formData.get('payment_request_id');
    
    // Submit via AJAX
    $.ajax({
        url: `/machinery/payment-requests/${paymentRequestId}/create-erp-payment`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Machinery payment created successfully'
                });
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('machineryPaymentModal')).hide();
                // Reload page to show updated payment history
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to create payment'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'Failed to create payment';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    // Use default error message
                }
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        },
        complete: function() {
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
};
</script>
@endpush
@endsection
