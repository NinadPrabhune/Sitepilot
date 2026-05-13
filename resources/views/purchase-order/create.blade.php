

{{ Form::open(['route' => 'purchase-order.store', 'enctype'=>'multipart/form-data', 'class' => 'needs-validation', 'novalidate', 'id' => 'po-create-form']) }}

<div class="modal-body">
    <div class="text-end">
        @if (module_is_active('AIAssistant'))
        @php
        $templateName = \Workdo\AIAssistant\Entities\AssistantTemplate::where('template_module', 'purchase-order')->where('module', 'General')->get();
        @endphp
        @if($templateName->isEmpty())
        @include('aiassistant::ai.generate_ai_btn',['template_module' => 'purchase-order','module'=>'General'])
        @else
        @include('aiassistant::ai.generate_ai_btn',['template_module' => 'purchase-order','module'=>'General'])
        @endif
        @endif
    </div>

    <div class="row">
        {{-- PO Number --}}
        <div class="form-group col-md-3">
            {{ Form::label('po_number', __('PO Number'), ['class' => 'form-label']) }}
            {{ Form::text('po_number', \App\Models\PurchaseOrder::generatePONumber(getActiveWorkSpace()), ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- PO Date --}}
        <div class="form-group col-md-3">
            {{ Form::label('po_date', __('PO Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('po_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
        </div>
        
        {{-- Site --}}
        <div class="form-group col-md-3">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites->pluck('name', 'id')->prepend(__('Select Site'), ''), $selectedSiteId ?? null, ['class' => 'form-control']) }}
        </div>

        {{-- Indent Selection --}}
        <div class="form-group col-md-3">
            {{ Form::label('indent_id', __('Indent'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('indent_id', $indents->pluck('indent_number', 'id')->prepend(__('Select Indent'), ''), null, ['class' => 'form-control', 'id' => 'indent-select', 'required' => true]) }}
            <small class="text-muted">{{ __('Select an indent to automatically show available quantities') }}</small>
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-3">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('supplier_id', $suppliers->pluck('name', 'id')->prepend(__('Select Supplier'), ''), null, ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Assign To --}}
        <div class="form-group col-md-3">
            {{ Form::label('assign_to', __('Assign To'), ['class' => 'form-label']) }}<x-required></x-required>
            {{-- Pass users data to JavaScript --}}
            <span style="display:none;" id="users-data">{{ json_encode($users) }}</span>
            <select class="multi-select" id="assign_to" name="assign_to[]" multiple="multiple"
                    data-placeholder="{{ __('Select Users ...') }}" required>
            </select>
            <p class="text-danger d-none" id="user_validation">{{ __('Assign To field is required.') }}</p>
        </div>

        {{-- Tax Type Selection --}}
        <div class="form-group col-md-1">
            <label class="form-label">{{ __('Tax Type') }}</label><x-required></x-required>
            <br>
            <div class="form-check form-check-inline">
                {{ Form::radio('tax_type', 'cgst', true, ['class' => 'form-check-input', 'id' => 'tax_type_cgst']) }}
                {{ Form::label('tax_type_cgst', __('CGST + SGST'), ['class' => 'form-check-label']) }}
            </div>
            <div class="form-check form-check-inline">
                {{ Form::radio('tax_type', 'igst', false, ['class' => 'form-check-input', 'id' => 'tax_type_igst']) }}
                {{ Form::label('tax_type_igst', __('IGST'), ['class' => 'form-check-label']) }}
            </div>

        </div>


        <div class="form-group col-md-2">
            {{ Form::label('delivery_date', __('Expected Delivery Date'), ['class' => 'form-label']) }}
            {{ Form::date('delivery_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control']) }}
            @if ($errors->has('delivery_date'))
                <span class="text-danger">{{ $errors->first('delivery_date') }}</span>
            @endif
        </div>

        
        

        {{-- Reference File --}}
        <div class="form-group col-md-3">
            {{ Form::label('reference_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('reference_file', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png']) }}
            <small class="text-muted">{{ __('Accepted file types: pdf, doc, docx, jpg, jpeg, png (Max: 10MB)') }}</small>
            @if ($errors->has('reference_file'))
                <span class="text-danger">{{ $errors->first('reference_file') }}</span>
            @endif
        </div>

        

        {{-- Items Table --}}
        <div class="form-group col-md-12" id="po-items-table-div">
            <label class="form-label">{{ __('Purchase Order Materials') }}</label>
            <button type="button" class="btn btn-sm btn-primary d-none" style="float:right;" id="add-item-row">{{ __('Add Item') }}</button>
            
            <table class="table table-bordered" id="po-items-table">
                <thead>
                    <tr>
                        <th style="width:15%;">{{ __('Material') }}</th>
                        <th>{{ __('Available Qty') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th style="width:8%;">{{ __('Unit') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th style="width:10%;">{{ __('GST (%)') }}</th>
                        <th>{{ __('Tax Amount') }}</th>
                        <th>{{ __('Discount Amount') }}</th>
                        <th>{{ __('Subtotal') }}</th>
                        <th style="display: none;">{{ __('Remarks') }}</th>
                       
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('Total Taxable Value') }}:</strong></td>
                        <td><span id="total-taxable">0.00</span></td>
                        <td></td>
                        
                        <td style="display: none;"></td>
                    </tr>
                    <tr id="total-igst-row">
                        <td colspan="8" class="text-end"><strong>{{ __('Total IGST') }}:</strong></td>
                        <td><span id="total-igst">0.00</span></td>
                        <td></td>
                      
                        <td style="display: none;"></td>
                    </tr>
                    <tr id="total-cgst-row" style="display:none;">
                        <td colspan="8" class="text-end"><strong>{{ __('Total CGST') }}:</strong></td>
                        <td><span id="total-cgst">0.00</span></td>
                        <td></td>
                       
                        <td style="display: none;"></td>
                    </tr>
                    <tr id="total-sgst-row" style="display:none;">
                        <td colspan="8" class="text-end"><strong>{{ __('Total SGST') }}:</strong></td>
                        <td><span id="total-sgst">0.00</span></td>
                        <td></td>
                        
                        <td style="display: none;"></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('Total Discount') }}:</strong></td>
                        <td><span id="total-discount">0.00</span></td>
                        <td></td>
                       
                        <td style="display: none;"></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('(+) Additional Charge') }}:</strong></td>
                        <td>
                            <input type="number" name="additional_charge" id="additional_charge" class="form-control form-control-sm" value="0" min="0" step="0.01">
                        </td>
                        <td></td>
                        
                        <td style="display: none;"></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('(-) Additional Deduction') }}:</strong></td>
                        <td>
                            <input type="number" name="additional_deduction" id="additional_deduction" class="form-control form-control-sm" value="0" min="0" step="0.01">
                        </td>
                        <td></td>
                        
                        <td style="display: none;"></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('(-) Additional Discount') }}:</strong></td>
                        <td>
                            <input type="number" name="additional_discount" id="additional_discount" class="form-control form-control-sm" value="0" min="0" step="0.01">
                        </td>
                        <td></td>
                      
                        <td style="display: none;"></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('Grand Total') }}:</strong></td>
                        <td><span id="grand-total">0.00</span></td>
                        <td></td>
                       
                        <td style="display: none;"></td>
                    </tr>
                </tfoot>
            </table>
        </div>


        {{-- Description --}}
        <div class="form-group col-md-3">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Enter description')]) }}
        </div>
        
        <div class="form-group col-md-3">
            {{ Form::label('delivery_address', __('Delivery Address'), ['class' => 'form-label']) }}
            {{ Form::textarea('delivery_address', old('delivery_address'), ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Enter delivery address')]) }}
            @if ($errors->has('delivery_address'))
                <span class="text-danger">{{ $errors->first('delivery_address') }}</span>
            @endif
        </div>

        <div class="form-group col-md-3">
            {{ Form::label('delivery_terms_conditions', __('Delivery Terms and Conditions'), ['class' => 'form-label']) }}
            {{ Form::textarea('delivery_terms_conditions', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Delivery Terms and Conditions')]) }}
        </div>

        <div class="form-group col-md-3">
            {{ Form::label('payment_terms_conditions', __('Payment Terms and Conditions'), ['class' => 'form-label']) }}
            {{ Form::textarea('payment_terms_conditions', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Payment Terms and Conditions')]) }}
        </div>

        {{-- Hidden remark field - kept for data integrity --}}
        {{ Form::hidden('remark', null) }}
    </div>
</div>

<div class="modal-footer">
    {{ Form::submit(__('Create Purchase Order'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}

@php
// Pass materials and indents to JavaScript using PHP variables to avoid Blade parsing issues
$materialsJson = $materials->map(function($m) { 
    return [
        'id' => $m->id, 
        'name' => $m->name, 
        'unit' => $m->unit ?? '',
        'price' => $m->price ?? 0
    ]; 
})->toJson();

$indentsJson = $indents->map(function($indent) {
    $items = $indent->getItemsWithAvailability(null)->map(function($item) {
        return [
            'id' => $item['id'],
            'material_id' => $item['material_id'],
            'material_name' => $item['material_name'],
            'quantity' => $item['quantity'],
            'remaining_quantity' => $item['remaining_quantity'],
            'unit' => $item['unit'],
            'price' => $item['price']
        ];
    });
    return ['id' => $indent->id, 'indent_number' => $indent->indent_number, 'items' => $items, 'assign_to' => $indent->assign_to];
})->filter(function($indent) {
    return count($indent['items']) > 0;
})->values()->toJson();

// GST Masters for JavaScript
$gstMastersJson = $gstMasters->map(function($gst) {
    return [
        'id' => $gst->id,
        'name' => $gst->name,
        'cgst' => (float) $gst->cgst,
        'sgst' => (float) $gst->sgst,
        'igst' => (float) $gst->igst,
        'total_gst' => (float) $gst->total_gst
    ];
})->toJson();
@endphp


<script>
$(document).ready(function () {

    // Parse all materials and indents from PHP
    let allMaterials = {!! $materialsJson !!}.map(m => ({
        id: parseInt(m.id),
        name: m.name,
        unit: (m.unit && typeof m.unit === 'object' && m.unit.name) ? m.unit.name : (m.unit || ''),
        price: parseFloat(m.price || 0),
        category_id: m.category_id || null,
        category_name: m.category_name || ''
    }));

    let indents = {!! $indentsJson !!};
    let selectedIndentMaterials = [];

    // Get users data from Blade
    let usersData = JSON.parse($('#users-data').text());

    // Convert usersData to Choices.js format
    let usersChoicesArray = Object.keys(usersData).map(function(id) {
        return { value: id.toString(), label: usersData[id] };
    });

    // Initialize Choices.js manually for assign_to (instead of relying on custom.js)
    let assignToChoices = null;
    let assignToSelectElement = document.getElementById('assign_to');

    if (assignToSelectElement && typeof Choices !== 'undefined') {
        try {
            // Create Choices.js instance
            assignToChoices = new Choices(assignToSelectElement, {
                removeItemButton: true,
                searchEnabled: true,
                placeholder: true,
                placeholderValue: "{{ __('Select Users ...') }}",
            });

            // Populate choices using setChoices (Choices.js best practice)
            assignToChoices.setChoices(usersChoicesArray, 'value', 'label', true);
        } catch (e) {
        }
    }

    // Parse GST Masters
    let gstMasters = {!! $gstMastersJson !!};

    // Check for old input
    let oldInput = {!! json_encode(old('items', [])) !!};
    let hasOldInput = oldInput.length > 0;

    /* =============================
       Utility Functions
    ============================== */
    function buildGstOptions(selectedId = null) {
        let options = '<option value="">{{ __("Select GST") }}</option>';
        gstMasters.forEach(gst => {
            let selected = (selectedId && selectedId == gst.id) ? 'selected' : '';
            options += `<option value="${gst.id}" data-cgst="${gst.cgst}" data-sgst="${gst.sgst}" data-igst="${gst.igst}" data-total-gst="${gst.total_gst}" ${selected}>${gst.name}</option>`;
        });
        return options;
    }

    function calculateSubtotal(row) {
        let quantity = parseFloat(row.find('.quantity').val()) || 0;
        let price = parseFloat(row.find('.price').val()) || 0;
        let subtotal = quantity * price;
        
        // Get row-level discount
        let discountAmount = parseFloat(row.find('.discount-amount').val()) || 0;
        
        // Ensure discount doesn't exceed subtotal
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
            row.find('.discount-amount').val(discountAmount.toFixed(2));
        }
        
        // Calculate taxable value after discount
        let taxableValue = subtotal - discountAmount;
        
        // Calculate tax on taxable value (after discount)
        let taxType = $('input[name="tax_type"]:checked').val();
        let gstSelect = row.find('.gst-select');
        let selectedGst = gstSelect.find('option:selected');
        let cgst = parseFloat(selectedGst.data('cgst')) || 0;
        let sgst = parseFloat(selectedGst.data('sgst')) || 0;
        let igst = parseFloat(selectedGst.data('igst')) || 0;
        
        let taxAmount = 0;
        if (taxType === 'igst') {
            taxAmount = taxableValue * (igst / 100);
        } else {
            taxAmount = taxableValue * ((cgst + sgst) / 100);
        }
        
        // Subtotal = taxable value + tax
        let finalSubtotal = taxableValue + taxAmount;
        
        row.find('.subtotal').val(finalSubtotal.toFixed(2));
        row.find('.tax-amount').val(taxAmount.toFixed(2));
    }

    function calculateTotal() {
        let totalTaxableValue = 0; // Sum of (quantity * price)
        let totalDiscount = 0;
        let totalIgst = 0;
        let totalCgst = 0;
        let totalSgst = 0;
        let taxType = $('input[name="tax_type"]:checked').val();
        
        $('#po-items-table tbody tr').each(function () {
            let row = $(this);
            calculateSubtotal(row);
            
            let quantity = parseFloat(row.find('.quantity').val()) || 0;
            let price = parseFloat(row.find('.price').val()) || 0;
            let rowSubtotal = quantity * price;
            let discountAmount = parseFloat(row.find('.discount-amount').val()) || 0;
            let taxAmount = parseFloat(row.find('.tax-amount').val()) || 0;
            let finalSubtotal = parseFloat(row.find('.subtotal').val()) || 0;
            
            // Sum up raw taxable value (before discount)
            totalTaxableValue += rowSubtotal;
            
            // Sum up discounts
            totalDiscount += discountAmount;
            
            // Tax is already calculated on discounted value in calculateSubtotal
            let cgst = parseFloat(row.find('.gst-select option:selected').data('cgst')) || 0;
            let sgst = parseFloat(row.find('.gst-select option:selected').data('sgst')) || 0;
            let igst = parseFloat(row.find('.gst-select option:selected').data('igst')) || 0;
            
            if (taxType === 'igst') {
                totalIgst += taxAmount;
            } else {
                totalCgst += taxAmount / 2;
                totalSgst += taxAmount / 2;
            }
        });
        
        let additionalCharge = parseFloat($('#additional_charge').val()) || 0;
        let additionalDeduction = parseFloat($('#additional_deduction').val()) || 0;
        let additionalDiscount = parseFloat($('#additional_discount').val()) || 0;
        
        let totalTax = (taxType === 'igst') ? totalIgst : (totalCgst + totalSgst);
        
        // Grand Total = Final Subtotals + Additional Charge - Additional Deduction - Additional Discount
        // (Row-level discounts are already included in final subtotals)
        let grandTotal = (totalTaxableValue - totalDiscount + totalTax + additionalCharge - additionalDeduction - additionalDiscount);
        
        $('#total-taxable').text(totalTaxableValue.toFixed(2));
        $('#total-discount').text(totalDiscount.toFixed(2));
        
        if (taxType === 'igst') {
            $('#total-igst-row').show();
            $('#total-cgst-row').hide();
            $('#total-sgst-row').hide();
            $('#total-igst').text(totalIgst.toFixed(2));
        } else {
            $('#total-igst-row').hide();
            $('#total-cgst-row').show();
            $('#total-sgst-row').show();
            $('#total-cgst').text(totalCgst.toFixed(2));
            $('#total-sgst').text(totalSgst.toFixed(2));
        }
        
        // Ensure grand total is not negative
        if (grandTotal < 0) {
            grandTotal = 0;
            $('#grand-total').addClass('text-danger');
            $('#grand-total').attr('title', 'Grand Total cannot be negative');
        } else {
            $('#grand-total').removeClass('text-danger');
            $('#grand-total').removeAttr('title');
        }
        
        $('#grand-total').text(grandTotal.toFixed(2));
    }

    function reindexRows() {
        $('#po-items-table tbody tr').each(function (index) {
            $(this).attr('data-row-index', index);
            $(this).find('select, input').each(function () {
                let name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + index + ']'));
                }
            });
        });
    }

    function buildMaterialOptions(materialList, selectedId = null, indentData = null) {
        return materialList.map(material => {
            let id = (material.material_id !== undefined) ? material.material_id : material.id;
            let name = material.material_name || material.name;
            let unit = (material.unit && typeof material.unit === 'object' && material.unit.name) ? material.unit.name : (material.unit || '');
            let price = material.price || 0;
            let remainingQty = indentData ? (material.remaining_quantity || material.quantity || 0) : 0;
            let originalQty = indentData ? (material.quantity || 0) : 0;
            let selected = 'selected'; // default selected
            return `<option value="${id}" data-unit="${unit}" data-price="${price}" data-indent-qty="${remainingQty}" data-original-qty="${originalQty}" data-is-indent-item="${indentData ? 1 : 0}" ${selected}>
                        ${name} 
                    </option>`;
        }).join('');
    }

    /* =============================
       Add Row
    ============================== */
    function addItemRow(materialData = null, index = null, fromOld = false) {
        let rowIndex = (index !== null) ? index : $('#po-items-table tbody tr').length;

        // Prevent duplicate manual materials
        let selectedMaterialIds = $('#po-items-table tbody tr select.material-select').map(function () {
            return parseInt($(this).val());
        }).get();

        let materialList;
        if (materialData) {
            // indent row: only its own material
            materialList = [materialData];
        } else {
            // manual row: only materials not already selected
            materialList = allMaterials.filter(m => !selectedMaterialIds.includes(parseInt(m.id)));
        }

        let selectedId = materialData ? materialData.material_id : null;
        let options = buildMaterialOptions(materialList, selectedId, materialData);

        let indentQty = materialData ? (materialData.remaining_quantity || materialData.quantity || '') : '';
        let originalQty = materialData ? (materialData.quantity || '') : '';
        let quantity = fromOld ? (materialData.quantity || '') : (materialData ? indentQty : '');
        let unit = fromOld ? ((materialData.unit && typeof materialData.unit === 'object') ? materialData.unit.name : materialData.unit || '') : (materialData ? ((materialData.unit && typeof materialData.unit === 'object') ? materialData.unit.name : materialData.unit || '') : '');
        let price = fromOld ? (materialData.price || '') : (materialData ? materialData.price || '' : '');

        let html = `
        <tr data-row-index="${rowIndex}">
            <td>
                <select name="items[${rowIndex}][material_id]" class="form-control material-select" required>
                    ${options}
                </select>
            </td>
            <td>
                <input type="number" class="form-control available-qty" value="${indentQty}" readonly>
                <input type="hidden" name="items[${rowIndex}][indent_quantity]" value="${originalQty}">
            </td>
            <td>
                <input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity"
                       min="0.001" step="0.001"
                       ${indentQty ? `max="${indentQty}"` : ''}
                       value="${quantity}" required>
            </td>
            <td><input type="text" name="items[${rowIndex}][unit]" class="form-control unit" value="${unit}" required></td>
            <td><input type="number" name="items[${rowIndex}][price]" class="form-control price" min="0" step="0.01" value="${price}" required></td>
            <td>
                <select name="items[${rowIndex}][gst_master_id]" class="form-control gst-select" required>
                    ${buildGstOptions()}
                </select>
            </td>
            <td><input type="number" name="items[${rowIndex}][tax_amount]" class="form-control tax-amount" readonly value="0"></td>
            <td><input type="number" name="items[${rowIndex}][discount_amount]" class="form-control discount-amount" min="0" step="0.01" value="0"></td>
            <td><input type="number" name="items[${rowIndex}][subtotal]" class="form-control subtotal" readonly></td>
            <td style="display: none;"><input type="text" name="items[${rowIndex}][remarks]" class="form-control remarks"></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
        </tr>`;

        $('#po-items-table tbody').append(html);
        calculateSubtotal($('#po-items-table tbody tr').last());
        calculateTotal();
    }

    /* =============================
       Initial Load
    ============================== */
    if (hasOldInput) {
        $('#po-items-table tbody').empty();
        oldInput.forEach((item, index) => addItemRow(item, index, true));
    } else {
//        addItemRow(); // <-- this adds the default empty row

    }

    /* =============================
       Event Handlers
    ============================== */

    // Add manual row
    $('#add-item-row').on('click', function () {
        addItemRow();
    });

    // Remove row
    $(document).on('click', '.remove-row', function () {
        $(this).closest('tr').remove();
        reindexRows();
        calculateTotal();
    });

    // Indent selection change - use jQuery change event
    $('#indent-select').on('change', function () {
        let indentId = parseInt($(this).val());
        handleIndentChange(indentId);
    });

    // Handle indent selection logic
    function handleIndentChange(indentId) {

        $('#po-items-table tbody').empty();
        selectedIndentMaterials = [];


        if (!indentId) {
            addItemRow();
            // Clear assign_to when no indent selected using Choices.js API
            if (assignToChoices) {
                assignToChoices.removeActiveItems();
            }
            return;
        }

        let selectedIndent = indents.find(i => parseInt(i.id) === indentId);

        if (selectedIndent) {
            selectedIndentMaterials = selectedIndent.items;

            selectedIndentMaterials.forEach(item => addItemRow(item));

            // Populate assign_to from selected indent using Choices.js API

            // If Choices.js instance is not available, try to get it from the element
            if (!assignToChoices && assignToSelectElement && assignToSelectElement.choices) {
                assignToChoices = assignToSelectElement.choices;
            }

            // Always clear current selections first
            if (assignToChoices) {
                assignToChoices.removeActiveItems();
            }

            if (selectedIndent.assign_to && assignToChoices) {
                let assignToArray = selectedIndent.assign_to.split(',').map(id => id.trim());

                // Set selected values using Choices.js API with array (best practice)
                assignToChoices.setChoiceByValue(assignToArray);
            }
        } else {
            addItemRow();
            if (assignToChoices) {
                assignToChoices.removeActiveItems();
            }
        }

        calculateTotal();
    }

    // Material change for manual rows
    $(document).on('change', '.material-select', function () {
        let row = $(this).closest('tr');
        let selected = $(this).find('option:selected');

        let unit = selected.data('unit') || '';
        let price = selected.data('price') || 0;
        let indentQty = selected.data('indent-qty') || '';

        row.find('.unit').val(unit);
        row.find('.price').val(price);

        if (indentQty) {
            row.find('.available-qty').val(indentQty);
            row.find('.quantity').attr('max', indentQty);
            if (!row.find('.quantity').val()) row.find('.quantity').val(indentQty);
        } else {
            row.find('.available-qty').val('');
            row.find('.quantity').val('1');
            row.find('.quantity').removeAttr('max');
        }

        calculateSubtotal(row);
        calculateTotal();
    });

    // GST change
    $(document).on('change', '.gst-select', function() {
        let row = $(this).closest('tr');
        calculateSubtotal(row);
        calculateTotal();
    });

    // Quantity or Price change
    $(document).on('input', '.quantity, .price', function () {
        let row = $(this).closest('tr');
        let quantity = parseFloat(row.find('.quantity').val()) || 0;
        let maxQty = parseFloat(row.find('.quantity').attr('max')) || 0;

        row.find('.invalid-feedback').remove();
        row.find('.quantity').removeClass('is-invalid');

        if (maxQty && quantity > maxQty) {
            row.find('.quantity').addClass('is-invalid')
                .after('<div class="invalid-feedback">Quantity exceeds remaining indent quantity</div>');
        }

        calculateSubtotal(row);
        calculateTotal();
    });

    // Tax type change
    $('input[name="tax_type"]').on('change', function() {
        calculateTotal();
    });

    // Discount amount change - use event delegation for dynamically added rows
    $(document).on('input', '.discount-amount', function() {
        calculateTotal();
    });

    // Additional fields change
    $('#additional_charge, #additional_deduction, #additional_discount').on('input', function() {
        calculateTotal();
    });
    
    // Form validation on submit
    $('#po-create-form').on('submit', function(e) {
        let isValid = true;
        let errorMessage = '';
        let $form = $(this);
        let $submitBtn = $form.find('button[type="submit"]');
        
        // Disable submit button to prevent double submission
        $submitBtn.prop('disabled', true);
        
        // Clear all previous validation messages
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').remove();
        
        // Check if there are items
        const rowCount = $('#po-items-table tbody tr').length;
        if (rowCount === 0) {
            isValid = false;
            errorMessage = '{{ __("Please add at least one item") }}';
            toastrs('Error', errorMessage, 'error');
            e.preventDefault();
            $submitBtn.prop('disabled', false);
            return false;
        }
        
        // Validate supplier_id
        const supplierId = $('select[name="supplier_id"]').val();
        if (!supplierId) {
            isValid = false;
            $('select[name="supplier_id"]').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select a supplier") }}</div>');
            toastrs('Error', '{{ __("Please select a supplier") }}', 'error');
        } else {
            $('select[name="supplier_id"]').removeClass('is-invalid');
        }
        
        // Validate site_id
        const siteId = $('select[name="site_id"]').val();
        if (!siteId) {
            isValid = false;
            $('select[name="site_id"]').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select a site") }}</div>');
            toastrs('Error', '{{ __("Please select a site") }}', 'error');
        } else {
            $('select[name="site_id"]').removeClass('is-invalid');
        }
        
        // Validate po_date
        const poDate = $('input[name="po_date"]').val();
        if (!poDate) {
            isValid = false;
            $('input[name="po_date"]').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select PO date") }}</div>');
            toastrs('Error', '{{ __("Please select PO date") }}', 'error');
        } else {
            $('input[name="po_date"]').removeClass('is-invalid');
        }
        
        // Validate indent_id
        const indentId = $('#indent-select').val();
        if (!indentId) {
            isValid = false;
            $('#indent-select').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select an indent") }}</div>');
            toastrs('Error', '{{ __("Please select an indent") }}', 'error');
        } else {
            $('#indent-select').removeClass('is-invalid');
        }
        
        if (!isValid) {
            e.preventDefault();
            $submitBtn.prop('disabled', false);
            return false;
        }
        
        // Validate each row
        $('#po-items-table tbody tr').each(function(index) {
            const row = $(this);
            const material = row.find('.material-select').val();
            const quantity = row.find('.quantity').val();
            const unit = row.find('.unit').val();
            const price = row.find('.price').val();
            const gst = row.find('.gst-select').val();
            
            if (!material) {
                isValid = false;
                row.find('.material-select').addClass('is-invalid')
                    .after('<div class="invalid-feedback">{{ __("Please select a material") }}</div>');
            } else {
                row.find('.material-select').removeClass('is-invalid');
            }
            
            if (!quantity || parseFloat(quantity) <= 0) {
                isValid = false;
                row.find('.quantity').addClass('is-invalid')
                    .after('<div class="invalid-feedback">{{ __("Please enter a valid quantity") }}</div>');
            } else {
                row.find('.quantity').removeClass('is-invalid');
            }
            
            if (!unit) {
                isValid = false;
                row.find('.unit').addClass('is-invalid')
                    .after('<div class="invalid-feedback">{{ __("Please enter unit") }}</div>');
            } else {
                row.find('.unit').removeClass('is-invalid');
            }
            
            if (!price || parseFloat(price) < 0) {
                isValid = false;
                row.find('.price').addClass('is-invalid')
                    .after('<div class="invalid-feedback">{{ __("Please enter a valid price") }}</div>');
            } else {
                row.find('.price').removeClass('is-invalid');
            }
            
            if (!gst) {
                isValid = false;
                row.find('.gst-select').addClass('is-invalid')
                    .after('<div class="invalid-feedback">{{ __("Please select GST") }}</div>');
            } else {
                row.find('.gst-select').removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            toastrs('Error', '{{ __("Please fill all required fields") }}', 'error');
            $submitBtn.prop('disabled', false);
            return false;
        }
        
        // Validate grand total is not negative
        const grandTotal = parseFloat($('#grand-total').text()) || 0;
        if (grandTotal < 0) {
            isValid = false;
            errorMessage = '{{ __("Grand Total cannot be negative") }}';
            toastrs('Error', errorMessage, 'error');
            e.preventDefault();
            $submitBtn.prop('disabled', false);
            return false;
        }
        
        if (!isValid) {
            e.preventDefault();
            $submitBtn.prop('disabled', false);
            return false;
        }
        
        // Re-enable button if form is valid (allows submission to proceed)
        $submitBtn.prop('disabled', false);
    });

});
</script>

