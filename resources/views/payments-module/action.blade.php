@permission('manage-payment show')
<div class="action-btn me-2">
    <a href="{{ route('payments-module.show', $paymentsModule->id) }}"
       class="mx-3 btn btn-sm align-items-center btn-warning"
       data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye text-white"></i></a>
</div>
@endpermission
@permission('manage-payment show')
@if($paymentsModule->payment_pdf)
    <div class="action-btn me-2">
        {{-- Check if path is relative or full URL --}}
        @if(str_starts_with($paymentsModule->payment_pdf, 'http'))
            {{-- For external URLs, show View PDF button --}}
            <a href="{{ $paymentsModule->payment_pdf }}" target="_blank"
               class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('View PDF') }}">
                <i class="ti ti-printer text-white"></i>
            </a>
        @elseif(file_exists(public_path($paymentsModule->payment_pdf)))
            {{-- For relative paths, check if file exists --}}
            <a href="{{ asset($paymentsModule->payment_pdf) }}" target="_blank"
               class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('View PDF') }}">
                <i class="ti ti-printer text-white"></i>
            </a>
        @else
            {{-- File doesn't exist, show Generate PDF button --}}
            <a href="{{ route('payments-module.generate-pdf', $paymentsModule->id) }}"
               class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Generate PDF') }}">
                <i class="ti ti-file-download text-white"></i>
            </a>
        @endif
    </div>
@else
    <div class="action-btn me-2">
        <a href="{{ route('payments-module.generate-pdf', $paymentsModule->id) }}"
           class="mx-3 btn btn-sm align-items-center btn-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Generate PDF') }}">
            <i class="ti ti-file-download text-white"></i>
        </a>
    </div>
@endif
@endpermission
@permission('manage-payment edit')
<!--<div class="action-btn me-2">
    <a class="mx-3 btn btn-sm  align-items-center btn-primary" data-url="{{ route('payments-module.edit', $paymentsModule->id) }}"
       data-ajax-popup="true" data-size="lg " data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"
       data-title="{{ __('Edit Payment') }}">
        <i class="ti ti-pencil text-white"></i>
    </a>
</div>-->
@endpermission
@permission('manage-payment delete')
<!--<div class="action-btn me-2">
    {!! Form::open([
    'method' => 'DELETE',
    'route' => ['payments-module.destroy', $paymentsModule->id],
    'id' => 'delete-form-' . $paymentsModule->id,
    ]) !!}
    <a href="#" class="mx-3 btn btn-sm  align-items-center show_confirm btn-danger" data-bs-toggle="tooltip"
       data-bs-original-title="{{ __('Delete') }}"><i class="ti ti-trash text-white"></i></a>
    {!! Form::close() !!}
</div>-->
@endpermission
