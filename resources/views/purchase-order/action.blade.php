@permission('purchase-order print')

@if(in_array($po->status, ['Approved','Partial Received','Completed','Closed','Partial']))
<div class="action-btn me-2">
<a href="{{ route('purchase-order.print-invoice', $po->id) }}" target="_blank" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" title="{{ __('Print Invoice') }}">
    <i class="ti ti-printer"></i>
</a><!-- comment -->
</div>
@endif
@endpermission

@permission('supplier-advance manage')
@if(in_array($po->status, ['Approved']))
<!--<div class="action-btn me-2">
    <a href="{{ route('supplier-advance.create-from-po', $po->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('Request Advance') }}">
        <i class="ti ti-credit-card text-white"></i>
    </a>
</div>-->
@endif
@endpermission

@permission('purchase-order advance-request')
@if(in_array($po->status, ['Approved']) && !$po->isPaymentCompleted() && !$po->hasAdvanceRequest())
<div class="action-btn me-2">
    <a href="javascript:void(0);" 
       class="btn btn-sm btn-info po-advance-request-btn" 
       data-po-id="{{ $po->id }}" 
       data-bs-toggle="tooltip" 
       title="{{ __('Request PO Advance') }}">
        <i class="ti ti-credit-card text-white"></i>
    </a>
</div>
@endif
@endpermission

<!--                                    <a href="{{ route('purchase-order.print-invoice-2', $po->id) }}" target="_blank" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" title="{{ __('Print Invoice V2') }}">
    <i class="ti ti-printer"></i>
</a>-->


@permission(['purchase-order payment','manage-payment create'])

@if(in_array($po->status, ['Approved','Partial Received']))
<!--<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center btn-info" 
       data-url="{{ route('payments-module.create-from-po', $po->id) }}" 
       data-ajax-popup="true" 
       data-size="xl" 
       data-bs-toggle="tooltip" 
       data-bs-original-title="{{ __('Make Payment') }}" 
       data-title="{{ __('Make Payment') }}"> 
        <i class="ti ti-cash text-white"></i> 
    </a>
</div>-->

@endif

@endpermission


@permission('purchase-order edit')
@if(in_array($po->status, ['Draft', 'Flagged']) || $po->display_status == 'Flagged - Corrected')
<div class="action-btn me-2">
    <a href="javascript:void(0);" 
        data-size="xxl"
        data-url="{{ route('purchase-order.edit', $po->id) }}" 
        data-ajax-popup="true" 
        data-bs-toggle="tooltip" 
        data-bs-original-title="{{ __('Edit') }}" 
        data-title="{{ __('Edit Purchase Order') }}" 
        class="btn btn-sm btn-primary">
        <i class="ti ti-edit text-white"></i>
    </a>
</div>
@endif
@endpermission

@permission('purchase-order edit')
@if(in_array($po->status, ['Draft', 'Flagged', 'Partial Received']) || $po->display_status == 'Flagged - Corrected')
<div class="action-btn me-2">
    <a href="javascript:void(0);" 
        data-url="{{ route('purchase-order.approve', $po->id) }}" 
        data-ajax-popup="true"
        data-bs-toggle="tooltip" 
        data-bs-original-title="{{ __('Approve') }}" 
        data-title="{{ __('Update Purchase Order Status') }}"
        class="btn btn-sm btn-success">
        <i class="ti ti-check text-white"></i>
    </a>
</div>
@endif
@endpermission

@permission('purchase-order delete')
@if($po->status == 'Draft' && $po->items->isEmpty())
<div class="action-btn me-2">
    {!! Form::open(['method' => 'DELETE', 'route' => ['purchase-order.destroy', $po->id], 'class' => 'd-inline']) !!}
    <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Delete') }}">
        <i class="ti ti-trash text-white"></i>
    </button>
    {!! Form::close() !!}
</div>
@endif
@endpermission
