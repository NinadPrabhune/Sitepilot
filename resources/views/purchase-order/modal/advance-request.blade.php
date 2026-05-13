<div class="modal fade" id="poAdvanceRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Request Advance from Purchase Order') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="poAdvanceRequestModalContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).on('click', '.po-advance-request-btn', function(e) {
    e.preventDefault();
    const poId = $(this).data('po-id');
    const modal = $('#poAdvanceRequestModal');
    const modalContent = $('#poAdvanceRequestModalContent');
    
    // Show loading state
    modalContent.html(`
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    
    // Fetch modal data
    $.ajax({
        url: "{{ route('purchase-order.advance-request-modal', ':poId') }}".replace(':poId', poId),
        type: 'GET',
        success: function(response) {
            if (response.success) {
                renderPOAdvanceModal(response.data);
                modal.modal('show');
            } else {
                modalContent.html(`
                    <div class="alert alert-danger">
                        ${response.message}
                    </div>
                `);
                modal.modal('show');
            }
        },
        error: function(xhr) {
            modalContent.html(`
                <div class="alert alert-danger">
                    {{ __('Failed to load modal data') }}
                </div>
            `);
            modal.modal('show');
        }
    });
});

function renderPOAdvanceModal(data) {
    const modalContent = $('#poAdvanceRequestModalContent');
    const po = data.po;
    const supplier = data.supplier;
    const site = data.site;
    const grandTotal = data.grand_total;
    const existingAdvances = data.existing_advances;
    const pendingAdvances = data.pending_advances;
    const availableBalance = data.available_balance;
    const paymentTerms = data.payment_terms_conditions || '';
    
    // Disable button if no balance available
    const isDisabled = availableBalance <= 0 ? 'disabled' : '';
    const disabledMessage = availableBalance <= 0 
        ? '<div class="alert alert-warning">{{ __("No advance available for this PO") }}</div>' 
        : '';
    
    modalContent.html(`
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">{{ __('PO Information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>{{ __('PO Number') }}:</strong> ${po.po_number || '-'}
                                </div>
                                <div class="mb-2">
                                    <strong>{{ __('Supplier') }}:</strong> ${supplier?.name || '-'}
                                </div>
                                <div class="mb-2">
                                    <strong>{{ __('PO Date') }}:</strong> ${formatDate(po.po_date)}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>{{ __('Site') }}:</strong> ${site?.name || '-'}
                                </div>
                                <div class="mb-2">
                                    <strong>{{ __('Grand Total') }}:</strong> ${formatCurrency(grandTotal)}
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <strong>{{ __('Already Utilized Advances') }}:</strong> ${formatCurrency(existingAdvances)}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <strong>{{ __('Pending Advances') }}:</strong> ${formatCurrency(pendingAdvances)}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <strong>{{ __('Available Balance') }}:</strong> <span class="text-success">${formatCurrency(availableBalance)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        ${paymentTerms ? `
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">{{ __('Payment Terms & Conditions') }}</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">${escapeHtml(paymentTerms)}</p>
                    </div>
                </div>
            </div>
        </div>
        ` : ''}
        
        ${disabledMessage}
        
        <div class="row mt-3">
            <div class="col-md-12">
                <form id="poAdvanceRequestForm">
                    <input type="hidden" name="po_id" value="${po.id}">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="percentage">{{ __('Percentage') }} <span class="text-danger">*</span></label>
                                <input type="number" 
                                       id="percentage" 
                                       name="percentage" 
                                       class="form-control" 
                                       min="1" 
                                       max="100" 
                                       value="10" 
                                       ${isDisabled}
                                       required>
                                <small class="text-muted">{{ __('Enter percentage (1-100%) of PO total') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="advance_amount">{{ __('Advance Amount') }}</label>
                                <input type="text" 
                                       id="advance_amount" 
                                       name="advance_amount" 
                                       class="form-control" 
                                       value="${formatCurrency(grandTotal * 0.10)}" 
                                       readonly>
                                <small class="text-muted">
                                    {{ __('Max allowed: ') }} ${formatCurrency(availableBalance)}<br>
                                    {{ __('Remaining balance: ') }} ${formatCurrency(availableBalance)}
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="payment_date">{{ __('Advance Date') }} <span class="text-danger">*</span></label>
                        <input type="date" 
                               id="payment_date" 
                               name="payment_date" 
                               class="form-control" 
                               value="${new Date().toISOString().split('T')[0]}" 
                               required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="notes">{{ __('Notes') }}</label>
                        <textarea id="notes" 
                                  name="notes" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="{{ __('Optional remarks for this advance request') }}"></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" 
                                class="btn btn-primary" 
                                ${isDisabled}>
                            <i class="ti ti-credit-card"></i> {{ __('Request Advance') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `);
    
    // Auto-calculate advance amount when percentage changes
    $('#percentage').on('input', function() {
        const percentage = parseFloat($(this).val()) || 0;
        const advanceAmount = (grandTotal * percentage) / 100;
        
        if (advanceAmount > availableBalance) {
            $('#advance_amount').val(formatCurrency(availableBalance));
            $(this).addClass('is-invalid');
            $(this).after('<div class="invalid-feedback">{{ __('Advance amount exceeds available balance') }}</div>');
        } else {
            $('#advance_amount').val(formatCurrency(advanceAmount));
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Form submission
    $('#poAdvanceRequestForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            po_id: po.id,
            percentage: parseInt($('#percentage').val()),
            advance_amount: parseCurrency($('#advance_amount').val()),
            payment_date: $('#payment_date').val(),
            notes: $('#notes').val()
        };
        
        // Client-side validation
        if (!formData.payment_date) {
            alert('{{ __('Advance Date is required') }}');
            return;
        }
        
        if (formData.percentage < 1 || formData.percentage > 100) {
            alert('{{ __('Percentage must be between 1 and 100') }}');
            return;
        }
        
        if (formData.advance_amount > availableBalance) {
            alert('{{ __('Advance amount exceeds available balance') }}');
            return;
        }
        
        $.ajax({
            url: "{{ route('purchase-order.advance-request', ':poId') }}".replace(':poId', po.id),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#poAdvanceRequestModal').modal('hide');
                    showNotification('success', response.message);
                    $('#purchase-orders-table').DataTable().ajax.reload();
                } else {
                    showNotification('error', response.message);
                }
            },
            error: function(xhr) {
                const errorMessage = xhr.responseJSON?.message || '{{ __('Failed to create advance request') }}';
                showNotification('error', errorMessage);
            }
        });
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    }).format(amount);
}

function parseCurrency(formatted) {
    return parseFloat(formatted.replace(/[^0-9.-]+/g, ''));
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(type, message) {
    // Simple notification - you can replace with your preferred notification library
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999;" 
             role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('body').append(notification);
    setTimeout(() => notification.remove(), 5000);
}
</script>
