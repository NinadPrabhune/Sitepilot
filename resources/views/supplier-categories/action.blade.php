@permission('supplier-category show')
<!--    <div class="action-btn me-2">
        <a href="{{ route('units.show', $SupplierCategory->id) }}"
            class="mx-3 btn btn-sm align-items-center bg-warning"
            data-bs-toggle="tooltip" title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
    </div>-->
@endpermission
@permission('supplier-category edit')
    @if($SupplierCategory->id != 1)
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm  align-items-center bg-info" data-url="{{ route('supplier-categories.edit', $SupplierCategory->id) }}"
            data-ajax-popup="true" data-size="lg " data-bs-toggle="tooltip" title="{{ __('Edit') }}"
            data-title="{{ __('Edit Supplier Category') }}">
            <i class="ti ti-pencil text-white"></i>
        </a>
    </div>
    @endif
@endpermission
@permission('supplier-category delete')
    @if($SupplierCategory->id != 1)
    <div class="action-btn">
        {!! Form::open([
            'method' => 'DELETE',
            'route' => ['supplier-categories.destroy', $SupplierCategory->id],
            'id' => 'delete-form-' . $SupplierCategory->id,
        ]) !!}
        <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para show_confirm bg-danger" data-bs-toggle="tooltip"
            title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
        {!! Form::close() !!}
    </div>
    @endif
@endpermission
