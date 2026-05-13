{{ Form::open(['route' => 'payments-module.store', 'class' => 'needs-validation', 'novalidate', 'files' => true]) }}
<div class="modal-body">
    {{-- Payment Request Summary Card (Primary Data Source) --}}
    <div class="alert alert-info mb-3">
        <div class="d-flex align-items-start">
            <i class="ti ti-file-description me-2 fs-4"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading mb-2">Payment Request Details</h6>
                <div class="row g-2">
                    @if($invoice)
                        <div class="col-md-6">
                            <strong>Supplier:</strong> {{ optional($invoice->supplier)->name }}
                        </div>
                        <div class="col-md-6">
                            <strong>Invoice No:</strong> {{ $invoice->invoice_number }}
                        </div>
                    @elseif($po)
                        <div class="col-md-6">
                            <strong>Supplier:</strong> {{ optional($po->supplier)->name }}
                        </div>
                        <div class="col-md-6">
                            <strong>PO No:</strong> {{ $po->po_number }}
                        </div>
                    @endif
                    <div class="col-md-6">
                        <strong>Type:</strong> {{ $paymentRequest->type === 'po_advance' ? 'PO Advance' : 'Invoice Payment' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Requested Amount:</strong> ₹{{ number_format($paymentRequest->requested_amount, 2) }}
                    </div>
                    <div class="col-md-6">
                        <strong>Approved Amount:</strong> ₹{{ number_format($paymentRequest->approved_amount ?? $paymentRequest->requested_amount, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" id="payment-div">
        {{-- Payment Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_number', __('Payment Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('payment_number', $nextPaymentNumber, [
                'class' => 'form-control',
                'required' => true,
                'readonly' => true,
                'placeholder' => 'Auto-generated'
            ]) }}
        </div>

        {{-- Payment Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_date', __('Payment Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('payment_date', \Carbon\Carbon::now()->format('Y-m-d'), [
                'class' => 'form-control',
                'required' => true
            ]) }}
        </div>

        {{-- Payment Type (Hidden - determined by payment request type) --}}
        @if($invoice)
            {{ Form::hidden('payment_type', 'against_invoice') }}
        @elseif($po)
            {{ Form::hidden('payment_type', 'advance_against_po') }}
        @endif

        {{-- Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ optional($invoice?->site ?? $po?->site)->name }}" readonly>
            {{ Form::hidden('site_id', $invoice?->site_id ?? $po?->site_id) }}
        </div>

       

        {{-- Supplier --}}
        <div class="form-group col-md-6">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ optional($invoice?->supplier ?? $po?->supplier)->name }}" readonly>
            {{ Form::hidden('supplier_id', $invoice?->supplier_id ?? $po?->supplier_id) }}
        </div>

        {{-- Invoice or PO --}}
        @if($invoice)
        <div class="form-group col-md-4">
            {{ Form::label('purchase_invoice_id', __('Purchase Invoice'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ $invoice->invoice_number }}" readonly>
            {{ Form::hidden('purchase_invoice_id', $invoice->id) }}
            {{ Form::hidden('purchase_order_id', $invoice->po_id) }}
        </div>
        @elseif($po)
        <div class="form-group col-md-4">
            {{ Form::label('purchase_order_id', __('Purchase Order'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ $po->po_number }}" readonly>
            {{ Form::hidden('purchase_order_id', $po->id) }}
        </div>
        @endif

        {{-- Payment Request Info --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_request_id', __('Payment Request'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="#{{ $paymentRequest->id }} - {{ $paymentRequest->status }}" readonly>
            {{ Form::hidden('payment_request_id', $paymentRequest->id) }}
        </div>

        {{-- Remaining Amount (Net Payable) --}}
        <div class="form-group col-md-4" id="remaining_amount_group">
            {{ Form::label('remaining_amount', $invoice ? __('Remaining Invoice Balance') : __('Remaining PO Liability'), ['class' => 'form-label']) }}
            <input type="text" id="remaining_amount" name="remaining_amount"
                   class="form-control" readonly value="{{ number_format($netPayable, 2) }}">
        </div>

        {{-- Remaining Approved Amount for this Request --}}
        <div class="form-group col-md-6">
            {{ Form::label('remaining_approved', __('Remaining Approved (this request only)'), ['class' => 'form-label']) }}
            <input type="text" id="remaining_approved" name="remaining_approved"
                   class="form-control" readonly value="{{ number_format($remainingApproved, 2) }}">
        </div>

        {{-- Warning when approved amount exhausted --}}
        @if($remainingApproved <= 0 && $netPayable > 0)
        <div class="col-md-12">
            <div class="alert alert-warning">
                <strong>Approved amount exhausted for this request.</strong><br>
                @if($invoice)
                    Remaining invoice balance (₹{{ number_format($netPayable, 2) }}) requires a new Payment Request.
                    <a href="{{ route('payment-request.create-modal', $invoice->id) }}" class="btn btn-sm btn-primary ms-2" data-url="{{ route('payment-request.create-modal', $invoice->id) }}" data-ajax-popup="true" data-size="xl" data-title="{{ __('Create Payment Request') }}">
                        Create New Request
                    </a>
                @else
                    Remaining PO liability (₹{{ number_format($netPayable, 2) }}) requires a new Payment Request.
                @endif
            </div>
        </div>
        @endif

        {{-- Amount --}}
        <div class="form-group col-md-6">
            {{ Form::label('amount', __('Amount'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('amount', max(0, min($remainingApproved, $netPayable)), [
                'class' => 'form-control',
                'step' => '1.00',
                'required' => true,
                'readonly' => true,
                'placeholder' => __('Enter Amount'),
                'max' => $remainingApproved > 0 ? $remainingApproved : 0
            ]) }}

        </div>

        {{-- Mode --}}
        <div class="form-group col-md-6">
            {{ Form::label('mode', __('Payment Mode'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('mode', [
                'cash' => __('Cash'),
                'bank_transfer' => __('Bank Transfer'),
                'cheque' => __('Cheque'),
                'upi' => __('UPI')
            ], null, [
                'class' => 'form-control select2',
                'placeholder' => __('Select Payment Mode'),
                'required' => true
            ]) }}
        </div>

        {{-- Reference Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('reference_number', __('Reference Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('reference_number', null, [
                'class' => 'form-control',
                'placeholder' => __('Enter Reference Number'),
                'required' => true
            ]) }}
        </div>

        {{-- Notes --}}
        <div class="form-group col-md-6">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, [
                'class' => 'form-control',
                'rows' => 2,
                'placeholder' => __('Enter Notes')
            ]) }}
        </div>

        {{-- Payment Proof File --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_proff_file', __('Payment Proof File'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::file('payment_proff_file', ['class' => 'form-control', 'required' => true]) }}
        </div>
    </div>

    <div class="row g-2">
{{-- Financial Preview (Read-Only) --}}
<div class="alert alert-warning col-md-6">
    <div class="d-flex align-items-start">
        <i class="ti ti-lock me-2 fs-4"></i>
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-2">Financial Preview</h6>
            <div class="text-muted small mb-1">
                <i class="ti ti-info-circle me-1"></i>
                These values are calculated automatically and cannot be modified
            </div>
            <div>
                <span class="text-muted">{{ $invoice ? 'Invoice Balance' : 'PO Liability' }} Before Payment:</span>
                <strong> ₹{{ number_format($netPayable, 2) }}</strong><br>
                <span class="text-success">{{ $invoice ? 'Invoice Balance' : 'PO Liability' }} After Payment:</span>
                <strong> ₹{{ number_format(max(0, $netPayable - min($remainingApproved, $netPayable)), 2) }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- Hard Warning --}}
<div class="alert alert-danger col-md-6">
    <div class="d-flex align-items-center">
        <i class="ti ti-alert-triangle me-2 fs-4"></i>
        <div>
            <strong>Important:</strong> This payment is strictly linked to an approved Payment Request.
            Direct payments or modifications outside the approval workflow are not allowed.
        </div>
    </div>
</div>
</div>

<div class="row mt-3">
    <div class="col-12 text-end">
        <input type="button" value="{{ __('Cancel') }}" class="btn btn-light me-2" data-bs-dismiss="modal">
        <input type="submit" value="{{ __('Process Payment') }}" class="btn btn-success">
    </div>
</div>
</div>
{{ Form::close() }}

<script>
$(document).ready(function () {
    var remainingApproved = parseFloat($('#remaining_approved').val()) || 0;
    var amountValue = parseFloat($('#amount').val()) || 0;

    // Disable submit if no remaining approved amount AND amount is 0
    if (remainingApproved <= 0 && amountValue <= 0) {
        $('input[type="submit"]').prop('disabled', true).attr('title', 'Approved amount exhausted. Create new request.');
        $('input[type="submit"]').val('{{ __("Cannot Pay - Create New Request") }}');
    }

    function checkNotNegative(fieldId) {
        let value = parseFloat($('#' + fieldId).val());
        if (!isNaN(value) && value < 0) {
            toastrs('Error', 'Value cannot be negative', 'error');
            $('#' + fieldId).val(0);
            return false;
        }
        return true;
    }

    $('#amount').on('input', function() {
        var $input = $(this);
        checkNotNegative('amount');
        let enteredAmount = parseFloat($input.val());
        let remainingAmount = parseFloat($('#remaining_amount').val());
        let remainingApproved = parseFloat($('#remaining_approved').val());

        // Validate against payment request remaining approved amount FIRST
        if (enteredAmount > remainingApproved && remainingApproved > 0) {
            toastrs('Error', 'Amount cannot exceed remaining approved amount for this request', 'error');
            setTimeout(function() {
                $input.val(remainingApproved);
            }, 0);
            return;
        }

        // Validate against invoice remaining amount
        if (enteredAmount > remainingAmount) {
            toastrs('Error', 'Amount cannot be greater than Invoice Remaining Amount', 'error');
            setTimeout(function() {
                $input.val(remainingAmount);
            }, 0);
        }
    });

    // Form submission handler with success message
    $('form.needs-validation').on('submit', function(e) {
        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"]');

        // Check form validity before disabling button
        if (!$form[0].checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            $form.addClass('was-validated');
            return;
        }

        // Disable submit button to prevent double submission
        $submitBtn.prop('disabled', true).val('Processing...');

        // Let the form submit normally (server-side processing)
        // Success message will be handled by backend redirect
    });
});
</script>