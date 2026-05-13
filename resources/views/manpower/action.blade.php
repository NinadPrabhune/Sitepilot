@permission('manpower show')

<div class="action-btn me-2">
    <a href="{{ route('manpower.show', $row->id) }}"
        class="mx-3 btn btn-sm align-items-center bg-warning"
        data-bs-toggle="tooltip" title="{{ __('View') }}">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>

@endpermission
@permission('manpower edit')
<!--<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-info"
        data-url="{{ route('manpower.edit', $row->id) }}"
        data-ajax-popup="true" data-size="lg"
        data-bs-toggle="tooltip" title="{{ __('Edit') }}"
        data-title="{{ __('Edit Manpower Record') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>-->
@endpermission

@permission('manpower delete')
<!--<div class="action-btn">
    {!! Form::open([
        'method' => 'DELETE',
        'route' => ['manpower.destroy', $row->id],
        'id' => 'delete-form-' . $row->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
        data-bs-toggle="tooltip" title="{{ __('Delete') }}">
        <i class="ti ti-trash text-white"></i>
    </a>
    {!! Form::close() !!}
</div>-->
@endpermission
