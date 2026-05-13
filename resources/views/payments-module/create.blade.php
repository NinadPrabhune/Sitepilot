{{ Form::open(['route' => 'payments-module.store', 'class' => 'needs-validation', 'novalidate', 'files' => true]) }}
<div class="modal-body">
    <div class="row">
        
        {{-- Payment Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_number', __('Payment Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('payment_number', isset($nextPaymentNumber) ? $nextPaymentNumber : '', ['class' => 'form-control', 'required' => true, 'readonly' => true, 'placeholder' => 'Auto-generated']) }}
        </div>
        
        {{-- Payment Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_date', __('Payment Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('payment_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
            @error('payment_date') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        
        {{-- Site --}}
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, getActiveWorkSpace(), ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Site'), 'id' => 'site_id']) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        
        {{-- Payment Type --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_type', __('Payment Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('payment_type', [
                'advance_against_po' => __('Advance Against PO'),
                'against_po' => __('Against PO'),
                'against_invoice' => __('Against Invoice')
            ], null, ['class' => 'form-control', 'required' => 'required', 'id' => 'payment_type']) }}
            @error('payment_type') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        
        {{-- Supplier --}}
        <div class="form-group col-md-4">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('supplier_id', $suppliers, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Supplier'), 'id' => 'supplier_id']) }}
            @error('supplier_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Purchase Order --}}
        <div class="form-group col-md-4">
            {{ Form::label('purchase_order_id', __('Purchase Order'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('purchase_order_id', [], null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select PO'), 'id' => 'purchase_order_id']) }}
            @error('purchase_order_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        
        {{-- Mode --}}
        <div class="form-group col-md-4">
            {{ Form::label('mode', __('Payment Mode'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('mode', [
                'cash' => __('Cash'),
                'bank_transfer' => __('Bank Transfer'),
                'cheque' => __('Cheque'),
                'upi' => __('UPI')
            ], null, [
                'class' => 'form-control select2',
                'placeholder' => __('Select Payment Mode'),
                'required' => 'required'
            ]) }}
            @error('mode') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Reference Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('reference_number', __('Reference Number'), ['class' => 'form-label']) }}
            {{ Form::text('reference_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Reference Number')]) }}
            @error('reference_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Payment Proof File --}}
        <div class="form-group col-md-4">
            {{ Form::label('payment_proff_file', __('Payment Proof File'), ['class' => 'form-label']) }}
            {{ Form::file('payment_proff_file', ['class' => 'form-control']) }}
            @error('payment_proff_file') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

    </div>

    {{-- Live Summary Card --}}
    <div class="row mt-3" id="po_summary_card" style="display: none;">
        <div class="col-md-12">
            <div class="card bg-primary">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col">
                            <small style="color: white;">{{ __('PO Total') }}</small>
                            <h5 class="mb-0" style="font-weight: bold; color: white;" id="summary_po_total">₹0.00</h5>
                        </div>
                        <div class="col">
                            <small style="color: white;">{{ __('Invoiced') }}</small>
                            <h5 class="mb-0" style="font-weight: bold; color: white;" id="summary_invoiced">₹0.00</h5>
                        </div>
                        <div class="col">
                            <small style="color: white;">{{ __('Paid') }}</small>
                            <h5 class="mb-0" style="font-weight: bold; color: white;" id="summary_paid">₹0.00</h5>
                        </div>
                        <div class="col">
                            <small style="color: white;">{{ __('Payable') }}</small>
                            <h5 class="mb-0" style="font-weight: bold; color: white;" id="summary_payable">₹0.00</h5>
                        </div>
                    </div>
                </div>
            </div>
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
                                <td colspan="5" class="text-center text-muted">{{ __('Select a PO to view ledger') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Amount Section --}}
    <div class="row mt-3">
        <div class="form-group col-md-4">
            {{ Form::label('remaining_payment', __('Remaining Payment'), ['class' => 'form-label']) }}
            {{ Form::text('remaining_payment', '', ['class' => 'form-control', 'readonly' => true, 'id' => 'remaining_payment', 'placeholder' => '0.00']) }}
        </div>
        
        <div class="form-group col-md-4">
            {{ Form::label('amount', __('Payment Amount'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('amount', null, ['class' => 'form-control', 'step' => '0.01', 'required' => 'required', 'placeholder' => __('Enter Amount'), 'id' => 'payment_amount', 'min' => '0']) }}
            @error('amount') <small class="text-danger">{{ $message }}</small> @enderror
            <small class="text-muted" id="amount_hint"></small>
        </div>
        
        {{-- Notes --}}
        <div class="form-group col-md-4">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter Notes')]) }}
            @error('notes') <small class="text-danger">{{ $message }}</small> @enderror
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
        if (amount === undefined || amount === null || isNaN(amount)) {
            return '₹0.00';
        }
        return '₹' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function loadSuppliers(siteId) {
        $.ajax({
            url: "{{ route('payments-module.get-suppliers-with-pending-pos') }}",
            type: "GET",
            data: { site_id: siteId },
            success: function(response) {
                if (response.status === 'success') {
                    $('#supplier_id').empty().append('<option value="">{{ __("Select Supplier") }}</option>');
                    $.each(response.suppliers, function(key, value) {
                        $('#supplier_id').append('<option value="'+ key +'">'+ value +'</option>');
                    });
                } else {
                    $('#supplier_id').empty().append('<option value="">{{ __("Select Supplier") }}</option>');
                }
            },
            error: function() {
                $('#supplier_id').empty().append('<option value="">{{ __("Select Supplier") }}</option>');
            }
        });
    }

    function loadPOs(supplierId) {
        let siteId = $('#site_id').val();
        $.ajax({
            url: "{{ route('payments-module.get-pos-with-pending-balance') }}",
            type: "GET",
            data: { 
                supplier_id: supplierId,
                site_id: siteId
            },
            success: function(response) {
                $('#purchase_order_id').empty().append('<option value="">{{ __("Select PO") }}</option>');
                if (response.status === 'success' && response.pos && response.pos.length > 0) {
                    $.each(response.pos, function(key, po) {
                        let remaining = parseFloat(po.remaining_balance) || 0;
                        $('#purchase_order_id').append('<option value="'+ po.id +'">'+ po.po_number +' - Remaining: '+ remaining.toFixed(2) +'</option>');
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#purchase_order_id').empty().append('<option value="">{{ __("No POs found") }}</option>');
            }
        });
    }

    function loadPOSummary(poId) {
        $.ajax({
            url: "{{ route('payments-module.get-po-summary') }}",
            type: "GET",
            data: { purchase_order_id: poId },
            success: function(response) {
                if (response.status === 'success') {
                    $('#summary_po_total').text(formatCurrency(response.po_total));
                    $('#summary_invoiced').text(formatCurrency(response.invoiced_amount)); // Show actual invoiced amount
                    $('#summary_paid').text(formatCurrency(response.paid_amount));
                    $('#summary_payable').text(formatCurrency(response.payable));
                    
                    let paymentType = $('#payment_type').val();
                    let currentAmount = parseFloat($('#payment_amount').val()) || 0;
                    
                    // Get remaining payment from the field (set by ledger)
                    let remainingVal = $('#remaining_payment').val();
                    let remainingPayment = parseFloat(remainingVal.replace(/[^0-9.-]/g, '')) || 0;
                    
                    if (paymentType === 'advance_against_po') {
                        $('#amount_hint').text('Maximum: ' + formatCurrency(response.po_total));
                    } else {
                        $('#amount_hint').text('Maximum: ' + formatCurrency(remainingPayment));
                    }
                    
                    $('#po_summary_card').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading PO summary:', error);
            }
        });
    }

    function loadPOLedger(poId) {
        let paymentType = $('#payment_type').val() || 'advance_against_po';
        
        $.ajax({
            url: "{{ route('payments-module.get-po-ledger') }}",
            type: "GET",
            data: { purchase_order_id: poId },
            success: function(response) {
                if (response.status === 'success') {
                    renderLedgerTable(response.entries);
                    $('#ledger_section').show();
                    
                    getRemainingPayment(poId, null, paymentType);
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
                _token: '{{ csrf_token() }}'
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
        loadSuppliers($('#site_id').val());

        $('#site_id').on('change', function() {
            loadSuppliers($(this).val());
            $('#purchase_order_id').empty().append('<option value="">{{ __("Select PO") }}</option>');
            $('#po_summary_card').hide();
            $('#ledger_section').hide();
            $('#remaining_payment').val('');
            $('#payment_amount').val('');
        });

        $('#supplier_id').on('change', function() {
            let supplierId = $(this).val();
            if (!supplierId) {
                $('#purchase_order_id').empty().append('<option value="">{{ __("Select PO") }}</option>');
                $('#po_summary_card').hide();
                $('#ledger_section').hide();
                $('#remaining_payment').val('');
                $('#payment_amount').val('');
                return;
            }
            loadPOs(supplierId);
        });

        $('#purchase_order_id').on('change', function() {
            let poId = $(this).val();
            if (!poId) {
                $('#po_summary_card').hide();
                $('#ledger_section').hide();
                $('#remaining_payment').val('');
                return;
            }
            loadPOSummary(poId);
            loadPOLedger(poId);
        });

        $('#payment_type').on('change', function() {
            let poId = $('#purchase_order_id').val();
            let paymentType = $(this).val();
            
            if (poId) {
                loadPOSummary(poId);
                loadPOLedger(poId);
                getRemainingPayment(poId, null, paymentType);
            }
        });

        $('#payment_amount').on('input change', function() {
            checkNotNegative('payment_amount');
            
            let poId = $('#purchase_order_id').val();
            let paymentType = $('#payment_type').val();
            let maxAmount = parseFloat($(this).attr('max')) || 0;
            let enteredAmount = parseFloat($(this).val()) || 0;
            
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