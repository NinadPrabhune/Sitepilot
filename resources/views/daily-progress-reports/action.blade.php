{{-- View --}}
@permission('machinery-dpr show')
<div class="action-btn me-2">
    <a href="{{ route('daily-progress-reports.show', $item->id) }}"
       class="mx-3 btn btn-sm align-items-center bg-warning"
       data-bs-toggle="tooltip"
       title="{{ __('View Daily Report') }}">
        <i class="ti ti-eye text-white"></i>
    </a>
</div>
@endpermission

{{-- Edit --}}
@permission('machinery-dpr edit')
<!--<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm align-items-center bg-info"
       data-url="{{ route('daily-progress-reports.edit', $item->id) }}"
       data-ajax-popup="true"
       data-size="xl"
       data-bs-toggle="tooltip"
       title="{{ __('Edit Daily Report') }}"
       data-title="{{ __('Edit Daily Progress Report') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>-->
@endpermission

{{-- Delete --}}
@permission('machinery-dpr delete')
<!--<div class="action-btn">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['daily-progress-reports.destroy', $item->id],
    'id' => 'delete-form-' . $item->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
       data-bs-toggle="tooltip"
       title="{{ __('Delete Daily Report') }}">
        <i class="ti ti-trash text-white"></i>
    </a>
    {!! Form::close() !!}
</div>-->
@endpermission
