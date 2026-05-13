
@permission('supplier show')
<div class="action-btn me-2">
    <a href="{{ route('supplier.show', $supplier->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
</div>
@endpermission
@permission('supplier edit')
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm  align-items-center btn-primary" data-url="{{ route('supplier.edit', $supplier->id) }}"
       data-ajax-popup="true" data-size="lg " data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"
       data-title="{{ __('Edit Supplier') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>
@endpermission
@permission('supplier delete')
<div class="action-btn me-2">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['supplier.destroy', $supplier->id],
    'id' => 'delete-form-' . $supplier->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm  align-items-center show_confirm btn-danger" data-bs-toggle="tooltip"
       data-bs-original-title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
    {!! Form::close() !!}
</div>
@endpermission
