@php($restrictMode = request()->has('material_id'))
{{ Form::open(['route' => 'material-transfer.store', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        {{-- Record Number --}}
        <div class="form-group col-md-6 {{ $restrictMode ? 'd-none' : '' }}">
            {{ Form::label('record_number', __('Record Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('record_number', $nextRecordNumber ?? '', ['class' => 'form-control', 'required' => true, 'readonly' => true, 'placeholder' => 'Auto-generated']) }}
        </div>

        {{-- Record Date --}}
        <div class="form-group col-md-6 {{ $restrictMode ? 'd-none' : '' }}">
            {{ Form::label('record_date', __('Record Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('record_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
        </div>

        
        
        {{-- From Site --}}
        @php($activeProjectId = array_key_exists(0, array_keys($ActiveProject->toArray() ?? [])) ? array_keys($ActiveProject->toArray())[0] : null)
        <div class="form-group col-md-6 {{ $restrictMode ? 'd-none' : '' }}">
            {{ Form::label('from_site_id', __('From Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('from_site_id', $ActiveProject, null, ['class' => 'form-control', 'required' => true, 'id' => 'from_site_id']) }}
        </div>
        @if($restrictMode)
            <input type="hidden" name="from_site_id" value="{{ $activeProjectId ?? getActiveProject() }}">
            <input type="hidden" name="record_date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
        @endif

        {{-- To Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('to_site_id', __('To Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('to_site_id', ['' => 'Select Site'] + $sites->toArray(), null, ['class' => 'form-control', 'required' => true]) }}
        </div>


        {{-- Material Table --}}
        <div class="form-group col-md-12">
            <label class="form-label">{{ __('Record Material') }}</label>
            <button type="button" class="btn btn-sm btn-primary float-end {{ $restrictMode ? 'd-none' : '' }}" id="add-item-row" {{ $restrictMode ? 'disabled' : '' }}>{{ __('Add Item') }}</button>
            <table class="table table-bordered" id="record-items-table">
                <thead>
                    <tr>
                        <th>{{ __('Material') }}</th>
                        <th>Current Stock<br>Quantity | Unit</th>
                        <th>{{ __('Quantity | Unit') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Subtotal') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div class="text-end mt-2" id="total_amount_div">
                <label for="total_amount" class="form-label fw-bold">{{ __('Total Amount') }}:</label>
                <input type="text" id="total_amount" name="total_amount" class="form-control d-inline-block" style="width:150px;font-weight:bold;" readonly value="0.00" />
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}


@if($restrictMode)
<style>
    #record-items-table thead th:nth-child(4),
    #record-items-table thead th:nth-child(5),
    #record-items-table thead th:nth-child(6),
    #record-items-table tbody td:nth-child(4),
    #record-items-table tbody td:nth-child(5),
    #record-items-table tbody td:nth-child(6),
    #total_amount_div {
        display: none !important;
    }
</style>
@endif

<script>
    $(document).ready(function () {
        let siteStock = {}; // material_id => { total_qty, unit }
        const restrictMode = {{ $restrictMode ? 'true' : 'false' }};
        const prefillMaterialId = {!! json_encode(request('material_id')) !!};

        function recalculateSubtotal(row) {
            let qty = parseFloat(row.find('.item-quantity').val()) || 0;
            let price = parseFloat(row.find('.item-price').val()) || 0;
            row.find('.item-subtotal').val((qty * price).toFixed(2));
            recalculateTotalAmount();
        }

        function recalculateTotalAmount() {
            let total = 0;
            $('#record-items-table .item-subtotal').each(function () {
                total += parseFloat($(this).val()) || 0;
            });
            $('#total_amount').val(total.toFixed(2));
        }

        function addItemRow() {
            const materials = @json($materials);
                    let index = $('#record-items-table tbody tr').length;

            // Build material options from siteStock only
            let materialOptions = '';
            if (restrictMode && prefillMaterialId) {
                const mat = materials[prefillMaterialId];
                const label = mat ? mat.name : 'Selected Material';
                materialOptions = `<option value="${prefillMaterialId}">${label}</option>`;
            } else {
                materialOptions = '<option value="">Select Material</option>';
                Object.keys(siteStock).forEach(id => {
                    if (materials[id]) {
                        materialOptions += `<option value="${id}">${materials[id].name}</option>`;
                    }
                });
            }

            let row = $('<tr>');

            // Material dropdown
            row.append(`<td>
        <select name="items[${index}][material_id]" class="form-control item-material" required>
            ${materialOptions}
        </select>
    </td>`);

            // Stock display with input-group
            row.append(`<td>
        <div class="input-group">
            <input type="text" class="form-control item-stock" readonly value="0" />
            <span class="input-group-text item-stock-unit">unit</span>
        </div>
    </td>`);

            // Quantity input with unit
            row.append(`<td>
        <div class="input-group">
            <input type="number" name="items[${index}][quantity]" class="form-control item-quantity" min="1" value="1" required />
            <input type="hidden" name="items[${index}][unit]" class="form-control item-unit" value="" required />
            <span class="input-group-text item-unit-label">unit</span>
        </div>
    </td>`);

            // Price and subtotal
            row.append(`<td><input type="text" name="items[${index}][price]" class="form-control item-price" readonly value="1" /></td>`);
            row.append(`<td><input type="text" name="items[${index}][subtotal]" class="form-control item-subtotal" readonly value="0.00" /></td>`);
            row.append(`<td><button type="button" class="btn btn-danger btn-sm remove-item-row"${restrictMode ? ' style="display:none;"' : ''}>&times;</button></td>`);

            $('#record-items-table tbody').append(row);

            recalculateTotalAmount();

            refreshMaterialDropdowns();
        }


//        $('#add-item-row').on('click', function () {
//            addItemRow();
//        });
        
        $('#add-item-row').on('click', function () {
            const $lastRow = $('#record-items-table tbody tr').last();
            let canAdd = true;

            const material = $lastRow.find('.item-material').val();
            const quantity = $lastRow.find('.item-quantity').val();
            const price = $lastRow.find('.item-price').val();

            // Validate material
            if (!material) {
                $lastRow.find('.item-material').addClass('is-invalid');
                canAdd = false;
            } else {
                $lastRow.find('.item-material').removeClass('is-invalid');
            }

            // Validate quantity
            if (!quantity) {
                $lastRow.find('.item-quantity').addClass('is-invalid');
                canAdd = false;
            } else {
                $lastRow.find('.item-quantity').removeClass('is-invalid');
            }

            // Validate price
            if (!price) {
                $lastRow.find('.item-price').addClass('is-invalid');
                canAdd = false;
            } else {
                $lastRow.find('.item-price').removeClass('is-invalid');
            }

//            // Highlight row if invalid
//            if (!canAdd) {
//                $lastRow.addClass('table-danger');
//                alert('Please complete the current item row before adding a new one.');
//            } else {
//                $lastRow.removeClass('table-danger');
//                addItemRow();
//            }
            
            
             
                $('#record-items-table tbody tr').each(function() {
                var material = $(this).find('.item-material').val();
                var quantity = $(this).find('.item-quantity').val();
                var price = $(this).find('.item-price').val();
                if (!material || !quantity || !price) {
                canAdd = false;
                $(this).addClass('table-danger');
                } else {
                $(this).removeClass('table-danger');
                }
                });
                if (canAdd) {
                addItemRow();
                }
        });
        
        
        
        

        $('#record-items-table').on('change', '.item-material', function () {
            const row = $(this).closest('tr');
            const materialId = $(this).val();

            if (siteStock[materialId]) {
                const material = siteStock[materialId];
                row.find('.item-price').val(parseFloat(material.price || 0).toFixed(2)); // optional
                row.find('.item-unit').val(material.unit);
                row.find('.item-unit-label').text(material.unit);
                row.find('.item-stock').val(material.total_qty);
                row.find('.item-stock-unit').text(material.unit);
                row.find('.item-quantity').attr('max', material.total_qty);
            } else {
                row.find('.item-price').val('0.00');
                row.find('.item-unit').val('');
                row.find('.item-unit-label').text('unit');
                row.find('.item-stock').val('0');
                row.find('.item-stock-unit').text('unit');
                row.find('.item-quantity').removeAttr('max');
            }

            refreshMaterialDropdowns();
            recalculateSubtotal(row);
        });


        $('#record-items-table').on('input', '.item-quantity', function () {
            const row = $(this).closest('tr');
            const qtyInput = row.find('.item-quantity');
            const materialId = row.find('.item-material').val();
            const available = (parseFloat(row.find('.item-stock').val()) || 0) || (siteStock[materialId]?.total_qty || 0);
            const enteredQty = parseFloat(qtyInput.val()) || 0;

            if (enteredQty > available) {
                alert('Quantity exceeds available stock');
                qtyInput.val(available);
//                qtyInput.addClass('is-invalid');
                qtyInput[0].setCustomValidity('Quantity exceeds available stock');
            } else {
                // Clear error
                qtyInput.removeClass('is-invalid');
                qtyInput[0].setCustomValidity('');
            }

            recalculateSubtotal(row);
        });


        $('#record-items-table').on('click', '.remove-item-row', function () {
            $(this).closest('tr').remove();
            recalculateTotalAmount();
        });

        $('#from_site_id').on('change', function () {
            
            // Reset to_site_id
                $('#to_site_id').val('');
            
            const selectedFromSiteId = $(this).val();

            // Enable all options first
            $('#to_site_id option').prop('disabled', false);

            // Disable the selected from_site_id in to_site_id
            if (selectedFromSiteId) {
                $('#to_site_id option').each(function () {
                    if ($(this).val() === selectedFromSiteId) {
                        $(this).prop('disabled', true);
                    }
                });

                // If currently selected to_site_id is now disabled, reset it
                if ($('#to_site_id').val() === selectedFromSiteId) {
                    $('#to_site_id').val('');
                }
            }
            
            const siteId = $(this).val();

            $.ajax({
                url: '{{ route("ajax.getStockBySite") }}',
                method: 'GET',
                data: {site_id: siteId},
                success: function (response) {
                    siteStock = {};

                    // Build siteStock object from response
                    response.forEach(item => {
                        siteStock[item.material_id] = {
                            name: item.material_name,
                            unit: item.unit_name || 'unit',
                            price: item.material_price,
                            total_qty: parseFloat(item.total_qty) || 0
                        };
                    });

                    // Remove all existing item rows
                    $('#record-items-table tbody').empty();

                    // Add one fresh empty row
                    addItemRow();

                    if (restrictMode && prefillMaterialId) {
                        const $row = $('#record-items-table tbody tr').first();
                        $row.find('.item-material').val(prefillMaterialId).trigger('change');
                    }
                },
                error: function (xhr) {
                    console.error('Error fetching stock:', xhr.responseText);
                    siteStock = {};
                }
            });
        });

        function refreshMaterialDropdowns() {
            const selectedIds = [];

            // Collect all selected material IDs
            $('.item-material').each(function () {
                const val = $(this).val();
                if (val)
                    selectedIds.push(val);
            });

            // Rebuild each dropdown
            $('.item-material').each(function () {
                const $select = $(this);
                const currentVal = $select.val();

                if (restrictMode && prefillMaterialId) {
                    const mat = (@json($materials))[prefillMaterialId];
                    const label = mat ? mat.name : 'Selected Material';
                    $select.empty().append(`<option value="${prefillMaterialId}">${label}</option>`);
                    $select.val(prefillMaterialId);
                    return; // skip full rebuild in restricted mode
                }
                $select.empty().append('<option value="">Select Material</option>');

                Object.entries(siteStock).forEach(([id, material]) => {
                    const isSelectedElsewhere = selectedIds.includes(id) && id !== currentVal;
                    const disabled = isSelectedElsewhere ? 'disabled' : '';
                    $select.append(`<option value="${id}" ${disabled}>${material.name}</option>`);
                });

                $select.val(currentVal);
            });
        }


        // Initial row
        addItemRow();
        recalculateTotalAmount();
        // Trigger change on page load
        // If restrict mode, set from_site_id to active project and trigger change
        if (restrictMode) {
            const activeProjectId = {{ json_encode($activeProjectId ?? getActiveProject()) }};
            $('#from_site_id').val(activeProjectId);
        }
        $('#from_site_id').trigger('change');
    });
</script>
