{{ Form::model($daily_consumption, ['route' => ['daily-consumption.update', $daily_consumption->id], 'method' => 'PUT', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <!-- First Row: Basic Information -->
    <div class="row mb-3">
        <div class="form-group col-md-4">
            {{ Form::label('consumption_number', __('Consumption Number'), ['class' => 'form-label']) }} <x-required />
            {{ Form::text('consumption_number', null, ['class' => 'form-control', 'readonly' => true, 'required']) }}
        </div>
        <div class="form-group col-md-4">
            {{ Form::label('consumption_date', __('Consumption Date'), ['class' => 'form-label']) }} <x-required />
            {{ Form::date('consumption_date', $daily_consumption->consumption_date ?? date('Y-m-d'), ['class' => 'form-control', 'required']) }}
        </div>
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }} <x-required />
            {{ Form::select('site_id', $sites->toArray(), $daily_consumption->site_id, ['class' => 'form-control', 'required']) }}
        </div>
    </div>

    <!-- Second Row: Consumption Type and Machinery -->
    <div class="row mb-3">
        <div class="form-group col-md-3">
            {{ Form::label('consumption_type', __('Consumption Type'), ['class' => 'form-label']) }} <x-required />
            {{ Form::select('consumption_type', ['all' => 'All Material', 'fuel' => 'Fuel'], $daily_consumption->consumption_type, ['class' => 'form-control', 'required', 'id' => 'consumption_type']) }}
        </div>
        
        <div class="form-group col-md-3" id="machinery_type_group" style="display: none;">
            {{ Form::label('machinery_type', __('Machinery Type'), ['class' => 'form-label']) }} <x-required />
            {{ Form::select('machinery_type', ['own' => 'Own', 'rental' => 'Rental'], $daily_consumption->machinery_type, ['class' => 'form-control', 'id' => 'machinery_type']) }}
        </div>
        
        <div class="form-group col-md-6" id="machinery_id_group" style="display: none;">
            {{ Form::label('machinery_id', __('Machinery'), ['class' => 'form-label']) }} <x-required />
            {{ Form::select('machinery_id', ['' => __('Select Machinery')] + $machineryOptions, $daily_consumption->machinery_id, ['class' => 'form-control', 'id' => 'machinery_id']) }}
        </div>
    </div>

    <!-- Third Row: File Upload -->
    <div class="row mb-3">
        <div class="form-group col-md-12">
            {{ Form::label('consumption_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('consumption_file', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx']) }}
            <small class="text-muted">{{ __('Allowed: pdf, jpg, jpeg, png, doc, docx') }}</small>
            
            {{-- Master file --}}
            @if($daily_consumption->consumption_file)
            <br>
            <a href="{{ asset('storage/'.$daily_consumption->consumption_file) }}" target="_blank">
                Download Current Consumption File
            </a>
            @endif
        </div>
    </div>

        <!-- Fourth Row: Consumption Details -->
    <div class="row mb-3">
        <div class="form-group col-md-12">
            <label class="form-label">{{ __('Consumption Details') }}</label>
            @if($daily_consumption->ledger_entry_id)
                <div class="alert alert-warning">
                    <strong>⚠️ Ledger entry exists</strong> - Quantity fields are locked. Use reversal to modify.
                </div>
            @endif
            <button type="button" class="btn btn-sm btn-primary float-end" @if($daily_consumption->ledger_entry_id) disabled @endif id="add-item-row">{{ __('Add Item') }}</button>
            <table class="table table-bordered mt-2" id="consumption-items-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">{{ __('Material') }}</th>
                        <th>Current Stock<br>Quantity | Unit</th>
                        <th>{{ __('Quantity | Unit') }}</th>
                        <th>{{ __('Remarks') }}</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody>
                    
                    
                    @foreach($daily_consumption->details as $index => $detail)
                    @php
                    $uniqueId = 'material_' . uniqid();
                    $materialSource = $daily_consumption->consumption_type === 'fuel' ? $materials_fules : $materials_all;
                    
                    // Debug: Show material data structure
                    if ($index === 0) {
                        echo '<script>console.log("Material Source:", ' . json_encode($materialSource) . ');</script>';
                        echo '<script>console.log("Current Detail:", ' . json_encode($detail) . ');</script>';
                        echo '<script>console.log("Expected Selected ID:", ' . $detail->material_id . ');</script>';
                    }
                    @endphp
                    <tr>
                        <td>
                            <select id="{{ $uniqueId }}" name="items[{{ $index }}][material_id]" class="form-control item-material " required>
                                <option value="">{{ __('Select Material') }}</option>
                                @foreach($materialSource as $id => $material)
                                <option value="{{ $id }}" {{ $detail->material_id == $id ? 'selected' : '' }}>{{ $material['name'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="input-group">
                                <input type="text" class="form-control item-stock" readonly value="{{ $materialSource[$detail->material_id]['total_qty'] ?? 0 }}" />
                                <span class="input-group-text item-stock-unit">{{ $materialSource[$detail->material_id]['unit'] ?? 'unit' }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group">
                                <input type="number" name="items[{{ $index }}][quantity]" class="form-control item-quantity" min="1" step="1" value="{{ $detail->quantity }}" @if($daily_consumption->ledger_entry_id) readonly @endif required>
                                <input type="hidden" name="items[{{ $index }}][unit]" class="form-control item-unit" value="{{ $detail->unit }}" required>
                                <span class="input-group-text item-unit-label">{{ $detail->unit }}</span>
                            </div>
                        </td>
                        <td>
                            <input type="text" name="items[{{ $index }}][remarks]" class="form-control item-remarks" value="{{ $detail->remarks }}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button>
                        </td>
                    </tr>
                    @endforeach


                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-footer">
    <a href="{{ route('daily-consumption.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
    {{ Form::submit(__('Update'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}


<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script>
    $(document).ready(function () {
        let materialsFuel = @json($materials_fules);
        let materialsAll = @json($materials_all);
        let daily_consumption_masters_id = "{{ $daily_consumption_masters_id }}";
        
        function getMaterialOptions(materials) {
            let options = '<option value="">{{ __("Select Material") }}</option>';
            $.each(materials, function (id, material) {
                options += `<option value="${id}">${material.name}</option>`;
            });
            return options;
        }

    function addItemRow(item = {}, materials = materialsAll) {
        const materialOptions = getMaterialOptions(materials);
        const uniqueId = 'material_' + Date.now();
        const index = $('#consumption-items-table tbody tr').length; // current row index
        const qty = item.quantity || 1;
        const unit = item.unit || 'unit';
        const remarks = item.remarks || '';
        const selectedMaterialId = item.material_id || '';
        const row = $('<tr>');
        row.append(`<td><select id="${uniqueId}" name="items[${index}][material_id]" class="form-control item-material " searchenabled="true" required>
            ${materialOptions.replace(`value="${selectedMaterialId}"`, `value="${selectedMaterialId}" selected`)}
        </select></td>`);
        // Stock display with input-group
        row.append(`<td>
            <div class="input-group">
                    <input type="text" class="form-control item-stock" readonly value="0" />
                    <span class="input-group-text item-stock-unit">unit</span>
                </div>
            </td>`);
        row.append(`<td><div class="input-group">
            <input type="number" name="items[${index}][quantity]" class="form-control item-quantity" min="1" step="1" value="${qty}" required>
            <input type="hidden" name="items[${index}][unit]" class="form-control item-unit" value="${unit}" required>
            <span class="input-group-text item-unit-label">${unit}</span>
        </div></td>`);
        row.append(`<td><input type="text" name="items[${index}][remarks]" class="form-control item-remarks" value="${remarks}"></td>`);
        row.append(`<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>`);
        $('#consumption-items-table tbody').append(row);
        choices(uniqueId);
    }

    $('#add-item-row').on('click', function () {
        const $lastRow = $('#consumption-items-table tbody tr').last();
        let canAdd = true;
        // Validate last row fields
        const material = $lastRow.find('.item-material').val();
        const quantity = $lastRow.find('.item-quantity').val();
        
        if (!material) {
            $lastRow.find('.item-material').addClass('is-invalid');
            canAdd = false;
        } else {
            $lastRow.find('.item-material').removeClass('is-invalid');
        }

        if (!quantity) {
            $lastRow.find('.item-quantity').addClass('is-invalid');
            canAdd = false;
        } else {
            $lastRow.find('.item-quantity').removeClass('is-invalid');
        }

        // Validate all rows to prevent incomplete entries
        $('#consumption-items-table tbody tr').each(function () {
            const material = $(this).find('.item-material').val();
            const quantity = $(this).find('.item-quantity').val();
            if (!material || !quantity) {
                canAdd = false;
                $(this).addClass('table-danger');
            } else {
                $(this).removeClass('table-danger');
            }
        });
        
        // Only add a new row if all validations pass
        if (canAdd) {
            const type = $('#consumption_type').val();
            const materials = type === 'fuel' ? materialsFuel : materialsAll;
            addItemRow({}, materials);
            refreshMaterialDropdowns();
        }
    });
    
    $('#consumption-items-table').on('click', '.remove-item-row', function () {
            const tableRows = $('#consumption-items-table tbody tr');
            // Prevent removing the last remaining row
            if (tableRows.length <= 1) {
                alert('Cannot remove the last remaining row. At least one item is required.');
                return;
            }
            $(this).closest('tr').remove();
            refreshMaterialDropdowns();
        });
        
        $('#consumption-items-table').on('change', '.item-material', function () {
            const materialId = $(this).val();
            const row = $(this).closest('tr');
            const unitInput = row.find('.item-unit');
            const unitLabel = row.find('.item-unit-label');
            const itemStock = row.find('.item-stock');
            const itemStockUnit = row.find('.item-stock-unit');
            
            // Merge both collections - ensure keys are strings for consistent lookup
            const allMaterials = { ...materialsFuel, ...materialsAll };
            
            // Convert materialId to string for consistent matching
            const materialKey = String(materialId);
            const material = allMaterials[materialKey];
            
            if (materialId && material) {
                // Update unit
                const unitName = material.unit || 'unit';
                unitInput.val(unitName);
                unitLabel.text(unitName);
                
                // Update current stock display - handle both total_qty and qty fields
                const stockValue = material.total_qty !== undefined ? material.total_qty : (material.qty || 0);
                itemStock.val(stockValue);
                itemStockUnit.text(unitName);
                
                // (Optional) If you want to auto-fill price or other fields, you can add here
                // row.find('.item-price').val(material.price);

                $(this).closest('tr').removeClass('table-danger');
            } else {
                unitInput.val('');
                unitLabel.text('unit');
                itemStock.val(0);
                itemStockUnit.text('unit');
            }

            refreshMaterialDropdowns();
        });
        
        function toggleMachineryFields() {
            const consumptionType = $('#consumption_type').val();
            const machineryType = $('#machinery_type').val();
            
            if (consumptionType === 'fuel') {
                $('#machinery_type_group').show();
                $('#machinery_type').attr('required', true);
                
                if (machineryType === 'own') {
                    $('#machinery_id_group').show();
                    $('#machinery_id').attr('required', true);
                } else {
                    $('#machinery_id_group').hide();
                    $('#machinery_id').removeAttr('required');
                }
            } else {
                $('#machinery_type_group').hide();
                $('#machinery_id_group').hide();
                $('#machinery_type').removeAttr('required');
                $('#machinery_id').removeAttr('required');
            }
        }

        $('#consumption_type').on('change', function () {
            $('#consumption-items-table tbody').empty();
            const type = $(this).val();
            const materials = type === 'fuel' ? materialsFuel : materialsAll;
            addItemRow({}, materials);
        });
        
        $('#consumption_type, #machinery_type').on('change', toggleMachineryFields);
        toggleMachineryFields();
        choices(); // initialize Choices.js globally


    $('#site_id').on('change', function () {
            const siteId = $(this).val();
            $.ajax({
                url: '{{ route("ajax.getStockBySiteForDailyConsumptionEdit") }}',
                method: 'GET',
                data: { site_id: siteId, daily_consumption_masters_id: daily_consumption_masters_id },
                success: function (response) {
                    // Reset arrays
                    materialsFuel = {};
                    materialsAll = {};
                    
                    // Handle both array and Collection responses
                    const items = Array.isArray(response) ? response : (response.data || []);
                    
                    // Build materialsFuel and materialsAll objects from response
                    items.forEach(item => {
                        const materialData = {
                            name: item.material_name,
                            unit: item.unit_name || 'unit',
                            price: item.material_price,
                            total_qty: parseFloat(item.total_qty) || 0,
                            category_id: item.category_id,
                            category_name: item.category_name,
                        };
                        
                        if (parseInt(item.category_id) === 2) {
                            // Category 2 → Fuel
                            materialsFuel[item.material_id] = materialData;
                        } else {
                            // All other categories
                            materialsAll[item.material_id] = materialData;
                        }
                    });
                    
                    // Remove all existing item rows
                    $('#consumption-items-table tbody').empty();
                    
                    // Add one fresh empty row (optional)
                    // addItemRow();

                    const type = $('#consumption_type').val();
                    const materials = type === 'fuel' ? materialsFuel : materialsAll;
                    addItemRow({}, materials);
                },
                error: function (xhr) {
                    console.error('Error fetching stock:', xhr.responseText);
                    materialsFuel = {};
                    materialsAll = {};
                }
            });
        });
        
        $('#consumption-items-table').on('input', '.item-quantity', function () {
            const row = $(this).closest('tr');
            const qtyInput = row.find('.item-quantity');
            const materialId = row.find('.item-material').val();
            // Merge both collections if you're using materialsFuel/materialsAll
            const allMaterials = { ...materialsFuel, ...materialsAll };
            const available = allMaterials[materialId]?.total_qty || 0;
            const enteredQty = parseFloat(qtyInput.val()) || 0;
            if (enteredQty > available) {
                // Show error feedback
                alert(`Quantity exceeds available stock (${available})`);
                // Reset quantity to max available or 1
                qtyInput.val(available > 0 ? available : 1);
                // Mark invalid for HTML5 validation
                // qtyInput.addClass('is-invalid');
                qtyInput[0].setCustomValidity('Quantity exceeds available stock');
            } else {
                // Clear error state
                qtyInput.removeClass('is-invalid');
                qtyInput[0].setCustomValidity('');
            }
        });
        
        function refreshMaterialDropdowns() {
            const selectedIds = [];
            // Collect all selected material IDs
            $('.item-material').each(function () {
                const val = $(this).val();
                if (val) selectedIds.push(val);
            });
            
            // Merge both collections (fuel + all)
            // const allMaterials = { ...materialsFuel, ...materialsAll };

            const type = $('#consumption_type').val();
            const materials = type === 'fuel' ? materialsFuel : materialsAll;
            
            // Rebuild each dropdown
            $('.item-material').each(function () {
                const $select = $(this);
                const currentVal = $select.val();
                $select.empty().append('<option value="">Select Material</option>');
                Object.entries(materials).forEach(([id, material]) => {
                    const isSelectedElsewhere = selectedIds.includes(id) && id !== currentVal;
                    const disabled = isSelectedElsewhere ? 'disabled' : '';
                    $select.append(`<option value="${id}" ${disabled}>${material.name}</option>`);
                });
                // Restore current selection if still valid
                if (currentVal) {
                    $select.val(currentVal);
                }
            });
        }
        
        // Form submission validation
        $('form').on('submit', function (e) {
            let valid = true;
            $('#consumption-items-table tbody tr').each(function () {
                const material = $(this).find('.item-material').val();
                const quantity = $(this).find('.item-quantity').val();
                if (!material || !quantity) {
                    $(this).addClass('table-danger');
                    valid = false;
                } else {
                    $(this).removeClass('table-danger');
                }
            });
            if (!valid) {
                e.preventDefault();
                alert('Please fill all required fields in item rows.');
            }
        });
    });
</script>