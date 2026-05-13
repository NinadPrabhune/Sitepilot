@permission('purchase-invoice show')
<div class="action-btn me-2">
    <a href="{{ route('payment-request.show', $pr->id) }}"
       class="mx-3 btn btn-sm align-items-center bg-warning"
       data-bs-toggle="tooltip" title="{{ __('View') }}">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>
@endpermission

@permission('manage-payment create')
@if($pr->isPending())
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-primary"
       data-url="{{ route('payment-request.approval', $pr->id) }}"
       data-ajax-popup="true" data-size="lg"
       data-bs-toggle="tooltip" title="{{ __('Approve') }}"
       data-title="{{ __('Payment Request Approval') }}">
        <i class="ti ti-check text-white"></i>
    </a>
</div>
@elseif($pr->canMakePayment())
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-info"
       data-url="{{ route('payments-module.create-from-payment-request', $pr->id) }}"
       data-ajax-popup="true" data-size="lg"
       data-bs-toggle="tooltip" title="{{ __('Make Payment') }}"
       data-title="{{ __('Make Payment') }}">
        <i class="ti ti-cash text-white"></i>
    </a>
</div>
@endif
@endpermission