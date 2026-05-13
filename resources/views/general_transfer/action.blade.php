{{-- Machinery Actions --}}
@if($transfer->transfer_type === 'machinery' && $transfer->machinery)
<!--    <div class="action-btn me-2">
        <a href="{{ route('machineries.show', $transfer->machinery->id) }}"
           class="mx-3 btn btn-sm align-items-center bg-warning"
           data-bs-toggle="tooltip" title="{{ __('View') }}">
            <i class="ti ti-eye text-white"></i>
        </a>
    </div>-->
@endif


{{-- Tools & Equipment Actions --}}
@if($transfer->transfer_type === 'tools_and_equipment' && $transfer->toolsAndEquipment)
<!--    <div class="action-btn me-2">
        <a href="{{ route('assets_tools_and_equipment.show', $transfer->toolsAndEquipment->id) }}"
           class="mx-3 btn btn-sm align-items-center bg-warning"
           data-bs-toggle="tooltip" title="{{ __('View') }}">
            <i class="ti ti-eye text-white"></i>
        </a>
    </div>-->
@endif

{{-- Employee Actions --}}
@if($transfer->transfer_type === 'employee' && $transfer->employee)
<!--    <div class="action-btn me-2">
        <a href="{{ route('employee.show', $transfer->employee->id) }}"
           class="mx-3 btn btn-sm align-items-center bg-warning"
           data-bs-toggle="tooltip" title="{{ __('View') }}">
            <i class="ti ti-eye text-white"></i>
        </a>
    </div>-->
@endif


{{-- @permission('general_transfer delete') --}}
<div class="action-btn">
    {!! Form::open([
        'method' => 'DELETE',
        'route' => ['general_transfer.destroy', $transfer->id],
        'id' => 'delete-form-' . $transfer->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
       data-bs-toggle="tooltip" title="{{ __('Delete Transfer') }}">
        <i class="ti ti-trash text-white"></i>
    </a>
    {!! Form::close() !!}
</div>
{{-- @endpermission --}}


