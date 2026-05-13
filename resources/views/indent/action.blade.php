
@permission('indent show')
<div class="action-btn me-2">
    <a href="{{ route('indent.show', $indent->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
</div>
@endpermission

@permission('indent edit')
@if($indent->status === 'Open' && !$indent->purchaseOrders()->exists())
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center btn-primary" data-url="{{ route('indent.edit', $indent->id) }}"
       data-ajax-popup="true" data-size="xxl" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"
       data-title="{{ __('Edit Indent') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>
@endif
@endpermission

@permission('indent delete')
@if(!$indent->purchaseOrders()->exists())
<div class="action-btn me-2">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['indent.destroy', $indent->id],
    'id' => 'delete-form-' . $indent->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center btn-danger show_confirm" data-bs-toggle="tooltip"
       data-bs-original-title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
    {!! Form::close() !!}
</div>
@endif
@endpermission
