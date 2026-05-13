{{ Form::open(['route' => 'payments-module.store', 'class' => 'needs-validation', 'novalidate', 'files' => true]) }}
<div class="modal-body">
   

    <div class="row" id="payment-div">
        {{-- Payment Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_number', __('Payment Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('payment_number', $nextPaymentNumber, [
                'class' => 'form-control',
                'required' => true,
                'readonly' => true,
                'placeholder' => 'Auto-generated'
            ]) }}
        </div>

        {{-- Payment Date --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_date', __('Payment Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('payment_date', \Carbon\Carbon::now()->format('Y-m-d'), [
                'class' => 'form-control',
                'required' => true
            ]) }}
        </div>

        {{-- Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ optional($selectedInvoice->site)->name }}" readonly>
            {{ Form::hidden('site_id', $selectedInvoice->site_id) }}
        </div>

        {{-- Payment Type --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_type', __('Payment Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('payment_type', [
                'against_invoice' => 'Against Invoice'
            ], 'against_invoice', [
                'class' => 'form-control',
                'required' => true
            ]) }}
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-6">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ optional($selectedInvoice->supplier)->name }}" readonly>
            {{ Form::hidden('supplier_id', $selectedInvoice->supplier_id) }}
        </div>

        {{-- Invoice --}}
        <div class="form-group col-md-6">
            {{ Form::label('purchase_invoice_id', __('Purchase Invoice'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ $selectedInvoice->invoice_number }}" readonly>
            {{ Form::hidden('purchase_invoice_id', $selectedInvoiceId) }}
            {{ Form::hidden('purchase_order_id', $selectedInvoice->po_id) }}
        </div>

        {{-- Remaining Amount --}}
        @php
            $totalPaid = optional($selectedInvoice)->payments->sum('amount') ?? 0;
            $balance   = optional($selectedInvoice)->total_amount - $totalPaid;
        @endphp
        <div class="form-group col-md-6" id="remaining_amount_group">
            {{ Form::label('remaining_amount', __('Remaining Amount'), ['class' => 'form-label']) }}
            <input type="text" id="remaining_amount" name="remaining_amount"
                   class="form-control" readonly value="{{ $selectedInvoice ? $balance : 0 }}">
        </div>

        {{-- Amount --}}
        <div class="form-group col-md-6">
            {{ Form::label('amount', __('Amount'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('amount', $selectedInvoice ? $balance : null, [
                'class' => 'form-control',
                'step' => '1.00',
                'required' => true,
                'placeholder' => __('Enter Amount')
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
            {{ Form::label('reference_number', __('Reference Number'), ['class' => 'form-label']) }}
            {{ Form::text('reference_number', null, [
                'class' => 'form-control',
                'placeholder' => __('Enter Reference Number')
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
            {{ Form::label('payment_proff_file', __('Payment Proof File'), ['class' => 'form-label']) }}
            {{ Form::file('payment_proff_file', ['class' => 'form-control']) }}
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}

<script src="{{ asset('js/jquery-ui.min.js') }}"></script>

<script>
$(document).ready(function () {
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
        checkNotNegative('amount');
        let type = $('#payment_type').val();
        if (type === 'against_invoice') {
            let enteredAmount = parseFloat($(this).val());
            let remainingAmount = parseFloat($('#remaining_amount').val());
            if (enteredAmount > remainingAmount) {
                toastrs('Error', 'Amount cannot be greater than Remaining Amount', 'error');
                $(this).val(remainingAmount);
            }
        }
    });

//    $('#ac_payment_status').on('change', function () {
//        if ($(this).val() === 'rejected') {
//            $('#rejection_reason_group').removeClass('d-none');
//            $('#rejection_reason').attr('required', true);
//            $('#payment-div').addClass('d-none');
//        } else {
//            $('#rejection_reason_group').addClass('d-none');
//            $('#rejection_reason').removeAttr('required').val('');
//            $('#payment-div').removeClass('d-none');
//        }
//    });
});
</script>
