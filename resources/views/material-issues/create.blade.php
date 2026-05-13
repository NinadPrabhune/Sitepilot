
            <div class="modal-body">
                {{ Form::open(['route' => 'material-issues.store', 'method' => 'POST', 'id' => 'material-issue-form']) }}
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('issue_to_type', __('Issue To Type'), ['class' => 'form-label']) }}
                            <select name="issue_to_type" id="issue_to_type" class="form-select" required>
                                <option value="">{{ __('Select Type') }}</option>
                                <option value="user">{{ __('User (Employee)') }}</option>
                                <option value="supplier">{{ __('Supplier (Subcontractor)') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('issue_to_id', __('Issue To'), ['class' => 'form-label']) }}
                            <select name="issue_to_id" id="issue_to_id" class="form-select" required disabled>
                                <option value="">{{ __('Select Type First') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('issue_date', __('Issue Date'), ['class' => 'form-label']) }}
                            {{ Form::date('issue_date', \Carbon\Carbon::now()->toDateString(), ['class' => 'form-control', 'required' => true]) }}
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
                <div class="table-responsive">
                     <button type="button" class="btn btn-sm btn-primary float-end" id="add-row"><i class="ti ti-plus"></i> {{ __('Add Row') }}</button>
                    <table class="table table-bordered" id="items-table">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Available Stock') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Remarks') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="items[0][material_id]" class="form-select material-select" required>
                                        <option value="">{{ __('Select Material') }}</option>
                                        @foreach($materials as $material)
                                        <option value="{{ $material->id }}" data-unit="{{ $material->unit ? $material->unit->name : '' }}">{{ $material->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <span class="available-stock badge bg-secondary">0</span>
                                    <span class="unit-name text-muted"></span>
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]" class="form-control quantity-input" step="0.01" min="0.01" required>
                                </td>
                                <td>
                                    <input type="number" name="items[0][rate]" class="form-control rate-input" step="0.01" min="0">
                                </td>
                                <td>
                                    <input type="text" name="items[0][remarks]" class="form-control">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
               

                <hr>
                <div class="row">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">{{ __('Create Material Issue') }}</button>
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        

<script>
    $(document).ready(function() {
        var rowIndex = 1;
        var usersData = @json($users);
        var suppliers = @json($suppliers);
        var materials = @json($materials);

        // Transform users from plucked format {id: name} to array format [{id: id, name: name}]
        var users = [];
        if (usersData && typeof usersData === 'object') {
            Object.keys(usersData).forEach(function(id) {
                users.push({id: parseInt(id), name: usersData[id]});
            });
        }


        // Issue to type change - dynamically load users or suppliers
        $('#issue_to_type').change(function() {
            var type = $(this).val();
            var $issueToId = $('#issue_to_id');
            
            // Clear existing options
            $issueToId.empty();
            
            if (!type) {
                $issueToId.append('<option value="">{{ __("Select Type First") }}</option>');
                $issueToId.prop('disabled', true);
                return;
            }
            
            $issueToId.prop('disabled', false);
            $issueToId.append('<option value="">{{ __("Select") }}</option>');
            
            if (type === 'user') {
                if (users && users.length > 0) {
                    users.forEach(function(user) {
                        $issueToId.append('<option value="' + user.id + '">' + user.name + '</option>');
                    });
                } else {
                    $issueToId.append('<option value="">{{ __("No users available") }}</option>');
                }
            } else if (type === 'supplier') {
                if (suppliers && suppliers.length > 0) {
                    suppliers.forEach(function(supplier) {
                        $issueToId.append('<option value="' + supplier.id + '">' + supplier.name + '</option>');
                    });
                } else {
                    $issueToId.append('<option value="">{{ __("No suppliers available") }}</option>');
                }
            }
        });

        // Material select change - get available stock via AJAX
        $(document).on('change', '.material-select', function() {
            var materialId = $(this).val();
            var row = $(this).closest('tr');
            var $availableStock = row.find('.available-stock');
            var $unitName = row.find('.unit-name');
            var $quantityInput = row.find('.quantity-input');
            var $rateInput = row.find('.rate-input');
            
            // Reset values
            $availableStock.text('0').removeClass('bg-success bg-danger bg-warning').addClass('bg-secondary');
            $unitName.text('');
            $quantityInput.val('').attr('max', '');
            $rateInput.val('');
            
            if (materialId) {
                // Show loading state
                $availableStock.text('{{ __("Loading...") }}').removeClass('bg-secondary').addClass('bg-warning');
                
                $.ajax({
                    url: "{{ route('material-issues.get-available-stock') }}",
                    type: 'POST',
                    data: {
                        material_id: materialId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            var stock = parseFloat(response.available_stock) || 0;
                            $availableStock.text(stock);
                            
                            // Set max attribute for quantity validation
                            $quantityInput.attr('max', stock);
                            
                            // Color code based on stock level
                            $availableStock.removeClass('bg-secondary bg-warning bg-danger bg-success');
                            if (stock > 0) {
                                $availableStock.addClass('bg-success');
                            } else {
                                $availableStock.addClass('bg-danger');
                            }
                            
                            // Get unit name from selected option
                            var unitName = row.find('.material-select option:selected').data('unit');
                            $unitName.text(unitName || '');
                            
                            // Auto-fill rate if available in response
                            if (response.rate !== undefined) {
                                $rateInput.val(response.rate);
                            }
                        } else {
                            $availableStock.text('0').removeClass('bg-warning').addClass('bg-danger');
                            $unitName.text('');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        $availableStock.text('{{ __("Error") }}').removeClass('bg-warning').addClass('bg-danger');
                        $unitName.text('');
                        
                        // Show user-friendly error
                        if (xhr.status === 419) {
                            alert('{{ __("Session expired. Please refresh the page.") }}');
                        } else if (xhr.status === 500) {
                            alert('{{ __("Server error. Please try again.") }}');
                        }
                    }
                });
            }
        });

        // Quantity validation against available stock - prevent exceeding stock
        $(document).on('input change', '.quantity-input', function() {
            var $input = $(this);
            var quantity = parseFloat($input.val()) || 0;
            var maxStock = parseFloat($input.attr('max')) || 0;
            var $row = $input.closest('tr');
            var $availableStock = $row.find('.available-stock');
            
            if (maxStock === 0 && quantity > 0) {
                // No stock available, prevent any quantity
                $input.val(0);
                $input.addClass('is-invalid');
                $availableStock.removeClass('bg-success bg-secondary').addClass('bg-danger');
                
                // Show warning message
                alert('{{ __("No stock available for this material") }}');
            } else if (maxStock > 0 && quantity > maxStock) {
                // Cap the quantity to max available stock
                $input.val(maxStock);
                $input.addClass('is-invalid');
                $availableStock.removeClass('bg-success bg-secondary').addClass('bg-danger');
                
                // Show warning message
                alert('{{ __("Quantity cannot exceed available stock. Maximum available: ") }}' + maxStock);
            } else {
                $input.removeClass('is-invalid');
                if (maxStock > 0) {
                    $availableStock.removeClass('bg-danger').addClass('bg-success');
                }
            }
        });

        // Rate auto-fill (optional - can be enhanced with API)
        $(document).on('change', '.material-select', function() {
            var $row = $(this).closest('tr');
            var $rateInput = $row.find('.rate-input');
            
            // Clear rate when material changes
            $rateInput.val('');
            
            // Optional: Auto-fill rate from material if available
            // This can be enhanced to fetch from API
            var materialId = $(this).val();
            if (materialId && materials) {
                var material = materials.find(m => m.id == materialId);
                if (material && material.rate) {
                    $rateInput.val(material.rate);
                }
            }
        });

        // Add row
        $('#add-row').click(function() {
            var materialOptions = '<option value="">{{ __("Select Material") }}</option>';
            if (materials && materials.length > 0) {
                materials.forEach(function(material) {
                    var unitName = material.unit ? material.unit.name : '';
                    materialOptions += '<option value="' + material.id + '" data-unit="' + unitName + '">' + material.name + '</option>';
                });
            }

            var newRow = `
                <tr>
                    <td>
                        <select name="items[${rowIndex}][material_id]" class="form-select material-select" required>
                            ${materialOptions}
                        </select>
                    </td>
                    <td>
                        <span class="available-stock badge bg-secondary">0</span>
                        <span class="unit-name text-muted"></span>
                    </td>
                    <td>
                        <input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="number" name="items[${rowIndex}][rate]" class="form-control rate-input" step="0.01" min="0">
                    </td>
                    <td>
                        <input type="text" name="items[${rowIndex}][remarks]" class="form-control">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>
                    </td>
                </tr>
            `;
            $('#items-table tbody').append(newRow);
            rowIndex++;
        });

        // Remove row
        $(document).on('click', '.remove-row', function() {
            if ($('#items-table tbody tr').length > 1) {
                $(this).closest('tr').remove();
            } else {
                alert('{{ __("At least one item is required") }}');
            }
        });

        // Form validation
        $('#material-issue-form').submit(function(e) {
            var isValid = true;
            var errorMessages = [];
            
            // Validate issue_to_type and issue_to_id
            var issueToType = $('#issue_to_type').val();
            var issueToId = $('#issue_to_id').val();
            
            if (!issueToType) {
                errorMessages.push('{{ __("Please select Issue To Type") }}');
                isValid = false;
            }
            
            if (!issueToId) {
                errorMessages.push('{{ __("Please select Issue To") }}');
                isValid = false;
            }
            
            // Validate items
            $('#items-table tbody tr').each(function(index) {
                var materialId = $(this).find('.material-select').val();
                var quantity = parseFloat($(this).find('.quantity-input').val());
                var availableStock = parseFloat($(this).find('.available-stock').text()) || 0;
                
                if (!materialId) {
                    errorMessages.push('{{ __("Row") }} ' + (index + 1) + ': {{ __("Please select a material") }}');
                    isValid = false;
                    return false;
                }
                
                if (!quantity || quantity <= 0) {
                    errorMessages.push('{{ __("Row") }} ' + (index + 1) + ': {{ __("Quantity must be greater than zero") }}');
                    isValid = false;
                    return false;
                }
                
                if (availableStock === 0 && quantity > 0) {
                    errorMessages.push('{{ __("Row") }} ' + (index + 1) + ': {{ __("No stock available for this material") }}');
                    isValid = false;
                    return false;
                }
                
                if (availableStock > 0 && quantity > availableStock) {
                    errorMessages.push('{{ __("Row") }} ' + (index + 1) + ': {{ __("Insufficient stock. Available: ") }}' + availableStock + '{{ __(", Requested: ") }}' + quantity);
                    isValid = false;
                    return false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessages.join('\n'));
            }
        });
    });
</script>

