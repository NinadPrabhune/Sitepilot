{{-- ✅ New Actions --}}
@permission('stock-report add')
    <div class="action-btn me-2">
        <a href="{{ route('purchase-invoice.index') }}"
           class="mx-3 btn btn-sm align-items-center bg-primary"
           data-bs-toggle="tooltip" title="{{ __('Add Stock') }}">
            <i class="ti ti-plus text-white"></i>
        </a>
    </div>
@endpermission

{{-- Only show if available qty > 0 --}}
@if(!empty($row->total_qty) && $row->total_qty > 0)
    @permission('stock-report consume')
        <div class="action-btn me-2">
            <a href="{{ route('daily-consumption.index') }}"
               class="mx-3 btn btn-sm align-items-center bg-secondary"
               data-bs-toggle="tooltip" title="{{ __('Consume Stock') }}">
                <i class="ti ti-minus text-white"></i>
            </a>
        </div>
    @endpermission

    @permission('stock-report transfer')
        <div class="action-btn me-2">
            <a class="mx-3 btn btn-sm align-items-center bg-success"
               data-ajax-popup="true"
               data-size="lg"
               data-title="{{ __('Transfer Stock') }}"
               data-url="{{ route('material-transfer.create', ['material_id' => $row->material_id]) }}"
               data-bs-toggle="tooltip" title="{{ __('Transfer Stock') }}">
                <i class="ti ti-arrows-left-right text-white"></i>
            </a>
        </div>
    @endpermission
@endif
