{{ Form::open(['route' => ['payment-request.approval.update', $paymentRequest->id], 'method' => 'POST', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    @if(isset($invoice))
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-light border-0">
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Invoice Total') }}</small>
                            <strong class="h6 mb-0">₹{{ format_indian_currency($invoice->grand_total) }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Advance Used') }}</small>
                            <strong class="h6 mb-0">₹{{ format_indian_currency($invoice->getAdvanceUtilizedForInvoice()) }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Already Paid') }}</small>
                            <strong class="h6 mb-0">₹{{ format_indian_currency($invoice->getActualPaidAmount()) }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Active Requests') }}</small>
                            <strong class="h6 mb-0">₹{{ format_indian_currency($invoice->getActivePaymentRequestsSum()) }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Net Payable') }}</small>
                            <strong class="h6 mb-0 text-success">₹{{ format_indian_currency($invoice->getNetPayableWithoutRequests()) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @elseif(isset($po))
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-light border-0">
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col">
                            <small class="text-muted d-block">{{ __('PO Number') }}</small>
                            <strong class="h6 mb-0">{{ $po->po_number }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('PO Total') }}</small>
                            <strong class="h6 mb-0">₹{{ format_indian_currency($po->grand_total) }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Already Paid') }}</small>
                            <strong class="h6 mb-0">₹{{ format_indian_currency($po->total_paid) }}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">{{ __('Available Balance') }}</small>
                            <strong class="h6 mb-0 text-success">₹{{ format_indian_currency($po->grand_total - $po->total_paid) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <h6 class="mb-3">{{ __('Payment Requests') }}</h6>

            @if($paymentRequests->isEmpty())
                <div class="alert alert-info">{{ isset($invoice) ? __('No payment requests found for this invoice.') : __('No payment requests found for this PO.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="paymentRequestsTable">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Requested') }}</th>
                                <th>{{ __('Approved') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('By') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentRequests as $pr)
                            <tr>
                                <td>#{{ $pr->id }}</td>
                                <td>
                                    @if($pr->isPoAdvance())
                                        <span class="badge bg-primary">{{ __('PO Advance') }}</span>
                                    @elseif($pr->isInvoicePayment())
                                        <span class="badge bg-secondary">{{ __('Invoice Payment') }}</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ __('Unknown') }}</span>
                                    @endif
                                </td>
                                <td>₹{{ format_indian_currency($pr->requested_amount) }}</td>
                                <td>
                                    @if($pr->isPending())
                                        <span class="text-muted">-</span>
                                    @else
                                        ₹{{ format_indian_currency($pr->approved_amount ?? 0) }}
                                    @endif
                                </td>
                                <td>
                                    @if($pr->status === 'pending')
                                        <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                    @elseif($pr->status === 'approved')
                                        <span class="badge bg-success">{{ __('Approved') }}</span>
                                    @elseif($pr->status === 'partially_approved')
                                        <span class="badge bg-info text-dark">{{ __('Partial') }}</span>
                                    @elseif($pr->status === 'rejected')
                                        <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($pr->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $pr->requestedBy?->name ?? '-' }}</td>
                                <td>
                                    @if($pr->isPending())
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-success" onclick="approveRequest({{ $pr->id }}, 'approve', {{ $pr->requested_amount }})" title="{{ __('Approve') }}">
                                                <i class="ti ti-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-info" onclick="approveRequest({{ $pr->id }}, 'partial', {{ $pr->requested_amount }})" title="{{ __('Partial') }}">
                                                <i class="ti ti-minus"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" onclick="approveRequest({{ $pr->id }}, 'reject')" title="{{ __('Reject') }}">
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted small">{{ __('Processed') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Close') }}" class="btn btn-light" data-bs-dismiss="modal">
</div>
{{ Form::close() }}

<div class="modal fade" id="approvalModal" tabindex="-1" role="dialog" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {{ Form::open(['id' => 'approvalForm', 'method' => 'POST']) }}
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalLabel">{{ __('Process Payment Request') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="approvalAction" value="">
                <input type="hidden" name="payment_request_id" id="paymentRequestId" value="">
                
                <div id="approvedAmountGroup" class="mb-3 d-none">
                    <label for="approved_amount" class="form-label">{{ __('Approved Amount') }}</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" name="approved_amount" id="approvedAmount" class="form-control" step="0.01" min="0.01" readonly>
                    </div>
                    <small class="text-muted">{{ __('Max allowed: ₹') }}<span id="maxAllowedDisplay"></span></small>
                </div>
                
                <div id="rejectionReasonGroup" class="mb-3 d-none">
                    <label for="rejection_reason" class="form-label">{{ __('Reason for Rejection') }}<x-required></x-required></label>
                    <textarea name="rejection_reason" id="rejectionReason" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var isSubmitting = false;
    var currentRequestedAmount = 0;

    window.approveRequest = function(id, action, requestedAmount) {
        if (isSubmitting) return;

        // Store the requested amount for validation
        currentRequestedAmount = requestedAmount || 0;

        $('#paymentRequestId').val(id);
        $('#approvalAction').val(action);
        $('#approvedAmountGroup').addClass('d-none');
        $('#rejectionReasonGroup').addClass('d-none');
        $('#approvedAmount').val('');
        $('#rejectionReason').val('');

        if (action === 'approve') {
            $('#approvalModalLabel').text('{{ __("Approve Payment Request") }}');
            $('#approvedAmountGroup').removeClass('d-none');
            $('#approvedAmount').prop('readonly', true).val(requestedAmount).attr('max', requestedAmount);
            $('#maxAllowedDisplay').text(requestedAmount.toLocaleString('en-IN'));
        } else if (action === 'partial') {
            $('#approvalModalLabel').text('{{ __("Partially Approve Payment Request") }}');
            $('#approvedAmountGroup').removeClass('d-none');
            $('#approvedAmount').prop('readonly', false).val(requestedAmount).attr('max', requestedAmount);
            $('#maxAllowedDisplay').text(requestedAmount.toLocaleString('en-IN'));
            setTimeout(function() { $('#approvedAmount').focus(); }, 300);
        } else if (action === 'reject') {
            $('#approvalModalLabel').text('{{ __("Reject Payment Request") }}');
            $('#rejectionReasonGroup').removeClass('d-none');
            setTimeout(function() { $('#rejectionReason').focus(); }, 300);
        }

        var modal = new bootstrap.Modal(document.getElementById('approvalModal'));
        modal.show();
    };

    $('#approvedAmount').on('input change', function() {
        var requestedAmount = currentRequestedAmount;

        var value = parseFloat(this.value) || 0;
        if (value > requestedAmount) {
            this.value = requestedAmount;
            toastrs('{{ __("Warning") }}', '{{ __("Amount exceeds requested amount. Adjusted to requested amount.") }}', 'warning');
        }
        if (value < 0.01 && this.value !== '') {
            this.value = '';
        }
    });

    // Also validate on blur to catch paste events
    $('#approvedAmount').on('blur', function() {
        var requestedAmount = currentRequestedAmount;
        var value = parseFloat(this.value) || 0;
        if (value > requestedAmount) {
            this.value = requestedAmount;
            toastrs('{{ __("Warning") }}', '{{ __("Amount exceeds requested amount. Adjusted to requested amount.") }}', 'warning');
        }
    });

    $(document).on('submit', '#approvalForm', function(e) {
        e.preventDefault();

        if (isSubmitting) return;
        isSubmitting = true;

        var id = $('#paymentRequestId').val();
        var action = $('#approvalAction').val();
        var url = '{{ route("payment-request.approve.single", ["id" => ":id"]) }}'.replace(':id', id);

        var approvedAmount = parseFloat($('#approvedAmount').val()) || 0;
        var rejectionReason = $('#rejectionReason').val().trim();

        // Use the stored requested amount for validation
        var requestedAmount = currentRequestedAmount;

        // Validation
        if (action === 'reject') {
            if (!rejectionReason) {
                toastrs('{{ __("Error") }}', '{{ __("Rejection reason is required") }}', 'error');
                isSubmitting = false;
                return;
            }
        } else {
            if (approvedAmount < 0.01) {
                toastrs('{{ __("Error") }}', '{{ __("Approved amount must be greater than 0") }}', 'error');
                isSubmitting = false;
                return;
            }
            if (approvedAmount > requestedAmount) {
                toastrs('{{ __("Error") }}', '{{ __("Cannot approve more than requested amount") }}', 'error');
                isSubmitting = false;
                return;
            }
        }

        // Confirmation for partial
        if (action === 'partial' && approvedAmount > 0) {
            // Auto-confirm for smoother UX
        }

        var submitBtn = $('#approvalForm button[type="submit"]');
        var originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> {{ __("Processing...") }}');

        var formData = {
            _token: '{{ csrf_token() }}',
            action: action,
            approved_amount: approvedAmount,
            rejection_reason: rejectionReason
        };

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    toastrs('{{ __("Success") }}', response.message, 'success');
                    $('#approvalModal').modal('hide');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    toastrs('{{ __("Error") }}', response.message, 'error');
                    submitBtn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            },
            error: function(xhr) {
                var message = xhr.responseJSON?.message || '{{ __("Something went wrong.") }}';
                toastrs('{{ __("Error") }}', message, 'error');
                submitBtn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            }
        });
    });

    $('#approvalModal').on('hidden.bs.modal', function() {
        $('#approvalForm')[0].reset();
        isSubmitting = false;
    });
});
</script>