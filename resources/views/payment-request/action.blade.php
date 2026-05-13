@permission('purchase-invoice show')
<div class="action-btn me-2">
    <a href="{{ route('purchase-invoice.show', $invoice->id) }}"
       class="mx-3 btn btn-sm align-items-center bg-warning"
       data-bs-toggle="tooltip" title="{{ __('View') }}">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>
@endpermission

@permission(['manage-payment create']) 
@if($invoice->isPaid())
    <span class="badge bg-success">{{ __('Paid') }}</span>
@elseif($invoice->hasPendingPaymentRequest())
    @php $pendingRequest = $invoice->getPendingPaymentRequest(); @endphp
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center bg-primary"
           data-url="{{ route('payment-request.approval', $pendingRequest->id) }}"
           data-ajax-popup="true" data-size="lg"
           data-bs-toggle="tooltip" title="{{ __('Review Request') }}"
           data-title="{{ __('Payment Request Approval') }}">
            <i class="ti ti-check text-white"></i>
        </a>
    </div>
@elseif($invoice->getMaxAllowedPaymentRequest() > 0)
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center bg-info"
           data-url="{{ route('payment-request.create-modal', $invoice->id) }}"
           data-ajax-popup="true" data-size="lg"
           data-bs-toggle="tooltip" title="{{ __('Create Payment Request') }}"
           data-title="{{ __('Create Payment Request') }}">
            <i class="ti ti-plus text-white"></i>
        </a>
    </div>
@endif
@endpermission