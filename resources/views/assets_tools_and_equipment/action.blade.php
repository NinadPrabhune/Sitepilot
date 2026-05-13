@permission('tools-and-equipment show')
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center bg-warning"
           data-url="{{ route('assets_tools_and_equipment.show', $item->id) }}"
           data-ajax-popup="true"
           data-size="lg"
           data-bs-toggle="tooltip"
           title="{{ __('View') }}"
           data-title="{{ __('View Tools & Equipment') }}">
            <i class="ti ti-eye text-white"></i>
        </a>
    </div>
@endpermission
@permission('tools-and-equipment edit')
    <div class="action-btn me-2">
        <a class="mx-3 btn btn-sm align-items-center bg-info"
           data-url="{{ route('assets_tools_and_equipment.edit', $item->id) }}"
           data-ajax-popup="true"
           data-size="lg"
           data-bs-toggle="tooltip"
           title="{{ __('Edit') }}"
           data-title="{{ __('Edit Tools & Equipment') }}">
            <i class="ti ti-pencil text-white"></i>
        </a>
    </div>
@endpermission
@permission('tools-and-equipment delete')
    <div class="action-btn">
        {!! Form::open([
            'method' => 'DELETE',
            'route' => ['assets_tools_and_equipment.destroy', $item->id],
            'id' => 'delete-form-' . $item->id,
        ]) !!}
         @if($item->quantity != 0)
        <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para show_confirm bg-danger" data-bs-toggle="tooltip"
            title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
         @endif
        {!! Form::close() !!}
    </div>
@endpermission


@permission('tools-and-equipment transfer')
<div class="action-btn me-2 ms-2">
     @if($item->quantity != 0)
    <a class="mx-3 btn btn-sm align-items-center bg-success"
       data-url="{{ route('general_transfer.create', ['transfer_type' => 'tools_and_equipment', 'tools_and_equipment_id' => $item->id]) }}"
       data-ajax-popup="true"
       data-size="xl"
       data-bs-toggle="tooltip"
       title="{{ __('Create Tools and Equipment Transfer') }}"
       data-title="{{ __('New Tools and Equipment Transfer') }}">
        <i class="ti ti-arrows-left-right text-white"></i>
    </a>
      @endif
</div>
@endpermission
