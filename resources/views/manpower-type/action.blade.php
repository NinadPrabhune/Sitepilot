
{{--@permission('man-power-type show')--}}
<!--    <div class="action-btn me-2">
        <a href="{{ route('manpower-type.show', $manPowerType->id) }}"
            class="mx-3 btn btn-sm align-items-center bg-warning"
            data-bs-toggle="tooltip" title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
    </div>-->
{{--@endpermission--}}
@permission('man-power-type edit')
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm  align-items-center bg-info" data-url="{{ route('manpower-type.edit', $manPowerType->id) }}"
            data-ajax-popup="true" data-size="lg " data-bs-toggle="tooltip" title="{{ __('Edit') }}"
            data-title="{{ __('Edit Material') }}">
            <i class="ti ti-pencil text-white"></i>
        </a>
    </div>
@endpermission
@permission('man-power-type delete')
    <div class="action-btn">
        {!! Form::open([
            'method' => 'DELETE',
            'route' => ['manpower-type.destroy', $manPowerType->id],
            'id' => 'delete-form-' . $manPowerType->id,
        ]) !!}
        <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para show_confirm bg-danger" data-bs-toggle="tooltip"
            title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
        {!! Form::close() !!}
    </div>
@endpermission
