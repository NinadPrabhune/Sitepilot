@permission('spent edit')
<a data-size="xl" data-url="{{ route('spent.edit', $spent->id) }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Edit')}}" data-title="{{__('Edit Spent')}}" class="btn btn-sm btn-primary">
    <i class="ti ti-pencil"></i>
</a>
@endpermission

@permission('spent delete')
<a href="{{ route('spent.destroy', $spent->id) }}" data-confirm="{{__('Are you sure?')}}" data-confirm-yes="{{__('Yes')}}" data-confirm-no="{{__('No')}}" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="{{__('Delete')}}">
    <i class="ti ti-trash"></i>
</a>
@endpermission
