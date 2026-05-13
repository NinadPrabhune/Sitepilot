
@permission('material-return show')
<div class="action-btn me-2">
    <a href="{{ route('material-returns.show', $return->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
</div>
@endpermission

@permission('material-return edit')
<!--<div class="action-btn me-2">
    <a href="{{ route('material-returns.edit', $return->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-primary"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>-->
@endpermission

@permission('material-return delete')
<!--<div class="action-btn me-2">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['material-returns.destroy', $return->id],
    'id' => 'delete-form-' . $return->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center btn-danger show_confirm" data-bs-toggle="tooltip"
       data-bs-original-title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
    {!! Form::close() !!}
</div>-->
@endpermission
