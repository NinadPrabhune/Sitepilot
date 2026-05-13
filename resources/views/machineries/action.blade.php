@permission('machinery show')
    <div class="action-btn me-2">
        <a href="{{ route('machineries.show', $machinery->id) }}"
            class="mx-3 btn btn-sm align-items-center bg-warning"
            data-bs-toggle="tooltip" title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
    </div>
@endpermission
@permission('machinery edit')
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm  align-items-center bg-info" data-url="{{ route('machineries.edit', $machinery->id) }}"
            data-ajax-popup="true" data-size="xl " data-bs-toggle="tooltip" title="{{ __('Edit') }}"
            data-title="{{ __('Edit Machinery') }}">
            <i class="ti ti-pencil text-white"></i>
        </a>
    </div>
@endpermission


@permission('machinery transfer')
<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-success"
       data-url="{{ route('general_transfer.create', ['transfer_type' => 'machinery', 'machinery_id' => $machinery->id]) }}"
       data-ajax-popup="true"
       data-size="xl"
       data-bs-toggle="tooltip"
       title="{{ __('Create Machinery Transfer') }}"
       data-title="{{ __('New Machinery Transfer') }}">
        <i class="ti ti-arrows-left-right text-white"></i>
    </a>
</div>
@endpermission

@permission('machinery-dpr create')
<!--<div class="action-btn me-2">
    <a href="{{ route('machinery.dpr.create', $machinery) }}"
       class="mx-3 btn btn-sm align-items-center bg-primary"
       data-bs-toggle="tooltip"
       title="{{ __('Create DPR') }}">
        <i class="ti ti-file-plus text-white"></i>
    </a>
</div>-->
@endpermission 

@permission('machinery delete')
    <div class="action-btn me-2">
        {!! Form::open([
            'method' => 'DELETE',
            'route' => ['machineries.destroy', $machinery->id],
            'id' => 'delete-form-' . $machinery->id,
        ]) !!}
        <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para show_confirm bg-danger" data-bs-toggle="tooltip"
            title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
        {!! Form::close() !!}
    </div>
@endpermission
