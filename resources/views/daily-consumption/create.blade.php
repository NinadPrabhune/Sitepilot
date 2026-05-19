{{ Form::open(['route' => 'daily-consumption.store', 'method' => 'POST', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
{{ Form::hidden('activity_id', $activity_id ?? null) }}
{{ Form::hidden('activity_completed_id', $activity_completed_id ?? null) }}
<div class="modal-body">
    <!-- First Row: Basic Information -->
    <div class="row mb-3">
        <div class="form-group col-md-4">
            {{ Form::label('consumption_number', __('Consumption Number'), ['class' => 'form-label']) }} <x-required />
            {{ Form::text('consumption_number', $nextConsumptionNumber, ['class' => 'form-control', 'readonly' => true, 'required']) }}
        </div>
        <div class="form-group col-md-4">
            {{ Form::label('consumption_date', __('Consumption Date'), ['class' => 'form-label']) }} <x-required />
            {{ Form::date('consumption_date', date('Y-m-d'), ['class' => 'form-control', 'required']) }}
        </div>
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }} <x-required />
            {{ Form::select('site_id', $sites->toArray(), $defaultSiteId ?? null, ['class' => 'form-control', 'required']) }}
        </div>
    </div>
@php
$consumption_type_arr_old = [

        'all' => 'All Material',
        'fuel' => 'Fuel',
    ];    
@endphp
    <!-- Second Row: Consumption Type and Machinery -->
    <div class="row mb-3">
        <div class="form-group col-md-3">
            {{ Form::label('consumption_type', __('Consumption Type 123'), ['class' => 'form-label']) }}<x-required />
            {{ Form::select('consumption_type', [
                'all' => 'All Material',                
            ], 'all', ['class' => 'form-control', 'required' => 'required', 'id' => 'consumption_type']) }}
        </div>
        
        <div class="form-group col-md-3" id="machinery_type_group" style="display: none;">
            {{ Form::label('machinery_type', __('Machinery Type'), ['class' => 'form-label']) }}<x-required />
            {{ Form::select('machinery_type', [
                'own' => 'Own',
                'rental' => 'Rental'
            ], null, ['class' => 'form-control', 'id' => 'machinery_type']) }}
        </div>
        
        <div class="form-group col-md-6" id="machinery_id_group" style="display: none;">
            {{ Form::label('machinery_id', __('Machinery'), ['class' => 'form-label']) }} <x-required />
            {{ Form::select('machinery_id', ['' => __('Select Machinery')] + $machineryOptions, null, ['class' => 'form-control', 'id' => 'machinery_id']) }}
        </div>
    </div>

    <!-- Third Row: File Upload -->
    <div class="row mb-3">
        <div class="form-group col-md-12">
            {{ Form::label('consumption_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('consumption_file', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx']) }}
            <small class="text-muted">{{ __('Allowed: pdf, jpg, jpeg, png, doc, docx') }}</small>
        </div>
    </div>

        <!-- Fourth Row: Consumption Details -->
    <div class="row mb-3">
        <div class="form-group col-md-12">
            <label class="form-label">{{ __('Consumption Details') }}</label>
            <button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">{{ __('Add Item') }}</button>
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
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal-footer">
    <a href="{{ route('daily-consumption.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
    {{ Form::submit(__('Create'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}

{{-- Scripts --}}
<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script>
$(document).ready(function () {
    let materialsFuel = @json($materials_fules);
    let materialsAll = @json($materials_all);
    let rowIndex = 0;

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
        const qty = item.quantity || 1;
        const unit = item.unit || 'unit';
        const remarks = item.remarks || '';
        const selectedMaterialId = item.material_id || '';

        const row = $('<tr>');
        row.append(`<td><select id="${uniqueId}" name="items[${rowIndex}][material_id]" class="form-control item-material" required>
            ${materialOptions.replace(`value="${selectedMaterialId}"`, `value="${selectedMaterialId}" selected`)}
        </select></td>`);
    
        row.append(`<td><div class="input-group"><input type="text" class="form-control item-stock" readonly value="0"/><span class="input-group-text item-stock-unit">unit</span></div></td>`);
    
        row.append(`<td><div class="input-group">
            <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-quantity" min="1" value="1" value="${qty}" required>
            <input type="hidden" name="items[${rowIndex}][unit]" class="item-unit" value="${unit}" required>
            <span class="input-group-text item-unit-label">${unit}</span>
        </div></td>`);
        row.append(`<td><input type="text" name="items[${rowIndex}][remarks]" class="form-control item-remarks" value="${remarks}"></td>`);
        row.append(`<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>`);

        $('#consumption-items-table tbody').append(row);
        rowIndex++;
    }

//    $('#add-item-row').on('click', function () {
//        const type = $('#consumption_type').val();
//        const materials = type === 'fuel' ? materialsFuel : materialsAll;
//        addItemRow({}, materials);
//    });
    
    
    $('#add-item-row').on('click', function () {
        const $lastRow = $('#consumption-items-table tbody tr').last();
        let canAdd = true;

        // Validate last row fields
        const material = $lastRow.find('.item-material').val();
        const quantity = $lastRow.find('.item-quantity').val();
//        const price = $lastRow.find('.item-price').val();

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

//        if (!price) {
//            $lastRow.find('.item-price').addClass('is-invalid');
//            canAdd = false;
//        } else {
//            $lastRow.find('.item-price').removeClass('is-invalid');
//        }

        // Validate all rows to prevent incomplete entries
        $('#consumption-items-table tbody tr').each(function () {
            const material = $(this).find('.item-material').val();
            const quantity = $(this).find('.item-quantity').val();
           

            if (!material || !quantity ) {
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
        if (typeof refreshRemainingStockDisplay === 'function') {
            refreshRemainingStockDisplay();
        }
    });

    $('#consumption-items-table').on('change', '.item-material', function () {
        const materialId = $(this).val();
        const row = $(this).closest('tr');
        const unitInput = row.find('.item-unit');
        const unitLabel = row.find('.item-unit-label');
        const itemStock = row.find('.item-stock');
        const itemStockUnit = row.find('.item-stock-unit');

        // Debug logging
        console.log('Material changed:', {
            materialId: materialId,
            materialsFuelCount: Object.keys(materialsFuel).length,
            materialsAllCount: Object.keys(materialsAll).length
        });

        // Merge both collections - ensure keys are strings for consistent lookup
        const allMaterials = { ...materialsFuel, ...materialsAll };
        
        // Convert materialId to string for consistent matching
        const materialKey = String(materialId);
        const material = allMaterials[materialKey];

        console.log('Material lookup result:', {
            materialKey: materialKey,
            materialFound: !!material,
            material: material
        });

        if (materialId && material) {
            // Update unit
            const unitName = material.unit || 'unit';
            unitInput.val(unitName);
            unitLabel.text(unitName);

            // Update current stock display - handle both total_qty and qty fields
            itemStockUnit.text(unitName);
            row.find('.item-quantity').val(row.find('.item-quantity').val() || 0);
            refreshRemainingStockDisplay();

            console.log('Stock updated:', {
                stockValue: stockValue,
                unit: unitName
            });

            // (Optional) If you want to auto-fill price or other fields, you can add here
            // row.find('.item-price').val(material.price);
            
            $(this).closest('tr').removeClass('table-danger');
        } else {
            unitInput.val('');
            unitLabel.text('unit');
            itemStock.val(0);
            itemStockUnit.text('unit');
            
            console.log('No material found, cleared fields');
        }       
        
        refreshMaterialDropdowns();
    });


    $('#consumption_type').on('change', function () {
        $('#consumption-items-table tbody').empty();
        const type = $(this).val();
        const materials = type === 'fuel' ? materialsFuel : materialsAll;
        addItemRow({}, materials);
    });  
    
    function loadStockBySite(siteId) {
    console.log('Loading stock for site:', siteId);
    
    $.ajax({
        url: '{{ route("ajax.getStockBySiteForDailyConsumption") }}',
        method: 'GET',
        data: { site_id: siteId },
        success: function (response) {
            console.log('Stock data received:', response);

            materialsFuel = {};
            materialsAll = {};

            // Handle both array and Collection responses
            const items = Array.isArray(response) ? response : (response.data || []);
            
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
                    materialsFuel[item.material_id] = materialData;
                } else {
                    materialsAll[item.material_id] = materialData;
                }
            });

            console.log('Processed materials:', {
                fuelsCount: Object.keys(materialsFuel).length,
                allCount: Object.keys(materialsAll).length
            });

            $('#consumption-items-table tbody').empty();

            const type = $('#consumption_type').val();
            const materials = type === 'fuel' ? materialsFuel : materialsAll;

            addItemRow({}, materials);
            refreshRemainingStockDisplay();
        },
        error: function (xhr) {
            console.error('Error fetching stock:', xhr.responseText);
            materialsFuel = {};
            materialsAll = {};
            
            // Still add an empty row even if stock fetch fails
            const type = $('#consumption_type').val();
            const materials = type === 'fuel' ? materialsFuel : materialsAll;
            addItemRow({}, materials);
        }
    });
}
        
    
    function getAllMaterialsMap() {
        return { ...materialsFuel, ...materialsAll };
    }

    function refreshRemainingStockDisplay() {
        const allMaterials = getAllMaterialsMap();
        const usedByMaterial = {};
        $('#consumption-items-table tbody tr').each(function () {
            const materialId = $(this).find('.item-material').val();
            if (!materialId) return;
            usedByMaterial[materialId] = (usedByMaterial[materialId] || 0) + (parseFloat($(this).find('.item-quantity').val()) || 0);
        });
        $('#consumption-items-table tbody tr').each(function () {
            const row = $(this);
            const materialId = row.find('.item-material').val();
            const base = allMaterials[materialId]?.total_qty || 0;
            const remaining = materialId ? Math.max(0, base - (usedByMaterial[materialId] || 0)) : 0;
            row.find('.item-stock').val(remaining);
        });
    }

    function getAvailableQtyForRow(row, materialId) {
        const allMaterials = getAllMaterialsMap();
        const base = allMaterials[materialId]?.total_qty || 0;
        const rowEl = row[0] || row;
        let otherQty = 0;
        $('#consumption-items-table tbody tr').each(function () {
            if (this === rowEl) return;
            if ($(this).find('.item-material').val() == materialId) {
                otherQty += parseFloat($(this).find('.item-quantity').val()) || 0;
            }
        });
        return Math.max(0, base - otherQty);
    }

    $('#site_id').on('change', function () {
    loadStockBySite($(this).val());
});
    
    
    $('#consumption-items-table').on('input', '.item-quantity', function () {
    const row = $(this).closest('tr');
    const qtyInput = row.find('.item-quantity');
    const materialId = row.find('.item-material').val();
    const available = getAvailableQtyForRow(row, materialId);
    const enteredQty = parseFloat(qtyInput.val()) || 0;

    if (enteredQty > available) {
        alert(`Quantity exceeds available stock (${available})`);
        qtyInput.val(available > 0 ? available : 0);
        qtyInput[0].setCustomValidity('Quantity exceeds available stock');
    } else {
        qtyInput.removeClass('is-invalid');
        qtyInput[0].setCustomValidity('');
    }
    refreshRemainingStockDisplay();
});


function refreshMaterialDropdowns() {
    const selectedIds = [];

    // Collect all selected material IDs
    $('.item-material').each(function () {
        const val = $(this).val();
        if (val) selectedIds.push(val);
    });

//    // Merge both collections (fuel + all)
//    const allMaterials = { ...materialsFuel, ...materialsAll };
    
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
    // Initialize first row
    addItemRow({}, materialsAll);
    
    
    const siteId = $('#site_id').val() || 1; // fallback to 1
    loadStockBySite(siteId);
});
</script>
<script>
$(document).ready(function () {
    function toggleMachineryFields() {
        const consumptionType = $('#consumption_type').val();
        const machineryType = $('#machinery_type').val();

        if (consumptionType === 'fuel') {
            $('#machinery_type_group').show();
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
            $('#machinery_id').removeAttr('required');
        }
    }

    $('#consumption_type, #machinery_type').on('change', toggleMachineryFields);
    toggleMachineryFields(); // Initial check

    // Prevent form submission if any .item-stock value is empty or zero
    $('form').on('submit', function(e) {
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false
        });
        
        // Check if at least one row exists in the consumption-items-table
        const tableRows = $('#consumption-items-table tbody tr');
        
        if (tableRows.length === 0) {
            e.preventDefault();
            swalWithBootstrapButtons.fire(
                'Error!',
                'Please add at least one item row before submitting.',
                'error'
            );
            $('.btn-primary').prop('disabled', false);
            return false;
        }
        
        let hasValidStock = true;
        let firstInvalidStock = null;
        
        $('.item-stock').each(function() {
            const stockValue = $(this).val();
            // Check if empty, zero, or not a valid number
            if (stockValue === '' || stockValue === '0' || parseFloat(stockValue) <= 0) {
                hasValidStock = false;
                if (!firstInvalidStock) {
                    firstInvalidStock = $(this);
                }
            }
        });
        
        if (!hasValidStock) {
            e.preventDefault();
            
            swalWithBootstrapButtons.fire(
                        'Error!',
                        'Please ensure all materials have valid stock (greater than 0) before submitting.',
                        'error'
                    );
            
            if (firstInvalidStock) {
                // Scroll to the first invalid stock field
                $('html, body').animate({
                    scrollTop: firstInvalidStock.closest('tr').offset().top - 100
                }, 300);
            }
            $('.btn-primary').prop('disabled', false);
            return false;
        }
    });
});
</script>
