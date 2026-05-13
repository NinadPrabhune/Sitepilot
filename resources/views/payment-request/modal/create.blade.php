<form id="payment-request-form" method="POST" action="{{ route('payment-request.store') }}">
    @csrf
    <input type="hidden" name="purchase_invoice_id" value="{{ $invoice->id }}">

    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <!-- PO/Direct GRN Badge -->
        <div class="row mb-3">
            <div class="col-12">
                @if($invoice->po_id)
                    <span class="badge bg-primary">{{ __('PO-Based Invoice') }}</span>
                    <span class="badge bg-secondary">{{ $invoice->purchaseOrder->po_number ?? '' }}</span>
                @else
                    <span class="badge bg-warning text-dark">{{ __('Direct GRN Invoice') }}</span>
                    <span class="badge bg-danger">{{ __('No Advance Available') }}</span>
                @endif
            </div>
        </div>

        <!-- Quick Financial Summary Strip -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info d-flex justify-content-between align-items-center py-2 mb-0">
                    <div class="text-center">
                        <small class="d-block text-muted">{{ __('Invoice Total') }}</small>
                        <strong class="h6 mb-0">₹{{ format_indian_currency($invoice->grand_total) }}</strong>
                    </div>
                    <div class="text-center">
                        <small class="d-block text-muted">{{ __('Direct Payments') }}</small>
                        <strong class="h6 mb-0">₹{{ format_indian_currency($paidAmount) }}</strong>
                    </div>
                    @if($invoice->po_id)
                    <div class="text-center">
                        <small class="d-block text-muted">{{ __('Advance Used') }}</small>
                        <strong class="h6 mb-0">₹{{ format_indian_currency($advanceUtilized ?? 0) }}</strong>
                    </div>
                    <div class="text-center">
                        <small class="d-block text-muted">{{ __('Available Advance') }}</small>
                        <strong class="h6 mb-0">₹{{ format_indian_currency($advanceAmount) }}</strong>
                    </div>
                    @endif
                    <div class="text-center">
                        <small class="d-block text-muted">{{ __('Net Payable') }}</small>
                        <strong class="h6 mb-0 text-success">₹{{ format_indian_currency($maxAllowedAmount) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @if(!$invoice->po_id)
        <!-- Direct GRN Warning -->
        <div class="alert alert-warning mb-3">
            <i class="ti ti-alert-triangle"></i>
            <strong>{{ __('Direct GRN Invoice') }}</strong>: {{ __('This invoice is not linked to a Purchase Order. Advance allocation is not available. Full payment is required.') }}
        </div>
        @endif

        <!-- Invoice Details Section -->
        <div class="card mb-3">
            <div class="card-header cursor-pointer" data-bs-toggle="collapse" data-bs-target="#invoice-details-collapse" role="button" aria-expanded="true">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">{{ __('Invoice Details') }}</h6>
                    <i class="ti ti-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div id="invoice-details-collapse" class="collapse show">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted">{{ __('Invoice Number') }}:</td>
                                    <td class="fw-bold">{{ $invoice->invoice_number }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('Invoice Date') }}:</td>
                                    <td>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('Supplier Invoice No') }}:</td>
                                    <td>{{ $invoice->supplier_invoice_number ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('Supplier Name') }}:</td>
                                    <td>{{ $invoice->supplier->name ?? '-' }}</td>
                                </tr>
                                
                            </table>
                        </div>
                        <div class="col-md-4">
                            <table class="table table-sm table-borderless">
                                
                                <tr>
                                    <td class="text-muted">{{ __('Site') }}:</td>
                                    <td>{{ $invoice->site->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('PO Number') }}:</td>
                                    <td>{{ $invoice->purchaseOrder?->po_number ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('GRN Number') }}:</td>
                                    <td>{{ $invoice->grn?->grn_number ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('Tax Type') }}:</td>
                                <td>{{ strtoupper($invoice->tax_type ?? '-') }}</td>
                            </tr>
                            
                           
                        </table>
                    </div>
                        
                        <div class="col-md-4">
                            <table class="table table-sm table-borderless">
                             <tr>
                                <td class="text-muted">{{ __('Invoice Type') }}:</td>
                                <td>{{ $invoice->invoice_type ?? '-' }}</td>
                            </tr>   
                               
                            <tr>
                                <td class="text-muted">{{ __('Approval Status') }}:</td>
                                <td>
                                    @if($invoice->status === 'Approved')
                                        <span class="badge bg-success">{{ __('Approved') }}</span>
                                    @elseif($invoice->status === 'Pending')
                                        <span class="badge bg-warning">{{ __('Pending') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ $invoice->status }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('Payment Status') }}:</td>
                                <td>
                                    @if($invoice->payment_status === 'paid')
                                        <span class="badge bg-success">{{ __('Paid') }}</span>
                                    @elseif($invoice->payment_status === 'partially_paid')
                                        <span class="badge bg-info">{{ __('Partially Paid') }}</span>
                                    @else
                                        <span class="badge bg-warning">{{ __('Unpaid') }}</span>
                                    @endif
                                </td>
                            </tr>
                            
                            
                            
                            <tr>
                                <td class="text-muted">Invoice PDF:</td>
                                <td>
                                    @if($invoice->pi_pdf)
                                    <div class="mt-2">
                                        <a href="{{ route('purchase-invoice.download-pdf', $invoice->id) }}" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="ti ti-file"></i> {{ __('Download ') }}
                                        </a>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            
                            
                            
                        </table>
                    </div>
                        
                        
                </div>
                
                </div>
            </div>
        </div>

        <!-- Tax Summary Section -->
        <div class="card mb-3">
            <div class="card-header cursor-pointer" data-bs-toggle="collapse" data-bs-target="#tax-summary-collapse" role="button" aria-expanded="true">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">{{ __('Tax Summary') }}</h6>
                    <i class="ti ti-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div id="tax-summary-collapse" class="collapse show">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted d-block">{{ __('Taxable Value') }}</small>
                                <strong>₹{{ format_indian_currency($invoice->total_taxable_value) }}</strong>
                            </div>
                        </div>
                        @if($invoice->tax_type === 'cgst')
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted d-block">{{ __('CGST + SGST') }}</small>
                                <strong>₹{{ format_indian_currency($invoice->total_cgst + $invoice->total_sgst) }}</strong>
                                <small class="d-block text-muted">({{ format_indian_currency($invoice->total_cgst) }} + {{ format_indian_currency($invoice->total_sgst) }})</small>
                            </div>
                        </div>
                        @else
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted d-block">{{ __('IGST') }}</small>
                                <strong>₹{{ format_indian_currency($invoice->total_igst) }}</strong>
                            </div>
                        </div>
                        @endif
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted d-block">{{ __('Total Tax') }}</small>
                                <strong>₹{{ format_indian_currency($invoice->total_tax) }}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted d-block">{{ __('Discount') }}</small>
                                <strong>₹{{ format_indian_currency($invoice->total_discount ?? 0) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted d-block">{{ __('Paid Amount') }}</small>
                                <strong>₹{{ format_indian_currency($paidAmount) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                                <small class="text-muted d-block">{{ __('Grand Total') }}</small>
                                <strong class="h4 text-success">₹{{ format_indian_currency($invoice->grand_total) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supplier Ledger Section -->
        <div class="card mb-3">
            <div class="card-header cursor-pointer" data-bs-toggle="collapse" data-bs-target="#supplier-ledger-collapse" role="button" aria-expanded="true">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">{{ __('Supplier Ledger') }}</h6>
                    <div>
                        <small class="text-muted me-2">{{ __('Last 20 transactions') }}</small>
                        <i class="ti ti-chevron-down toggle-icon"></i>
                    </div>
                </div>
            </div>
            <div id="supplier-ledger-collapse" class="collapse show">
                <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                @if($ledgerEntries->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="ti ti-receipt fs-1"></i>
                        <p class="mb-0">{{ __('No transactions found for this supplier.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light position-sticky top-0">
                                <tr>
                                    <th>{{ __('Date & Time') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th class="text-end">{{ __('Debit') }}</th>
                                    <th class="text-end">{{ __('Credit') }}</th>
                                    <th class="text-end">{{ __('Balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ledgerEntries as $entry)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($entry->transaction_date)->format('d M Y h:i A') }}</td>
                                    <td>{{ $entry->description ?? '-' }}</td>
                                    <td class="text-end">{{ $entry->debit ? '₹'.format_indian_currency($entry->debit) : '-' }}</td>
                                    <td class="text-end">{{ $entry->credit ? '₹'.format_indian_currency($entry->credit) : '-' }}</td>
                                    <td class="text-end">
                                        <span class="{{ $entry->running_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                            ₹{{ format_indian_currency($entry->running_balance) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>
        </div>

        <!-- Payment Request Form Section -->
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary bg-opacity-10 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#payment-request-form-collapse" role="button" aria-expanded="true">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">{{ __('Payment Request Form') }}</h6>
                    <i class="ti ti-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div id="payment-request-form-collapse" class="collapse show">
                <div class="card-body">
                    <div class="row">
                        <!-- Payment Date -->
                        <div class="col-md-4">
                            <div class="form-group">
                                {{ Form::label('payment_date', __('Payment Date'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::date('payment_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true, 'min' => \Carbon\Carbon::now()->format('Y-m-d')]) }}
                                @error('payment_date') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <!-- Requested Amount -->
                        <div class="col-md-4">
                            <div class="form-group">
                                {{ Form::label('requested_amount', __('Requested Amount'), ['class' => 'form-label']) }}<x-required></x-required>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    {{ Form::number('requested_amount', number_format($maxAllowedAmount, 2, '.', ''), [
                                        'class' => 'form-control',
                                        'required' => true,
                                        'min' => '0.01',
                                        'step' => '0.01',
                                        'max' => number_format($maxAllowedAmount, 2, '.', '')
                                    ]) }}
                                </div>
                                <small class="text-muted">{{ __('Max allowed: ₹') }}{{ format_indian_currency($maxAllowedAmount) }}</small>
                                @error('requested_amount') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <!-- Max Allowed Amount Display -->
                        <div class="col-md-4">
                            <div class="form-group">
                                {{ Form::label('max_allowed', __('Available Balance'), ['class' => 'form-label']) }}
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="text" class="form-control" value="{{ format_indian_currency($maxAllowedAmount) }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Terms -->
                    @if($paymentTerms)
                    <div class="col-12 mt-3">
                        <div class="form-group">
                            {{ Form::label('payment_terms', __('Payment Terms & Conditions'), ['class' => 'form-label']) }}
                            <div class="p-2 bg-light rounded">
                                {!! nl2br(e($paymentTerms)) !!}
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Remarks -->
                    <div class="col-12 mt-3">
                        <div class="form-group">
                            {{ Form::label('remarks', __('Remarks (Optional)'), ['class' => 'form-label']) }}
                            {{ Form::textarea('remarks', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter any additional notes...')]) }}
                        </div>
                    </div>
                </div>

                <!-- Warning Messages -->
                @if($invoice->status !== 'Approved')
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="ti ti-alert-circle"></i>
                    {{ __('Note: This invoice is not yet approved. You can still create a payment request but it will require approval before payment can be processed.') }}
                </div>
                @endif

                @if($invoice->getActivePaymentRequestsSum() > 0)
                <div class="alert alert-info mt-2 mb-0">
                    <i class="ti ti-info-circle"></i>
                    {{ __('There is already an active payment request of ₹') }}{{ format_indian_currency($invoice->getActivePaymentRequestsSum()) }} {{ __('for this invoice.') }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="submit" class="btn btn-primary">{{ __('Submit Payment Request') }}</button>
    </div>
</form>

<style>
.cursor-pointer { cursor: pointer; }
.toggle-icon { transition: transform 0.3s ease; }
.rotate-180 { transform: rotate(180deg); }
</style>
<script>
var maxAllowedAmount = {{ $maxAllowedAmount }};
var isSubmitting = false;
var isDirectGRN = {{ $invoice->po_id ? 'false' : 'true' }};

$(document).ready(function() {
    // Toggle icon rotation for collapsible sections
    $('.collapse').on('show.bs.collapse hide.bs.collapse', function() {
        $(this).prev('.card-header').find('.toggle-icon').toggleClass('rotate-180');
    });

    // Auto-focus on requested amount field
    setTimeout(function() {
        $('#requested_amount').focus();
    }, 300);

    // CRITICAL: Direct GRN - Force full payment
    if (isDirectGRN) {
        $('#requested_amount').val(maxAllowedAmount).prop('readonly', true);
        $('#requested_amount').parent().after('<small class="text-warning d-block mt-1">{{ __("Direct GRN requires full payment") }}</small>');
    }

    // Real-time validation for requested amount
    $('#requested_amount').on('input change', function() {
        var value = parseFloat(this.value) || 0;
        var max = parseFloat(maxAllowedAmount);

        // Direct GRN - cannot modify amount
        if (isDirectGRN) {
            this.value = max;
            return;
        }

        // Handle decimal overflow - round to 2 decimals
        if (this.value && this.value.includes('.')) {
            var parts = this.value.split('.');
            if (parts[1].length > 2) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        }

        if (value > max) {
            this.value = max;
            toastrs('{{ __("Warning") }}', '{{ __("Amount exceeds maximum. Adjusted to maximum.") }}', 'warning');
        }

        if (value < 0 && this.value !== '') {
            this.value = Math.abs(value);
            toastrs('{{ __("Warning") }}', '{{ __("Negative values not allowed.") }}', 'warning');
        }

        if (value <= 0 && this.value !== '') {
            this.value = '';
        }
    });
    
    $('#payment-request-form').on('submit', function(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        var form = $(this);
        var requestedAmount = parseFloat($('#requested_amount').val()) || 0;
        
        // Validation
        if (! $('#requested_amount').val()) {
            toastrs('{{ __("Error") }}', '{{ __("Please enter requested amount") }}', 'error');
            return;
        }
        
        if (requestedAmount > maxAllowedAmount) {
            toastrs('{{ __("Error") }}', '{{ __("Requested amount exceeds maximum allowed") }}', 'error');
            return;
        }
        
        if (requestedAmount < 0.01) {
            toastrs('{{ __("Error") }}', '{{ __("Amount must be at least ₹0.01") }}', 'error');
            return;
        }
        
        isSubmitting = true;
        
        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> {{ __("Processing...") }}');
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                if(response.success) {
                    toastrs('{{ __("Success") }}', response.message, 'success');
                    $('#commonModal').modal('hide');
                    if (typeof dt_dtable !== 'undefined') {
                        dt_dtable.ajax.reload();
                    }
                    // Refresh page to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    toastrs('{{ __("Error") }}', response.message, 'error');
                    submitBtn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            },
            error: function(xhr) {
                var errorMessage = '{{ __("Something went wrong. Please try again.") }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastrs('{{ __("Error") }}', errorMessage, 'error');
                submitBtn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            }
        });
    });
});
</script>