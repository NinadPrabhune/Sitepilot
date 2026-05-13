<div class="modal-body">
    <form id="machineryPaymentForm" class="needs-validation" novalidate>
        <div class="row">
            {{-- Payment Number --}}
            <div class="form-group col-md-6">
                {{ Form::label('payment_number', __('Payment Number'), ['class' => 'form-label']) }}
                <input type="text" class="form-control" value="MACH-{{ date('Y') }}-{{ str_pad($paymentRequest->id ?? 0, 6, '0', STR_PAD_LEFT) }}" readonly>
                <input type="hidden" name="payment_request_id" value="{{ $paymentRequest->id ?? 0 }}">
            </div>

            {{-- Payment Date --}}
            <div class="form-group col-md-6">
                {{ Form::label('payment_date', __('Payment Date'), ['class' => 'form-label']) }}<x-required></x-required>
                <input type="date" name="payment_date" class="form-control" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
            </div>

            {{-- Machinery --}}
            <div class="form-group col-md-6">
                {{ Form::label('machinery', __('Machinery'), ['class' => 'form-label']) }}
                <input type="text" class="form-control" value="{{ $paymentRequest->machinery->name ?? 'N/A' }}" readonly>
                <small class="text-muted">{{ $paymentRequest->machinery->code ?? '' }}</small>
            </div>

            {{-- Supplier --}}
            <div class="form-group col-md-6">
                {{ Form::label('supplier', __('Supplier'), ['class' => 'form-label']) }}
                <input type="text" class="form-control" value="{{ $paymentRequest->supplier->name ?? 'N/A' }}" readonly>
                <small class="text-muted">{{ $paymentRequest->supplier->code ?? '' }}</small>
            </div>

            {{-- Period --}}
            <div class="form-group col-md-6">
                {{ Form::label('period', __('Period'), ['class' => 'form-label']) }}
                <input type="text" class="form-control" value="{{ $paymentRequest->period_start ? \Carbon\Carbon::parse($paymentRequest->period_start)->format('d M Y') : '' }} {{ __('to') }} {{ $paymentRequest->period_end ? \Carbon\Carbon::parse($paymentRequest->period_end)->format('d M Y') : '' }}" readonly>
            </div>

            {{-- Net Payable --}}
            <div class="form-group col-md-6">
                {{ Form::label('net_payable', __('Net Payable'), ['class' => 'form-label']) }}
                <input type="text" class="form-control" value="{{ number_format($paymentRequest->net_payable ?? 0, 2) }}" readonly>
            </div>

            {{-- Amount --}}
            <div class="form-group col-md-6">
                {{ Form::label('amount', __('Amount'), ['class' => 'form-label']) }}<x-required></x-required>
                <?php 
                $totalPosted = $paymentRequest->payments()->sum('amount');
                $remainingBalance = $paymentRequest->net_payable - $totalPosted;
                ?>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="{{ $remainingBalance }}" value="{{ $remainingBalance }}" required placeholder="{{ __('Enter Amount') }}" id="machinery-payment-amount" onkeyup="if(parseFloat(this.value) > {{ $remainingBalance }}) { this.value = {{ $remainingBalance }}; toastrs('Error', 'Maximum amount is {{ number_format($remainingBalance, 2) }}', 'error'); }">
                <input type="hidden" id="remaining-balance" value="{{ $remainingBalance }}">
                <small class="text-muted">
                    {{ __('Net Payable') }}: {{ number_format($paymentRequest->net_payable, 2) }}<br>
                    {{ __('Already Paid') }}: {{ number_format($totalPosted, 2) }}<br>
                    {{ __('Remaining Balance') }}: {{ number_format($remainingBalance, 2) }}
                </small>
            </div>

            {{-- Payment Mode --}}
            <div class="form-group col-md-6">
                {{ Form::label('payment_mode', __('Payment Mode'), ['class' => 'form-label']) }}<x-required></x-required>
                <select name="payment_mode" class="form-control" required>
                    <option value="">{{ __('Select Payment Mode') }}</option>
                    <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                    <option value="cash">{{ __('Cash') }}</option>
                    <option value="cheque">{{ __('Cheque') }}</option>
                    <option value="upi">{{ __('UPI') }}</option>
                </select>
            </div>

            {{-- Reference Number --}}
            <div class="form-group col-md-6">
                {{ Form::label('reference_number', __('Reference Number'), ['class' => 'form-label']) }}
                <input type="text" name="reference_number" class="form-control" placeholder="{{ __('Enter Reference Number') }}">
            </div>

            {{-- Payment Proof File --}}
            <div class="form-group col-md-6">
                {{ Form::label('payment_proof', __('Payment Proof'), ['class' => 'form-label']) }}<x-required></x-required>
                <input type="file" name="payment_proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                <small class="text-muted">{{ __('Upload payment proof (PDF, JPG, PNG up to 5MB)') }}</small>
            </div>

            {{-- Notes --}}
            <div class="form-group col-md-12">
                {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
                <textarea name="remarks" class="form-control" rows="3" placeholder="{{ __('Optional payment remarks') }}"></textarea>
            </div>
        </div>
    </form>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    <button type="button" class="btn btn-primary" onclick="submitMachineryPayment()">{{ __('Create Payment') }}</button>
</div>

