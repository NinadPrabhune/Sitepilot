{{ Form::open(['route' => 'payments-module.store', 'class' => 'needs-validation', 'novalidate', 'files' => true]) }}
<div class="modal-body">
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

        {{-- Site --}}
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ optional($selectedPo->site)->name }}" readonly>
            {{ Form::hidden('site_id', $selectedPo->site_id) }}
        </div>

        {{-- Payment Type --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_type', __('Payment Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('payment_type', [
                'advance_against_po' => __('Advance Against PO')
            ], 'advance_against_po', [
                'class' => 'form-control',
                'required' => true
            ]) }}
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-4">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ optional($selectedPo->supplier)->name }}" readonly>
            {{ Form::hidden('supplier_id', $selectedPo->supplier_id) }}
        </div>

        {{-- PO Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('purchase_order_id', __('Purchase Order'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ $selectedPo->po_number }}" readonly>
            {{ Form::hidden('purchase_order_id', $selectedPo->id) }}
        </div>
    </div>

    {{-- Live Summary Card --}}
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col">
                            <small>{{ __('PO Total') }}</small>
                            <h5 class="mb-0" id="summary_po_total">{{ currency_format_with_sym($selectedPo->grand_total) }}</h5>
                        </div>
                        <div class="col">
                            <small>{{ __('Invoiced') }}</small>
                            <h5 class="mb-0" id="summary_invoiced">{{ currency_format_with_sym($selectedPo->invoiced_amount ?? 0) }}</h5>
                        </div>
                        <div class="col">
                            <small>{{ __('Paid') }}</small>
                            <h5 class="mb-0" id="summary_paid">{{ currency_format_with_sym($selectedPo->paid_amount ?? 0) }}</h5>
                        </div>
                        <div class="col">
                            <small>{{ __('Advance Balance') }}</small>
                            <h5 class="mb-0" id="summary_advance_balance">{{ currency_format_with_sym(($selectedPo->grand_total) - ($selectedPo->paid_amount ?? 0)) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ledger / A/c Statement Section --}}
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Ledger / A/c Statement') }}</h5>
                </div>
                <div class="card-body table-responsive">
                    @if(count($ledgerEntries) > 0)
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Date & Time') }}</th>
                                <th>{{ __('Details') }}</th>
                                <th class="text-end">{{ __('Dr. (Debit)') }}</th>
                                <th class="text-end">{{ __('Cr. (Credit)') }}</th>
                                <th class="text-end">{{ __('Running Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ledgerEntries as $entry)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($entry['datetime'])->format('d-m-Y H:i') }}</td>
                                <td>{{ $entry['details'] }}</td>
                                <td class="text-end">
                                    @if($entry['debit'] > 0)
                                    {{ currency_format_with_sym($entry['debit']) }}
                                    @else
                                    -
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($entry['credit'] > 0)
                                    {{ currency_format_with_sym($entry['credit']) }}
                                    @else
                                    -
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ currency_format_with_sym($entry['running_balance']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <p class="text-muted text-center">{{ __('No transactions yet') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        {{-- Amount --}}
        <div class="form-group col-md-6">
            {{ Form::label('amount', __('Advance Amount'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('amount', null, [
                'class' => 'form-control',
                'step' => '0.01',
                'required' => true,
                'placeholder' => __('Enter Advance Amount'),
                'max' => $selectedPo->grand_total
            ]) }}
            <small class="text-muted">{{ __('Maximum: ') }} {{ currency_format_with_sym($selectedPo->grand_total) }}</small>
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
    <input type="submit" value="{{ __('Create Payment') }}" class="btn btn-primary">
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
        let enteredAmount = parseFloat($(this).val());
        let maxAmount = parseFloat($(this).attr('max'));
        
        if (enteredAmount > maxAmount) {
            toastrs('Error', 'Amount cannot be greater than PO Amount', 'error');
            $(this).val(maxAmount);
        }
    });
});
</script>