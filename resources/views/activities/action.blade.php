 @permission('activity show') 
 <div class="action-btn me-2">
    <a href="{{ route('activities.show', $activity->id) }}" class="mx-3 btn btn-sm align-items-center bg-warning" 
        data-bs-toggle="tooltip" title="{{ __('View') }}">
        <i class="ti ti-eye text-white"></i>
    </a>
</div> 
 @endpermission 

 @permission('activity edit') 
<div class="action-btn me-2">
    <a href="{{ route('activities.edit', $activity->id) }}" class="mx-3 btn btn-sm align-items-center bg-info" 
       data-bs-toggle="tooltip" title="{{ __('Edit') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>
 @endpermission 

 @permission('activity delete') 
<div class="action-btn">
    {!! Form::open([
        'method' => 'DELETE',
        'route' => ['activities.destroy', $activity->id],
        'id' => 'delete-form-' . $activity->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger" 
       data-bs-toggle="tooltip" title="{{ __('Delete') }}">
        <i class="ti ti-trash text-white"></i>
    </a>
    {!! Form::close() !!}
</div>
 @endpermission 
