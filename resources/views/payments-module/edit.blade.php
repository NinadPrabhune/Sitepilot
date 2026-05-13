{{ Form::model($paymentsModule, [
    'route' => ['payments-module.update', $paymentsModule->id],
    'method' => 'PUT',
    'class' => 'needs-validation',
    'novalidate',
    'files' => true
]) }}
<input type="hidden" id="edit_po_id" value="{{ $poId ?? '' }}">
<input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
<div class="modal-body">
    <div class="row">
        {{-- Payment Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_number', __('Payment Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('payment_number', null, ['class' => 'form-control', 'required' => true, 'readonly' => true]) }}
        </div>

        {{-- Payment Date --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_date', __('Payment Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('payment_date', \Carbon\Carbon::parse($paymentsModule->payment_date)->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
            @error('payment_date') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Site')]) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Payment Type --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_type', __('Payment Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('payment_type', [
                'advance_against_po' => __('Advance Against PO'),
                'against_invoice' => __('Against Invoice'),
                'mixed' => __('Mixed (Advance + Invoice)'),
                'on_account' => __('On Account (No PO)')
            ], $paymentsModule->payment_type, ['class' => 'form-control', 'required' => 'required', 'id' => 'payment_type']) }}
            @error('payment_type') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-6">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('supplier_id', $suppliers, $paymentsModule->supplier_id, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Supplier'), 'id' => 'supplier_id']) }}
            @error('supplier_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Mode --}}
        <div class="form-group col-md-6">
            {{ Form::label('mode', __('Payment Mode'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('mode', [
                'cash' => __('Cash'),
                'bank_transfer' => __('Bank Transfer'),
                'cheque' => __('Cheque'),
                'upi' => __('UPI')
            ], $paymentsModule->mode, [
                'class' => 'form-control select2',
                'placeholder' => __('Select Payment Mode'),
                'required' => 'required'
            ]) }}
            @error('mode') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Reference Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('reference_number', __('Reference Number'), ['class' => 'form-label']) }}
            {{ Form::text('reference_number', $paymentsModule->reference_number, ['class' => 'form-control']) }}
            @error('reference_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Notes --}}
        <div class="form-group col-md-6">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', $paymentsModule->notes, ['class' => 'form-control', 'rows' => 2]) }}
            @error('notes') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Payment Proof File --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_proff_file', __('Payment Proof File'), ['class' => 'form-label']) }}
            {{ Form::file('payment_proff_file', ['class' => 'form-control']) }}
            @if($paymentsModule->payment_proff_file)
                <small class="text-muted">{{ __('Current file:') }} {{ basename($paymentsModule->payment_proff_file) }}</small>
            @endif
            @error('payment_proff_file') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
    </div>

    {{-- Ledger / A/c Statement Section --}}
    <div class="row mt-3" id="ledger_section" style="display: none;">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Supplier Ledger') }}</h5>
                </div>
                <div class="card-body table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Details') }}</th>
                                <th class="text-end">{{ __('Debit') }}</th>
                                <th class="text-end">{{ __('Credit') }}</th>
                                <th class="text-end">{{ __('Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody id="ledger_tbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">{{ __('Select a supplier to view ledger') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Invoice Allocation Section (shown for against_invoice and mixed types) --}}
    <div class="row mt-3" id="allocation_section" style="display: none;">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Invoice Allocation') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="invoice_allocation_table">
                            <thead>
                                <tr>
                                    <th>{{ __('Invoice No') }}</th>
                                    <th>{{ __('Invoice Date') }}</th>
                                    <th>{{ __('Invoice Amount') }}</th>
                                    <th>{{ __('Paid') }}</th>
                                    <th>{{ __('Balance') }}</th>
                                    <th>{{ __('Pay Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody id="invoice_allocation_tbody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        {{ __('Select a supplier to view unpaid invoices') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>{{ __('Total Allocated Amount') }}:</th>
                                    <td class="text-end">
                                        <span class="fw-bold text-success" id="total_allocated">0.00</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Amount Section --}}
    <div class="row mt-3">
        <div class="form-group col-md-6">
            {{ Form::label('remaining_payment', __('Remaining Payment'), ['class' => 'form-label']) }}
            {{ Form::text('remaining_payment', '', ['class' => 'form-control', 'readonly' => true, 'id' => 'remaining_payment', 'placeholder' => '0.00']) }}
        </div>
        
        <div class="form-group col-md-6">
            {{ Form::label('amount', __('Payment Amount'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('amount', $paymentsModule->amount, ['class' => 'form-control', 'step' => '0.01', 'required' => 'required', 'placeholder' => __('Enter Amount'), 'id' => 'payment_amount']) }}
            @error('amount') <small class="text-danger">{{ $message }}</small> @enderror
            <small class="text-muted" id="amount_hint"></small>
        </div>
    </div>

</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}


<script src="{{ asset('js/jquery-ui.min.js') }}"></script>

<script>
    var payments_module_id = "{{ $paymentsModule->id }}";
    var existingAllocations = @json($paymentsModule->allocations->toArray());
    
    function checkNotNegative(fieldId) {
        let value = parseFloat($('#' + fieldId).val());
        if (!isNaN(value) && value < 0) {
            toastrs('Error', 'Value cannot be negative', 'error');
            $('#' + fieldId).val(0);
            return false;
        }
        return true;
    }
    
    function formatCurrency(amount) {
        return '₹' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function loadSupplierLedger(supplierId, siteId) {
        $.ajax({
            url: "{{ route('payments-module.get-supplier-ledger') }}",
            type: "GET",
            data: { 
                supplier_id: supplierId,
                site_id: siteId 
            },
            success: function(response) {
                if (response.status === 'success') {
                    renderLedgerTable(response.entries);
                    $('#ledger_section').show();
                } else {
                    $('#ledger_section').hide();
                }
            },
            error: function() {
                $('#ledger_section').hide();
            }
        });
    }
    
    function getRemainingPayment(poId, invoiceId, paymentType) {
        let supplierId = $('#supplier_id').val();
        let siteId = $('#site_id').val();
        
        $.ajax({
            url: "{{ route('payments-module.get-remaining-payment') }}",
            type: "POST",
            data: {
                po_id: poId,
                invoice_id: invoiceId,
                payment_type: paymentType,
                supplier_id: supplierId,
                site_id: siteId,
                _token: $('#csrf_token').val()
            },
            success: function(response) {
                if (response.status === 'success') {
                    let remainingPayment = response.remaining_payment;
                    
                    if (remainingPayment > 0) {
                        $('#remaining_payment').val(formatCurrency(remainingPayment));
                        $('#payment_amount').attr('max', remainingPayment);
                        $('#amount_hint').text('Maximum: ' + formatCurrency(remainingPayment));
                    } else {
                        $('#remaining_payment').val(formatCurrency(0));
                        $('#payment_amount').attr('max', 0);
                        $('#amount_hint').text('Maximum: ' + formatCurrency(0));
                    }
                }
            },
            error: function() {
                $('#remaining_payment').val(formatCurrency(0));
            }
        });
    }
    
    function renderLedgerTable(entries) {
        let tbody = $('#ledger_tbody');
        tbody.empty();
        
        if (entries.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted">{{ __("No transactions yet") }}</td></tr>');
            return;
        }
        
        entries.forEach(function(entry) {
            let dateCell = entry.date || '-';
            let detailsCell = '-';
            let debitCell = '-';
            let creditCell = '-';
            let balanceCell = '-';
            
            let meta = entry.meta || {};
            let isNonAccounting = meta.non_accounting === true;
            
            detailsCell = entry.details || '-';
            
            // Show debit for non-accounting entries (only if has value)
            if (!isNonAccounting && entry.debit > 0) {
                debitCell = formatCurrency(entry.debit);
            }
            
            // Show credit for non-accounting entries (only if has value)
            if (!isNonAccounting && entry.credit > 0) {
                creditCell = formatCurrency(entry.credit);
            }
            
            // Show balance - show "-" for non-accounting entries
            if (!isNonAccounting) {
                balanceCell = formatCurrency(entry.running_balance || 0);
            }
            
            let row = `
                <tr>
                    <td>${dateCell}</td>
                    <td>${detailsCell}</td>
                    <td class="text-end">${debitCell}</td>
                    <td class="text-end">${creditCell}</td>
                    <td class="text-end fw-bold">${balanceCell}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    $(document).ready(function() {
        // When payment type changes
        $('#payment_type').on('change', function() {
            let type = $(this).val();
            let poId = $('#edit_po_id').val();
            
            if (type === 'against_invoice' || type === 'mixed') {
                $('#allocation_section').show();
                // Load invoices for the current supplier
                $('#supplier_id').trigger('change');
            } else {
                $('#allocation_section').hide();
            }
            
            // Update remaining payment based on new payment type
            if (poId) {
                getRemainingPayment(poId, null, type);
            }
        });
        
        // When supplier changes, fetch unpaid invoices
        $('#supplier_id').on('change', function() {
            let supplierId = $(this).val();
            let siteId = $('#site_id').val();
            let paymentType = $('#payment_type').val();
            
            // Load supplier ledger
            if (supplierId && siteId) {
                loadSupplierLedger(supplierId, siteId);
            } else {
                $('#ledger_section').hide();
            }
            
            if (!supplierId) {
                $('#invoice_allocation_tbody').html('<tr><td colspan="6" class="text-center text-muted">{{ __("Select a supplier to view unpaid invoices") }}</td></tr>');
                return;
            }
            
            // Only load invoices if payment type requires it
            if (paymentType !== 'against_invoice' && paymentType !== 'mixed') {
                return;
            }
            
            $.ajax({
                url: "{{ route('payments-module.get-supplier-invoices') }}",
                type: "GET",
                data: { 
                    supplier_id: supplierId,
                    site_id: siteId 
                },
                success: function(response) {
                    if (response.status === 'success') {
                        renderInvoiceTable(response.invoices);
                    } else {
                        toastrs('Error', response.message, 'error');
                    }
                },
                error: function(xhr) {
                    let message = xhr.responseJSON?.message ?? 'Unexpected error occurred';
                    toastrs('Error', message, 'error');
                }
            });
        });
        
        function renderInvoiceTable(invoices) {
            let tbody = $('#invoice_allocation_tbody');
            tbody.empty();
            
            if (invoices.length === 0) {
                tbody.html('<tr><td colspan="6" class="text-center text-muted">{{ __("No unpaid invoices found") }}</td></tr>');
                return;
            }
            
            // Create a map of existing allocations by invoice_id
            let existingAllocMap = {};
            existingAllocations.forEach(function(alloc) {
                if (alloc.purchase_invoice_id) {
                    existingAllocMap[alloc.purchase_invoice_id] = alloc.allocated_amount;
                }
            });
            
            invoices.forEach(function(invoice, index) {
                // Check if there's an existing allocation for this invoice
                let existingAmount = existingAllocMap[invoice.id] || 0;
                // Add the existing allocation back to the balance for editing
                let adjustedBalance = parseFloat(invoice.balance) + parseFloat(existingAmount);
                
                let row = `
                    <tr>
                        <td>${invoice.invoice_number}</td>
                        <td>${invoice.invoice_date}</td>
                        <td>${parseFloat(invoice.total_amount).toFixed(2)}</td>
                        <td>${parseFloat(invoice.paid_amount).toFixed(2)}</td>
                        <td>${adjustedBalance.toFixed(2)}</td>
                        <td>
                            <input type="hidden" name="allocations[${index}][invoice_id]" value="${invoice.id}">
                            <input type="hidden" name="allocations[${index}][order_id]" value="">
                            <input type="number" 
                                   class="form-control allocation-amount" 
                                   name="allocations[${index}][amount]" 
                                   value="${existingAmount}" 
                                   min="0" 
                                   max="${adjustedBalance}" 
                                   step="0.01"
                                   data-balance="${adjustedBalance}"
                                   data-index="${index}">
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Add event listeners for allocation amounts
            $('.allocation-amount').on('input', function() {
                let value = parseFloat($(this).val()) || 0;
                let balance = parseFloat($(this).data('balance'));
                
                if (value < 0) {
                    $(this).val(0);
                    value = 0;
                }
                
                if (value > balance) {
                    toastrs('Error', 'Amount cannot be greater than balance', 'error');
                    $(this).val(balance);
                    value = balance;
                }
                
                calculateTotalAllocated();
            });
            
            // Calculate initial total
            calculateTotalAllocated();
        }
        
        function calculateTotalAllocated() {
            let total = 0;
            $('.allocation-amount').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            
            $('#total_allocated').text(total.toFixed(2));
            $('#payment_amount').val(total.toFixed(2));
        }
        
        // Load remaining payment on page load
        let editPoId = $('#edit_po_id').val();
        let editPaymentType = $('#payment_type').val() || 'advance_against_po';
        if (editPoId) {
            getRemainingPayment(editPoId, null, editPaymentType);
        }
        
        // Initial trigger based on payment type
        $('#payment_type').on('change', function() {
            let type = $(this).val();
            let poId = $('#edit_po_id').val();
            
            if (type === 'against_invoice' || type === 'mixed') {
                $('#allocation_section').show();
                // Load invoices for the current supplier
                $('#supplier_id').trigger('change');
            } else {
                $('#allocation_section').hide();
            }
            
            // Update remaining payment based on new payment type
            if (poId) {
                getRemainingPayment(poId, null, type);
            }
        });
        
        $('#payment_amount').on('input change', function() {
            checkNotNegative('payment_amount');
            
            let maxAmount = parseFloat($(this).attr('max')) || 0;
            let enteredAmount = parseFloat($(this).val()) || 0;
            let paymentType = $('#payment_type').val();
            let poId = $('#edit_po_id').val();
            
            if (enteredAmount > maxAmount) {
                toastrs('Error', 'Payment amount cannot exceed remaining payment', 'error');
                $(this).val(maxAmount);
                enteredAmount = maxAmount;
            }
            
            if (poId) {
                getRemainingPayment(poId, null, paymentType);
            }
        });
    });
</script>
