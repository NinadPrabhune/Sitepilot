
@permission('purchase-invoice show')
<div class="action-btn me-2">
    <a href="{{ route('purchase-invoice.show', $invoice->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
</div>
@endpermission

@permission('purchase-invoice print')
<div class="action-btn me-2">
    <a href="{{ route('purchase-invoice.print', $invoice->id) }}" target="_blank"
       class="mx-3 btn btn-sm align-items-center btn-secondary"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('Print') }}"><i class="ti ti-printer text-white"></i></a>
</div>
@endpermission

@permission('purchase-invoice edit')

<!--<div class="action-btn me-2">-->
    @if(strtolower($invoice->payment_status) === 'unpaid')
<!--    <a class="mx-3 btn btn-sm  align-items-center bg-info" data-url="{{ route('purchase-invoice.edit', $invoice->id) }}"
       data-ajax-popup="true" data-size="xl" data-bs-toggle="tooltip" title="{{ __('Edit') }}"
       data-title="{{ __('Edit Purchase Invoice') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>-->
    @endif
<!--</div>-->

@endpermission

  <!--Make Payment--> 
  @permission(['purchase-invoice payment','manage-payment create']) 

  @if(strtolower($invoice->payment_status) != 'paid')
<!--  <div class="action-btn me-2"> 
    <a class="mx-3 btn btn-sm align-items-center btn-info" data-url="{{ route('payments-module.create-from-invoice', $invoice->id) }}" data-ajax-popup="true" data-size="lg" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Make Payment') }}" data-title="{{ __('Make Payment') }}"> <i class="ti ti-cash text-white"></i> </a>
  </div>-->
  @endif

  @endpermission 
 
  
@if($invoice->isPaid())
    <span class="badge bg-success" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Invoice is fully paid') }}">{{ __('Paid') }}</span>
@elseif($invoice->hasPendingPaymentRequest())
    @php $pendingRequest = $invoice->getPendingPaymentRequest(); @endphp
<!--    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center btn-warning"
           data-url="{{ route('payment-request.approval', $pendingRequest->id) }}"
           data-ajax-popup="true"
           data-size="lg"
           data-bs-toggle="tooltip"
           data-original-title="{{ __('Review pending payment request') }}"
           data-title="{{ __('Payment Request Approval') }}">
            <i class="ti ti-eye text-white"></i>
        </a>
    </div>-->
@elseif($invoice->getMaxAllowedPaymentRequest() > 0 && (!$invoice->po || !$invoice->po->isPaymentCompleted()))
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center btn-primary"
           data-url="{{ route('payment-request.create-modal', $invoice->id) }}"
           data-ajax-popup="true"
           data-size="xl"
           data-bs-toggle="tooltip"
           data-bs-original-title="{{ __('Create payment request for this invoice') }}"
           data-title="{{ __('Request Payment') }}">
            <i class="ti ti-credit-card text-white"></i>
        </a>
    </div>
@else
    <span class="badge bg-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('No remaining balance - covered by advances/payments') }}">{{ __('No Balance') }}</span>
@endif




@permission('purchase-invoice delete')
@if($invoice->payment_status === 'unpaid' && $invoice->status === 'Rejected')
<div class="action-btn me-2">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['purchase-invoice.destroy', $invoice->id],
    'id' => 'delete-form-' . $invoice->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm  align-items-center btn-danger show_confirm" data-bs-toggle="tooltip"
       data-bs-original-title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
    {!! Form::close() !!}
</div>
@endif
@endpermission
