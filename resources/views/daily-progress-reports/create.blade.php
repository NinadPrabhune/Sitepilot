{{-- resources/views/daily-progress-reports/create.blade.php --}}

{{ Form::open(['route' => 'daily-progress-reports.store', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    {{-- Error Messages --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> {{ __('Error!') }}</h5>
            @foreach($errors->all() as $error)
                <p class="mb-1">{{ $error }}</p>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    <div class="row">

        {{-- Machinery Name --}}
        <div class="form-group col-md-3">
            {{ Form::label('machinery_name', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('machinery_name', $machinery->name ?? '', ['class' => 'form-control', 'required' => 'required', 'readonly' => true]) }}
            {{ Form::hidden('machinery_id', $machinery->id) }}
        </div>

        {{-- Owned By --}}
        <div class="form-group col-md-3">
            {{ Form::label('owned_by', __('Owned By'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('owned_by', [
                'owned' => 'Owned',
                'rental' => 'Rental'
            ], $machinery->owned_by, ['class' => 'form-control', 'disabled' => true]) }}
            {{ Form::hidden('owned_by', $machinery->owned_by) }}
        </div>

        {{-- Current Site --}}
        <div class="form-group col-md-3">
            {{ Form::label('site_id', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, old('site_id', $machinery->site_id), ['class' => 'form-control', 'disabled' => true]) }}
            {{ Form::hidden('site_id', old('site_id', $machinery->site_id)) }}
        </div>

        {{-- Date --}}
        <div class="form-group col-md-3">
            {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('date', now(), ['class' => 'form-control', 'required' => 'required']) }}
        </div>

        <hr>
        <h6 class="mb-3">{{ __('Machinery Details') }}</h6>

        {{-- Machine Start Reading --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_start_reading', __('Machine Start Reading'), ['class' => 'form-label']) }}
            {{ Form::number('machine_start_reading', null, ['class' => 'form-control', 'min' => 0]) }}
        </div>

        {{-- Machine End Reading --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_end_reading', __('Machine End Reading'), ['class' => 'form-label']) }}
            {{ Form::number('machine_end_reading', null, ['class' => 'form-control', 'min' => 0, 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="endReadingError"></div>
        </div>

        {{-- Idle Hours --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_idle_reading', __('Idle Hours'), ['class' => 'form-label']) }}
            {{ Form::number('machine_idle_reading', null, ['class' => 'form-control', 'min' => 0, 'step' => '0.01', 'placeholder' => 'e.g. 1.00', 'onchange' => 'validateReadings()']) }}
            <div class="invalid-feedback" id="idleHoursError"></div>
        </div>

        {{-- Number of Operators --}}
        <div class="form-group col-md-4">
            {{ Form::label('number_of_operators', __('Number of Operators'), ['class' => 'form-label']) }}
            {{ Form::number('number_of_operators', null, ['class' => 'form-control', 'min' => 0]) }}
        </div>
        
        {{-- Reference File --}}
        <div class="form-group col-md-6">
            {{ Form::label('consumption_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('consumption_file', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx']) }}
            <small class="text-muted">{{ __('Allowed: pdf, jpg, jpeg, png, doc, docx') }}</small>
        </div>

        {{-- Work Details --}}
        <div class="form-group col-md-6 d-none">
            {{ Form::label('work_details', __('Work Details'), ['class' => 'form-label']) }}
            {{ Form::textarea('work_details', null, ['class' => 'form-control', 'rows' => 3]) }}
        </div>

        {{-- Maintenance Notes --}}
        <div class="form-group col-md-6">
            {{ Form::label('maintenance_notes', __('Maintenance Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('maintenance_notes', null, ['class' => 'form-control', 'rows' => 3]) }}
        </div>

        {{ Form::hidden('consumption_type', 'fuel') }}

        <hr>
        <h6 class="mb-3">{{ __('Fuel Consumption Details') }}</h6>
        <div class="form-group col-md-12">
            <button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">{{ __('Add Item') }}</button>
            <table class="table table-bordered mt-2" id="consumption-items-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">{{ __('Material') }}</th>
                        <th>{{ __('Current Stock') }}</th>
                        <th>{{ __('Quantity | Unit') }}</th>
                        <th>{{ __('Remarks') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>

{{ Form::close() }}
<script src="{{ asset('js/dpr-validation-simple.js') }}"></script>

<script>
$(document).ready(function () {
    let materials = @json($materials); // Fuel materials passed from controller
    let rowIndex = 0;

    function addItemRow(item = {}, materialsList = materials) {
        const materialOptions = Object.entries(materialsList).map(([id, material]) =>
            `<option value="${id}">${material.name}</option>`
        ).join('');

        const row = $('<tr>');
        row.append(`<td>
            <select name="items[${rowIndex}][material_id]" class="form-control item-material" required>
                <option value="">Select Material</option>
                ${materialOptions}
            </select>
        </td>`);
        row.append(`<td>
            <div class="input-group">
                <input type="text" class="form-control item-stock" readonly value="0"/>
                <span class="input-group-text item-stock-unit">unit</span>
            </div>
        </td>`);
        row.append(`<td>
            <div class="input-group">
                <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-quantity" min="1" value="0" required>
                <input type="hidden" name="items[${rowIndex}][unit]" class="item-unit" value="unit">
                <span class="input-group-text item-unit-label">unit</span>
            </div>
        </td>`);
        row.append(`<td><input type="text" name="items[${rowIndex}][remarks]" class="form-control"></td>`);
        row.append(`<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>`);

        $('#consumption-items-table tbody').append(row);
        rowIndex++;
    }

    // Add row button with validation
    $('#add-item-row').on('click', function () {
        const $lastRow = $('#consumption-items-table tbody tr').last();
        let canAdd = true;

        if ($lastRow.length) {
            const materialId = $lastRow.find('.item-material').val();
            const quantity = parseFloat($lastRow.find('.item-quantity').val()) || 0;

            $lastRow.find('.item-material, .item-quantity').removeClass('is-invalid');

            if (!materialId) {
                $lastRow.find('.item-material').addClass('is-invalid');
                canAdd = false;
            }
            if (quantity <= 0) {
                $lastRow.find('.item-quantity').addClass('is-invalid');
                canAdd = false;
            }
            if (materialId && materials[materialId]) {
                const available = materials[materialId].total_qty || 0;
                if (quantity > available) {
                    $lastRow.find('.item-quantity').addClass('is-invalid');
                    $lastRow.find('.item-quantity')[0].setCustomValidity(`Quantity exceeds available stock (${available})`);
                    canAdd = false;
                } else {
                    $lastRow.find('.item-quantity')[0].setCustomValidity('');
                }
            }
        }

        if (canAdd) {
            addItemRow();
        }
    });

    // Remove row
    $('#consumption-items-table').on('click', '.remove-item-row', function () {
        $(this).closest('tr').remove();
    });

    // Load stock by site
    function loadFuelStock(siteId) {
        if (!siteId) return;
        $.ajax({
            url: '{{ route("ajax.getStockBySiteForDailyConsumption") }}',
            method: 'GET',
            data: {site_id: siteId},
            success: function (response) {
                materials = {};
                response.forEach(item => {
                    if (parseInt(item.category_id) === 2) { // Only Fuel
                        materials[item.material_id] = {
                            name: item.material_name,
                            unit: item.unit_name || 'unit',
                            price: item.material_price,
                            total_qty: parseFloat(item.total_qty) || 0,
                            category_id: item.category_id,
                            category_name: item.category_name,
                        };
                    }
                });

                // Reset table
                $('#consumption-items-table tbody').empty();

                // Add one fresh row with fuel materials
                addItemRow({}, materials);
            },
            error: function (xhr) {
                console.error('Error fetching stock:', xhr.responseText);
                materials = {};
            }
        });
    }

    // Bind change event
    $('#site_id').on('change', function () {
        loadFuelStock($(this).val());
    });

    // Run once on page load if site already selected
    const initialSiteId = $('#site_id').val();
    if (initialSiteId) {
        loadFuelStock(initialSiteId);
    }

    // Bind calculation preview to input changes (validation handled by centralized jQuery)
    $('#machine_start_reading, #machine_end_reading, #machine_idle_reading').on('input', updateCalculationPreview);

    // Quantity validation against stock
    $('#consumption-items-table').on('input', '.item-quantity', function () {
        const row = $(this).closest('tr');
        const qtyInput = row.find('.item-quantity');
        const materialId = row.find('.item-material').val();

        const available = materials[materialId]?.total_qty || 0;
        const enteredQty = parseFloat(qtyInput.val()) || 0;

        if (enteredQty > available) {
            qtyInput.addClass('is-invalid');
            qtyInput[0].setCustomValidity(`Quantity exceeds available stock (${available})`);
            qtyInput.val(available > 0 ? available : 1);
        } else {
            qtyInput.removeClass('is-invalid');
            qtyInput[0].setCustomValidity('');
        }
    });

    // Update stock/unit when material changes
    $('#consumption-items-table').on('change', '.item-material', function () {
        const materialId = $(this).val();
        const row = $(this).closest('tr');
        const unitInput = row.find('.item-unit');
        const unitLabel = row.find('.item-unit-label');
        const itemStock = row.find('.item-stock');
        const itemStockUnit = row.find('.item-stock-unit');

        if (materialId && materials[materialId]) {
            const material = materials[materialId];
            unitInput.val(material.unit);
            unitLabel.text(material.unit);
            itemStock.val(material.total_qty);
            itemStockUnit.text(material.unit);
        } else {
            unitInput.val('');
            unitLabel.text('unit');
            itemStock.val(0);
            itemStockUnit.text('unit');
        }
    });
});
</script>
