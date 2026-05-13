@permission('consumption-log show')
    <div class="action-btn me-2">
        <a href="{{ route('daily-consumption.show', $master->id) }}"
           class="mx-3 btn btn-sm align-items-center bg-warning"
           data-bs-toggle="tooltip" title="{{ __('View') }}">
            <i class="ti ti-eye text-white"></i>
        </a>
    </div>
@endpermission

@permission('consumption-log edit')
<!--    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center bg-info"
           data-url="{{ route('daily-consumption.edit', $master->id) }}"
           data-ajax-popup="true" data-size="xl"
           data-bs-toggle="tooltip" title="{{ __('Edit') }}"
           data-title="{{ __('Edit Daily Consumption') }}">
            <i class="ti ti-pencil text-white"></i>
        </a>
    </div>-->
@endpermission

@permission('consumption-log delete')
<!--    <div class="action-btn">
        {!! Form::open([
            'method' => 'DELETE',
            'route' => ['daily-consumption.destroy', $master->id],
            'id' => 'delete-form-' . $master->id,
        ]) !!}
        <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
           data-bs-toggle="tooltip" title="{{ __('Delete') }}">
            <i class="ti ti-trash text-white"></i>
        </a>
        {!! Form::close() !!}
    </div>-->
@endpermission
