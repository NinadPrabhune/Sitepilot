
            <div class="modal-body">
                {{ Form::open(['route' => 'material-returns.store', 'method' => 'POST', 'id' => 'material-return-form']) }}
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('issue_id', __('Link to Issue'), ['class' => 'form-label']) }}
                            <select name="issue_id" id="issue_id" class="form-select" required>
                                <option value="">{{ __('Select Issue') }}</option>
                                @foreach($issues as $issue)
                                <option value="{{ $issue->id }}" {{ $selectedIssueId == $issue->id ? 'selected' : '' }}>{{ $issue->issue_number }} - {{ $issue->issue_date->format('d-m-Y') }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Material Return must be linked to a Material Issue') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('return_date', __('Return Date'), ['class' => 'form-label']) }}
                            {{ Form::date('return_date', \Carbon\Carbon::now()->toDateString(), ['class' => 'form-control', 'required' => true]) }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
                            {{ Form::textarea('remarks', null, ['class' => 'form-control', 'rows' => 2]) }}
                        </div>
                    </div>
                </div>

                <hr>
                <h5>{{ __('Material Items') }}</h5>
                <p class="text-muted">{{ __('Select an issue above to load items. Return quantity cannot exceed remaining issued quantity.') }}</p>
                
                <div class="table-responsive">
                    <table class="table table-bordered" id="items-table">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Issued Qty') }}</th>
                                <th>{{ __('Already Returned') }}</th>
                                <th>{{ __('Remaining') }}</th>
                                <th>{{ __('Return Qty') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted">{{ __('Please select an issue to load items') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                

                <hr>
                <div class="row">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">{{ __('Create Material Return') }}</button>
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        

<script>
    $(document).ready(function() {
        var issues = @json($issues);

        // Issue select change - load items
        $('#issue_id').change(function() {
            var issueId = $(this).val();
            
            if (issueId) {
                $.ajax({
                    url: "{{ route('material-returns.get-issue-details') }}",
                    type: 'POST',
                    data: {
                        issue_id: issueId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            var issue = response.issue;
                            $('#items-table tbody').empty();
                            
                            if (issue.items.length === 0) {
                                $('#items-table tbody').append(`
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('No items found in this issue') }}</td>
                                    </tr>
                                `);
                                return;
                            }
                            
                            $.each(issue.items, function(index, item) {
                                var remainingQty = item.remaining_qty || item.quantity;
                                var alreadyReturnedQty = item.already_returned_qty || 0;
                                var isDisabled = remainingQty <= 0;
                                
                                var newRow = `
                                    <tr>
                                        <td>
                                            <input type="hidden" name="items[${index}][issue_item_id]" value="${item.id}">
                                            <input type="hidden" name="items[${index}][material_id]" value="${item.material_id}">
                                            <span class="form-control-plaintext">${item.material ? item.material.name : 'N/A'}</span>
                                        </td>
                                        <td>
                                            <span class="form-control-plaintext">${item.quantity} ${item.material && item.material.unit ? item.material.unit.name : ''}</span>
                                        </td>
                                        <td>
                                            <span class="form-control-plaintext ${alreadyReturnedQty > 0 ? 'text-warning' : ''}">${alreadyReturnedQty} ${item.material && item.material.unit ? item.material.unit.name : ''}</span>
                                        </td>
                                        <td>
                                            <span class="form-control-plaintext ${remainingQty > 0 ? 'text-success' : 'text-danger'}">${remainingQty} ${item.material && item.material.unit ? item.material.unit.name : ''}</span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="items[${index}][quantity]" 
                                                   class="form-control quantity-input" 
                                                   step="0.01" 
                                                   min="0.01" 
                                                   max="${remainingQty}"
                                                   value="${remainingQty > 0 ? remainingQty : 0}"
                                                   ${isDisabled ? 'disabled' : 'required'}
                                                   data-remaining="${remainingQty}">
                                            ${isDisabled ? '<small class="text-danger">{{ __('Fully returned') }}</small>' : ''}
                                        </td>
                                        <td>
                                            <input type="text" name="items[${index}][remarks]" class="form-control" ${isDisabled ? 'disabled' : ''}>
                                        </td>
                                    </tr>
                                `;
                                $('#items-table tbody').append(newRow);
                            });
                        }
                    }
                });
            } else {
                $('#items-table tbody').html(`
                    <tr>
                        <td colspan="6" class="text-center text-muted">{{ __('Please select an issue to load items') }}</td>
                    </tr>
                `);
            }
        });

        // Validate quantity on input
        $(document).on('input', '.quantity-input', function() {
            var input = $(this);
            var remaining = parseFloat(input.data('remaining'));
            var value = parseFloat(input.val()) || 0;
            
            if (value > remaining) {
                input.addClass('is-invalid');
                if (!input.next('.invalid-feedback').length) {
                    input.after('<div class="invalid-feedback">{{ __('Return quantity cannot exceed remaining issued quantity') }}</div>');
                }
            } else {
                input.removeClass('is-invalid');
                input.next('.invalid-feedback').remove();
            }
        });

        // Form validation
        $('#material-return-form').submit(function(e) {
            var isValid = true;
            var issueId = $('#issue_id').val();
            
            if (!issueId) {
                alert('{{ __("Please select a Material Issue") }}');
                e.preventDefault();
                return false;
            }
            
            $('#items-table tbody tr').each(function() {
                var quantityInput = $(this).find('.quantity-input');
                if (quantityInput.length && !quantityInput.prop('disabled')) {
                    var quantity = parseFloat(quantityInput.val());
                    var remaining = parseFloat(quantityInput.data('remaining'));
                    
                    if (!quantity || quantity <= 0) {
                        alert('{{ __("Return quantity must be greater than zero") }}');
                        isValid = false;
                        return false;
                    }
                    
                    if (quantity > remaining) {
                        alert('{{ __("Return quantity cannot exceed remaining issued quantity") }}');
                        isValid = false;
                        return false;
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
</script>
