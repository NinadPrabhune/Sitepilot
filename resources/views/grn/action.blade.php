<!-- 
@permission('grn show')
<div class="action-btn me-2">
    <a href="{{ route('grn.show', $grn->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
</div>
@endpermission

@permission('grn edit')
@if($grn->status === 'Pending')
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center btn-primary" data-url="{{ route('grn.edit', $grn->id) }}"
       data-ajax-popup="true" data-size="lg" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"
       data-title="{{ __('Edit GRN') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>
@endif
@endpermission

<div class="action-btn me-2">
    <a href="{{ route('grn.print', $grn->id) }}" target="_blank"
       class="mx-3 btn btn-sm align-items-center btn-secondary"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('Print') }}"><i class="ti ti-printer text-white"></i></a>
</div>

@permission('grn delete')
@if($grn->status === 'Pending')
<div class="action-btn me-2">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['grn.destroy', $grn->id],
    'id' => 'delete-form-' . $grn->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center btn-danger show_confirm" data-bs-toggle="tooltip"
       data-bs-original-title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
    {!! Form::close() !!}
</div>
@endif
@endpermission -->


@permission('grn show')
<!--                                    <a href="{{ route('grn.show', $grn->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-original-title="{{ __('View GRN Details') }}" title="{{ __('View GRN Details') }}" aria-label="{{ __('View GRN') }}">
    <i class="ti ti-eye"></i>
</a>-->
@endpermission

@permission('grn edit')
@if(!$grn->hasInvoice() && !$grn->is_locked)
<a data-size="xxl" data-url="{{ route('grn.edit', $grn->id) }}" data-ajax-popup="true" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit GRN') }}" title="{{ __('Edit GRN') }}" data-title="{{ __('Edit GRN') }}" class="btn btn-sm btn-primary" aria-label="{{ __('Edit GRN Details') }}">
    <i class="ti ti-edit"></i>
</a>
@endif
@endpermission

@permission('grn print')
<a href="{{ route('grn.print', $grn->id) }}" target="_blank" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Print GRN') }}" title="{{ __('Print GRN') }}" aria-label="{{ __('Print GRN Document') }}">
    <i class="ti ti-printer"></i>
</a>
@endpermission

@permission('purchase-invoice create')
@if($grn->hasInvoice())
<a href="{{ route('purchase-invoice.show', $grn->getInvoice()->id) }}" class="btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-original-title="{{ __('View Invoice') }}" title="{{ __('View Invoice') }}" aria-label="{{ __('View Purchase Invoice') }}">
    <i class="ti ti-file-invoice"></i>
</a>

@else
<button class="btn btn-sm btn-warning create-invoice" data-id="{{ $grn->id }}" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create Invoice from GRN') }}" title="{{ __('Create Invoice from GRN') }}" aria-label="{{ __('Create Purchase Invoice') }}">
    <i class="ti ti-file-invoice"></i>
</button>
@endif
@endpermission

@permission('grn delete')
@if(!$grn->hasInvoice() && !$grn->is_locked)
<form method="POST" action="{{ route('grn.destroy', $grn->id) }}" style="display:inline-block;">
    @csrf
    @method('DELETE')
    <button type="button" class="btn btn-sm btn-danger confirm-delete" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Delete GRN') }}" title="{{ __('Delete GRN') }}" aria-label="{{ __('Delete GRN') }}">
        <i class="ti ti-trash"></i>
    </button>
</form>
@endif
@endpermission
