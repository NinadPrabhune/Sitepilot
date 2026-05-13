
{{ Form::model($transfer, ['route' => ['material-transfer.update', $transfer->id], 'method' => 'PUT', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        {{-- Record Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('record_number', 'Record Number', ['class' => 'form-label']) }}<x-required />
            {{ Form::text('record_number', null, ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- Record Date --}}
        <div class="form-group col-md-6">
            {{ Form::label('record_date', 'Record Date', ['class' => 'form-label']) }}<x-required />
            {{ Form::date('record_date',  \Carbon\Carbon::parse($transfer->record_date)->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- From Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('from_site_id', 'From Site', ['class' => 'form-label']) }}<x-required />
            {{ Form::select('from_site_id', $from_site_id, $transfer->from_site_id, ['class' => 'form-control', 'required' => true, 'id' => 'from_site_id']) }}
        </div>

        {{-- To Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('to_site_id', 'To Site', ['class' => 'form-label']) }}<x-required />
            {{ Form::select('to_site_id', ['' => 'Select Site'] + $sites->toArray(), (string) $transfer->to_site_id, ['class' => 'form-control', 'required' => true, 'id' => 'to_site_id']) }}

        </div>

        
        
        
        {{-- Material Table --}}
        <div class="form-group col-md-12">
            <label class="form-label">Record Material</label>
            <!--<button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">Add Item</button>-->
            <table class="table table-bordered" id="record-items-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Current Stock<br>Quantity | Unit</th>
                        <th>Quantity | Unit</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    
                    
                    
                    @foreach ($transfer->items as $index => $item)
                    
                   
                    <tr>
                        <td>
                            <select name="items[{{ $index }}][material_id]" class="form-control item-material" disabled>
                                <option value="{{ $item->material_id }}">
                                    {{ $materials[$item->material_id]['name'] ?? 'Unknown' }}
                                </option>
                            </select>

                        </td>
                        <td>
                            <div class="input-group">
                                <input type="text" class="form-control item-stock" readonly value="{{ $siteStock[$item->material_id]['total_qty'] ?? 0 }}" />
                                <span class="input-group-text item-stock-unit">{{ $siteStock[$item->material_id]['unit'] ?? 'unit' }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group">
                                <input type="number" name="items[{{ $index }}][quantity]" class="form-control item-quantity" min="1" value="{{ $item->quantity }}" required />
                                <input type="hidden" name="items[{{ $index }}][unit]" class="form-control item-unit" value="{{ $item->unit }}" required />
                                <span class="input-group-text item-unit-label">{{ $item->unit }}</span>
                            </div>
                        </td>
                        <td><input type="text" name="items[{{ $index }}][price]" class="form-control item-price" min="1.00" step="1.00" readonly value="{{ $item->price }}" /></td>
                        <td><input type="text" name="items[{{ $index }}][subtotal]" class="form-control item-subtotal" readonly value="{{ $item->subtotal }}" /></td>
                        <!--<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>-->
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="text-end mt-2">
                <label for="total_amount" class="form-label fw-bold">Total Amount:</label>
                <input type="text" id="total_amount" name="total_amount" class="form-control d-inline-block" style="width:150px;font-weight:bold;" readonly value="{{ $transfer->total_amount }}" />
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <a href="{{ route('material-transfer.index') }}" class="btn btn-light">Cancel</a>
    <input type="submit" value="Update" class="btn btn-primary">
</div>
{{ Form::close() }}


<script>
$(document).ready(function () {
    let siteStock = @json($siteStock);
    let materials = @json($materials);
    
    let materialTransferId = "{{ $materialTransferId }}";

    function recalculateSubtotal(row) {
        let qty = parseFloat(row.find('.item-quantity').val()) || 0;
        let price = parseFloat(row.find('.item-price').val()) || 0;
        row.find('.item-subtotal').val((qty * price).toFixed(2));
        recalculateTotalAmount();
    }

    function recalculateTotalAmount() {
        let total = 0;
        $('.item-subtotal').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        $('#total_amount').val(total.toFixed(2));
    }

    function refreshMaterialDropdowns() {
        const selectedIds = [];
        $('.item-material').each(function () {
            const val = $(this).val();
            if (val) selectedIds.push(val);
        });

        $('.item-material').each(function () {
            const $select = $(this);
            const currentVal = $select.val();
            $select.empty().append('<option value="">Select Material</option>');
            Object.entries(siteStock).forEach(([id, material]) => {
                const isSelectedElsewhere = selectedIds.includes(id) && id !== currentVal;
                const disabled = isSelectedElsewhere ? 'disabled' : '';
                $select.append(`<option value="${id}" ${disabled}>${material.name}</option>`);
            });
            $select.val(currentVal);
        });
    }

    function addItemRow() {
        let index = $('#record-items-table tbody tr').length;
        let materialOptions = '<option value="">Select Material</option>';
        Object.keys(siteStock).forEach(id => {
            if (materials[id]) {
                materialOptions += `<option value="${id}">${materials[id].name}</option>`;
            }
        });

        let row = $('<tr>');
        row.append(`<td><select name="items[${index}][material_id]" class="form-control item-material" required>${materialOptions}</select></td>`);
        row.append(`<td><div class="input-group"><input type="text" class="form-control item-stock" readonly value="0"/><span class="input-group-text item-stock-unit">unit</span></div></td>`);
        row.append(`<td><div class="input-group"><input type="number" name="items[${index}][quantity]" class="form-control item-quantity" min="1" value="1" required/><input type="hidden" name="items[${index}][unit]" class="form-control item-unit" value="" required/><span class="input-group-text item-unit-label">unit</span></div></td>`);
        row.append(`<td><input type="text" name="items[${index}][price]" class="form-control item-price" readonly value="0.00"/></td>`);
        row.append(`<td><input type="text" name="items[${index}][subtotal]" class="form-control item-subtotal" readonly value="0.00"/></td>`);
        row.append(`<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>`);
        $('#record-items-table tbody').append(row);
        refreshMaterialDropdowns();
        recalculateTotalAmount();
    }

    $('#add-item-row').on('click', function () {
        const $lastRow = $('#record-items-table tbody tr').last();
        let canAdd = true;
        const material = $lastRow.find('.item-material').val();
        const quantity = $lastRow.find('.item-quantity').val();
        const price = $lastRow.find('.item-price').val();

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

        if (!price) {
            $lastRow.find('.item-price').addClass('is-invalid');
            canAdd = false;
        } else {
            $lastRow.find('.item-price').removeClass('is-invalid');
        }

        if (canAdd) {
            $lastRow.removeClass('table-danger');
            addItemRow();
        } else {
            $lastRow.addClass('table-danger');
            alert('Please complete the current item row before adding a new one.');
        }
    });

    $('#record-items-table').on('change', '.item-material', function () {
        const row = $(this).closest('tr');
        const materialId = $(this).val();

        if (siteStock[materialId]) {
            const material = siteStock[materialId];
            row.find('.item-price').val(parseFloat(material.price || 0).toFixed(2));
            row.find('.item-unit').val(material.unit);
            row.find('.item-unit-label').text(material.unit);
            row.find('.item-stock').val(material.total_qty);
            row.find('.item-stock-unit').text(material.unit);
        } else {
            row.find('.item-price').val('0.00');
            row.find('.item-unit').val('');
            row.find('.item-unit-label').text('unit');
            row.find('.item-stock').val('0');
            row.find('.item-stock-unit').text('unit');
        }

        refreshMaterialDropdowns();
        recalculateSubtotal(row);
    });

    $('#record-items-table').on('input', '.item-quantity, .item-price', function () {
        const row = $(this).closest('tr');
        recalculateSubtotal(row);
    });

    $('#record-items-table').on('input', '.item-quantity', function () {
        const row = $(this).closest('tr');
        const qtyInput = row.find('.item-quantity');
        const materialId = row.find('.item-material').val();
        const available = siteStock[materialId]?.total_qty || 0;
        const enteredQty = parseFloat(qtyInput.val()) || 0;

        if (enteredQty > available) {
            alert('Quantity exceeds available stock');
            qtyInput.val(1);
        }
    });

    $('#record-items-table').on('click', '.remove-item-row', function () {
        $(this).closest('tr').remove();
        recalculateTotalAmount();
        refreshMaterialDropdowns();
    });

    $('#from_site_id').on('change', function () {
        $('#to_site_id').val('');
        const selectedFromSiteId = $(this).val();
        $('#to_site_id option').prop('disabled', false);
        if (selectedFromSiteId) {
            $('#to_site_id option').each(function () {
                if ($(this).val() === selectedFromSiteId) {
                    $(this).prop('disabled', true);
                }
            });
        }

        $.ajax({
            url: '{{ route("ajax.getStockBySiteMaterialTransferEdit") }}',
            method: 'GET',
            data: { site_id: selectedFromSiteId,materialTransferId:materialTransferId },
            success: function (response) {
                siteStock = {};
                response.forEach(item => {
                    siteStock[item.material_id] = {
                        name: item.material_name,
                        unit: item.unit_name || 'unit',
                        price: item.material_price,
                        total_qty: parseFloat(item.total_qty) || 0
                    };
                });

                $('#record-items-table tbody').empty();
                addItemRow();
            },
            error: function (xhr) {
                console.error('Error fetching stock:', xhr.responseText);
                siteStock = {};
            }
        });
    });

    refreshMaterialDropdowns();
    recalculateTotalAmount();
    
    
    
    const fromSiteSelect = document.getElementById('from_site_id');
    const toSiteSelect = document.getElementById('to_site_id');

    if (fromSiteSelect && toSiteSelect) {
        const selectedFromSiteId = fromSiteSelect.value;

        // Disable matching option in to_site_id
        Array.from(toSiteSelect.options).forEach(option => {
            option.disabled = option.value === selectedFromSiteId;
        });

        // If to_site_id is already set to the same value, reset it
        if (toSiteSelect.value === selectedFromSiteId) {
            toSiteSelect.value = '';
        }
    }
    
});
</script>

