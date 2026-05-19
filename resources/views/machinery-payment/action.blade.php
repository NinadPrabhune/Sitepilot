@permission('machinery-payment manage')
<div class="action-btn me-2">
    <a href="{{ route('machinery-payment.show', $request->id) }}" 
       class="mx-3 btn btn-sm align-items-center bg-warning"
       data-bs-toggle="tooltip" title="{{ __('View') }}">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>
@endpermission

@if($request->status === 'draft')
    <div class="action-btn me-2">
        <button onclick="submitRequest({{ $request->id }})" 
                class="mx-3 btn btn-sm align-items-center bg-success"
                data-bs-toggle="tooltip" title="{{ __('Submit') }}">
            <i class="ti ti-send text-white"></i>
        </button>
    </div>
@elseif($request->status === 'submitted')
    <div class="action-btn me-2">
        <button onclick="approveRequest({{ $request->id }})" 
                class="mx-3 btn btn-sm align-items-center bg-success"
                data-bs-toggle="tooltip" title="{{ __('Approve') }}">
            <i class="ti ti-checks text-white"></i>
        </button>
    </div>
@elseif($request->status === 'approved')
    <div class="action-btn me-2">
        <button onclick="lockRequest({{ $request->id }})" 
                class="mx-3 btn btn-sm align-items-center bg-warning"
                data-bs-toggle="tooltip" 
                title="{{ __('Lock payment period and secure ledger entries') }}">
            <i class="ti ti-lock text-white"></i>
        </button>
    </div>
@elseif($request->status === 'locked')
    @if(config('machinery_payment.enable_erp_payment_button', false) && in_array($request->settlement_status, ['unpaid', 'partial']))
        <div class="action-btn me-2">
            <button onclick="createMachineryPayment({{ $request->id }})" 
                    class="mx-3 btn btn-sm align-items-center bg-primary"
                    data-bs-toggle="tooltip" title="{{ __('Create Machinery Payment') }}">
                <i class="ti ti-building-factory-2 text-white"></i>
            </button>
        </div>
    @endif
@endif
