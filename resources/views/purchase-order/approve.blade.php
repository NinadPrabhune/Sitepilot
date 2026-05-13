{{ Form::open(['method' => 'PATCH', 'route' => ['purchase-order.update-status', $purchaseOrder->id], 'class' => 'needs-validation', 'novalidate', 'id' => 'po-status-form']) }}

<div class="modal-body">
    <div class="row">
        <div class="col-12">
            <div class="form-group">
                <label class="form-label">{{ __('Current Status') }}</label>
                @php
                    $statusColors = [
                        'Draft' => 'secondary',
                        'Approved' => 'primary',
                        'Partial Received' => 'warning',
                        'Completed' => 'success',
                        'Rejected' => 'danger',
                        'Flagged' => 'info',
                        'Short Closed' => 'dark'
                    ];
                    $color = $statusColors[$purchaseOrder->display_status] ?? 'secondary';
                @endphp
                <span class="badge bg-{{ $color }} ms-2">
                    {{ __($purchaseOrder->display_status) }}
                </span>
            </div>
        </div>
        
        <div class="col-12">
            <div class="form-group">
                <label class="form-label">{{ __('New Status') }} <span class="text-danger">*</span></label>
                @php
                    $statusOptions = ['' => __('Select Status')];
                    foreach ($allowedTransitions as $status) {
                        $statusOptions[$status] = __($status);
                    }
                @endphp
                {{ Form::select('status', $statusOptions, null, ['class' => 'form-control', 'id' => 'status-select', 'required' => true]) }}
            </div>
        </div>
        
        <!-- Reason textarea -->
        <div class="col-12" id="reason-div" style="display: none;">
            <div class="form-group">
                <label class="form-label" id="reason-label">{{ __('Reason') }} <span class="text-danger">*</span></label>
                {{ Form::textarea('reason', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter reason'), 'id' => 'reason-textarea']) }}
            </div>
        </div>
        
        <div class="col-12">
            <div class="alert alert-info" id="status-message">
                <i class="ti ti-info-circle"></i>
                {{ __('Select the new status for this Purchase Order.') }}
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    {{ Form::button(__('Cancel'), ['class' => 'btn btn-light', 'data-bs-dismiss' => 'modal']) }}
    {{ Form::button(__('Update Status'), ['class' => 'btn btn-primary', 'type' => 'submit', 'id' => 'submit-btn']) }}
</div>

{{ Form::close() }}

<script>
$(document).ready(function() {
    $('#status-select').change(function() {
        var status = $(this).val();
        
        // Hide reason div initially
        $('#reason-div').hide();
        $('#reason-textarea').removeAttr('required');
        
        if (status === 'Rejected') {
            $('#reason-div').show();
            $('#reason-label').text('{{ __("Rejection Reason") }}');
            $('#reason-textarea').attr('placeholder', '{{ __("Enter rejection reason") }}');
            $('#reason-textarea').attr('required', true);
            $('#status-message').html('<i class="ti ti-info-circle"></i> {{ __("Once rejected, this PO cannot be edited and no GRN can be created.") }}');
            $('#submit-btn').removeClass('btn-success').addClass('btn-danger');
        } else if (status === 'Flagged') {
            $('#reason-div').show();
            $('#reason-label').text('{{ __("Flag Reason") }}');
            $('#reason-textarea').attr('placeholder', '{{ __("Enter flag reason") }}');
            $('#reason-textarea').attr('required', true);
            $('#status-message').html('<i class="ti ti-info-circle"></i> {{ __("Once flagged, this PO becomes editable. You can correct it and move back to Approved.") }}');
            $('#submit-btn').removeClass('btn-danger').addClass('btn-info');
        } else if (status === 'Short Closed') {
            $('#reason-div').show();
            $('#reason-label').text('{{ __("Short Close Reason") }}');
            $('#reason-textarea').attr('placeholder', '{{ __("Enter short close reason") }}');
            $('#reason-textarea').attr('required', true);
            $('#status-message').html('<i class="ti ti-info-circle"></i> {{ __("Once short closed, this PO cannot receive any more goods. No GRN can be created.") }}');
            $('#submit-btn').removeClass('btn-danger btn-info').addClass('btn-dark');
        } else if (status === 'Approved') {
            $('#status-message').html('<i class="ti ti-info-circle"></i> {{ __("Once approved, GRN can be created for this Purchase Order.") }}');
            $('#submit-btn').removeClass('btn-danger btn-info btn-dark').addClass('btn-success');
        } else {
            $('#status-message').html('<i class="ti ti-info-circle"></i> {{ __("Select the new status for this Purchase Order.") }}');
            $('#submit-btn').removeClass('btn-danger btn-info btn-dark').addClass('btn-primary');
        }
    });
    
    // Trigger change on load to set initial state
    $('#status-select').trigger('change');
});
</script>
