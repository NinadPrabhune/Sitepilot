
{{ Form::open(['route' => ['purchase-order.update', $purchaseOrder->id], 'enctype'=>'multipart/form-data', 'class' => 'needs-validation', 'novalidate', 'id' => 'po-edit-form']) }}
@method('PUT')
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
            {{ Form::text('po_number', $purchaseOrder->po_number, ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- PO Date --}}
        <div class="form-group col-md-3">
            {{ Form::label('po_date', __('PO Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('po_date', $purchaseOrder->po_date, ['class' => 'form-control', 'required' => true]) }}
        </div>
        
        {{-- Site --}}
        <div class="form-group col-md-3">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites->pluck('name', 'id')->prepend(__('Select Site'), ''), $purchaseOrder->site_id, ['class' => 'form-control']) }}
        </div>

        {{-- Indent Selection (Readonly - cannot change indent in edit) --}}
        <div class="form-group col-md-3">
            {{ Form::label('indent_id', __('Indent'), ['class' => 'form-label']) }}
            {{ Form::text('indent_display', $purchaseOrder->indent ? $purchaseOrder->indent->indent_number : 'N/A', ['class' => 'form-control', 'readonly' => true]) }}
            <input type="hidden" name="indent_id" id="indent-select" value="{{ $purchaseOrder->indent_id }}">
            <small class="text-muted">{{ __('Indent cannot be changed after PO creation') }}</small>
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-3">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('supplier_id', $suppliers->pluck('name', 'id')->prepend(__('Select Supplier'), ''), $purchaseOrder->supplier_id, ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Assign To --}}
        @php
            $selectedAssignTo = $purchaseOrder->assign_to ? explode(',', $purchaseOrder->assign_to) : [];
        @endphp
        <div class="form-group col-md-3">
            {{ Form::label('assign_to', __('Assign To'), ['class' => 'form-label']) }}
            {{-- Pass users data to JavaScript --}}
            <span style="display:none;" id="users-data">{{ json_encode($users) }}</span>
            {{-- Pass selected assign_to values to JavaScript --}}
            <span style="display:none;" id="selected-assign-to">{{ json_encode($selectedAssignTo) }}</span>
            <select class="multi-select" id="assign_to" name="assign_to[]" multiple="multiple"
                    data-placeholder="{{ __('Select Users ...') }}">
            </select>
            <p class="text-danger d-none" id="user_validation">{{ __('Assign To field is required.') }}</p>
        </div>

        {{-- Tax Type Selection --}}
        <div class="form-group col-md-1">
            <label class="form-label">{{ __('Tax Type') }}</label><x-required></x-required>
            <br>
            <div class="form-check form-check-inline">
                {{ Form::radio('tax_type', 'cgst', $purchaseOrder->tax_type == 'cgst', ['class' => 'form-check-input', 'id' => 'tax_type_cgst']) }}
                {{ Form::label('tax_type_cgst', __('CGST + SGST'), ['class' => 'form-check-label']) }}
            </div>
            <div class="form-check form-check-inline">
                {{ Form::radio('tax_type', 'igst', $purchaseOrder->tax_type == 'igst', ['class' => 'form-check-input', 'id' => 'tax_type_igst']) }}
                {{ Form::label('tax_type_igst', __('IGST'), ['class' => 'form-check-label']) }}
            </div>
        </div>

        {{-- Delivery Date --}}
        <div class="form-group col-md-2">
            {{ Form::label('delivery_date', __('Expected Delivery Date'), ['class' => 'form-label']) }}
            {{ Form::date('delivery_date', $purchaseOrder->delivery_date, ['class' => 'form-control']) }}
            @if ($errors->has('delivery_date'))
                <span class="text-danger">{{ $errors->first('delivery_date') }}</span>
            @endif
        </div>

        

        {{-- Reference File --}}
        <div class="form-group col-md-3">
            {{ Form::label('reference_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('reference_file', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png']) }}
            <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png (Max: 10MB)') }}</small>
            @if ($errors->has('reference_file'))
                <span class="text-danger">{{ $errors->first('reference_file') }}</span>
            @endif
            @if($purchaseOrder->reference_file)
                <div class="mt-2">
                    <a href="{{ asset($purchaseOrder->reference_file) }}" target="_blank" class="btn btn-sm btn-info">
                        <i class="ti ti-file"></i> {{ __('View File') }}
                    </a>
                </div>
            @endif
        </div>

        {{-- Status Display --}}
        <div class="form-group col-md-3">
            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
            @php
                $statusClass = 'secondary';
                if ($purchaseOrder->display_status == 'Approved') $statusClass = 'primary';
                elseif ($purchaseOrder->display_status == 'Partial Received') $statusClass = 'warning';
                elseif ($purchaseOrder->display_status == 'Completed') $statusClass = 'success';
                elseif ($purchaseOrder->display_status == 'Rejected') $statusClass = 'danger';
                elseif ($purchaseOrder->display_status == 'Flagged - Corrected') $statusClass = 'info';
                elseif ($purchaseOrder->display_status == 'Flagged') $statusClass = 'info';
                elseif ($purchaseOrder->display_status == 'Short Closed') $statusClass = 'dark';
            @endphp
            <span class="badge bg-{{ $statusClass }}">
                {{ __($purchaseOrder->display_status) }}
            </span>
        </div>

        {{-- Status Reason Display (Flagged/Rejected/Short Closed) --}}
        @if(in_array($purchaseOrder->status, ['Flagged', 'Rejected', 'Short Closed']))
        <div class="form-group col-md-9">
            @if($purchaseOrder->status == 'Flagged')
                {{ Form::label('flag_reason', __('Flag Reason'), ['class' => 'form-label text-danger']) }}
                <div class="text-danger bg-light p-2 rounded">
                    {{ $purchaseOrder->flag_reason ?? 'N/A' }}
                </div>
            @elseif($purchaseOrder->status == 'Rejected')
                {{ Form::label('rejection_reason', __('Rejection Reason'), ['class' => 'form-label text-danger']) }}
                <div class="text-danger bg-light p-2 rounded">
                    {{ $purchaseOrder->rejection_reason ?? 'N/A' }}
                </div>
            @elseif($purchaseOrder->status == 'Short Closed')
                {{ Form::label('short_close_reason', __('Short Close Reason'), ['class' => 'form-label text-danger']) }}
                <div class="text-danger bg-light p-2 rounded">
                    {{ $purchaseOrder->short_close_reason ?? 'N/A' }}
                </div>
            @endif
        </div>
        @endif

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
                        <th></th>
                    </tr>
                </thead>
                <tbody id="items-tbody">
                    {{-- Items will be loaded via JavaScript --}}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('Total Taxable Value') }}:</strong></td>
                        <td><span id="total-taxable">0.00</span></td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr id="total-igst-row" style="{{ $purchaseOrder->tax_type == 'igst' ? '' : 'display:none;' }}">
                        <td colspan="8" class="text-end"><strong>{{ __('Total IGST') }}:</strong></td>
                        <td><span id="total-igst">0.00</span></td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr id="total-cgst-row" style="{{ $purchaseOrder->tax_type == 'cgst' ? '' : 'display:none;' }}">
                        <td colspan="8" class="text-end"><strong>{{ __('Total CGST') }}:</strong></td>
                        <td><span id="total-cgst">0.00</span></td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr id="total-sgst-row" style="{{ $purchaseOrder->tax_type == 'cgst' ? '' : 'display:none;' }}">
                        <td colspan="8" class="text-end"><strong>{{ __('Total SGST') }}:</strong></td>
                        <td><span id="total-sgst">0.00</span></td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('Total Discount') }}:</strong></td>
                        <td><span id="total-discount">0.00</span></td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('(+) Additional Charge') }}:</strong></td>
                        <td>
                            <input type="number" name="additional_charge" id="additional_charge" class="form-control form-control-sm" value="{{ $purchaseOrder->additional_charge ?? 0 }}" min="0" step="0.01">
                        </td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('(-) Additional Deduction') }}:</strong></td>
                        <td>
                            <input type="number" name="additional_deduction" id="additional_deduction" class="form-control form-control-sm" value="{{ $purchaseOrder->additional_deduction ?? 0 }}" min="0" step="0.01">
                        </td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('(-) Additional Discount') }}:</strong></td>
                        <td>
                            <input type="number" name="additional_discount" id="additional_discount" class="form-control form-control-sm" value="{{ $purchaseOrder->additional_discount ?? 0 }}" min="0" step="0.01">
                        </td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end"><strong>{{ __('Grand Total') }}:</strong></td>
                        <td><span id="grand-total">0.00</span></td>
                        <td style="display: none;"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Description --}}
        <div class="form-group col-md-3">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', $purchaseOrder->description, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Enter description')]) }}
        </div>
        
        {{-- Delivery Address --}}
        <div class="form-group col-md-3">
            {{ Form::label('delivery_address', __('Delivery Address'), ['class' => 'form-label']) }}
            {{ Form::textarea('delivery_address', old('delivery_address', $purchaseOrder->delivery_address), ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Enter delivery address')]) }}
            @if ($errors->has('delivery_address'))
                <span class="text-danger">{{ $errors->first('delivery_address') }}</span>
            @endif
        </div>

        <div class="form-group col-md-3">
            {{ Form::label('delivery_terms_conditions', __('Delivery Terms and Conditions'), ['class' => 'form-label']) }}
            {{ Form::textarea('delivery_terms_conditions', $purchaseOrder->delivery_terms_conditions, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Delivery Terms and Conditions')]) }}
        </div>

        <div class="form-group col-md-3">
            {{ Form::label('payment_terms_conditions', __('Payment Terms and Conditions'), ['class' => 'form-label']) }}
            {{ Form::textarea('payment_terms_conditions', $purchaseOrder->payment_terms_conditions, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Payment Terms and Conditions')]) }}
        </div>

        {{-- Hidden remark field - kept for data integrity --}}
        {{ Form::hidden('remark', $purchaseOrder->remark) }}
    </div>
</div>

<div class="modal-footer">
    {{ Form::submit(__('Update Purchase Order'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}

@php
// All materials for JavaScript
$materialsJson = $materials->map(function($m) { 
    return [
        'id' => $m->id, 
        'name' => $m->name, 
        'unit' => (is_object($m->unit) ? $m->unit->name : ($m->unit ?? '')),
        'price' => (float) ($m->price ?? 0)
    ]; 
})->toJson();

// Indents with items for JavaScript (only show indents with remaining quantity for CREATE)
$indentsJson = $indents->map(function($indent) { 
    $items = $indent->getItemsWithAvailability(null)->map(function($item) {
        return [
            'id' => $item['id'],
            'material_id' => $item['material_id'],
            'material_name' => $item['material_name'],
            'quantity' => (float) $item['quantity'],
            'remaining_quantity' => (float) $item['remaining_quantity'],
            'unit' => $item['unit'],
            'price' => (float) $item['price']
        ];
    });
    return ['id' => $indent->id, 'indent_number' => $indent->indent_number, 'items' => $items];
})->filter(function($indent) {
    return count($indent['items']) > 0;
})->values()->toJson();

// Selected indent with remaining quantities
$selectedIndentJson = null;
if (!empty($selectedIndentItems)) {
    $selectedIndentJson = json_encode([
        'id' => $selectedIndent->id,
        'indent_number' => $selectedIndent->indent_number,
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'material_id' => $item['material_id'],
                'material_name' => $item['material_name'] ?? 'Unknown',
                'quantity' => (float) ($item['quantity'] ?? 0),
                'indent_quantity' => (float) ($item['indent_quantity'] ?? $item['quantity'] ?? 0),
                'remaining_quantity' => (float) ($item['remaining_quantity'] ?? 0),
                'available_for_edit' => (float) ($item['available_for_edit'] ?? 0),
                'consumed_quantity' => (float) ($item['consumed_quantity'] ?? 0),
                'unit' => $item['unit'] ?? '',
                'price' => (float) ($item['price'] ?? 0)
            ];
        }, $selectedIndentItems)
    ]);
} elseif ($selectedIndent) {
    // Fallback: use old method
    $selectedIndentJson = json_encode([
        'id' => $selectedIndent->id,
        'indent_number' => $selectedIndent->indent_number,
        'items' => $selectedIndent->items->map(function($item) {
            return [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material_name' => $item->material_name ?? ($item->material ? $item->material->name : 'Unknown'),
                'quantity' => (float) ($item->quantity ?? 0),
                'indent_quantity' => (float) ($item->indent_quantity ?? $item->quantity ?? 0),
                'remaining_quantity' => (float) ($item->remaining_quantity ?? 0),
                'available_for_edit' => (float) ($item->available_for_edit ?? 0),
                'consumed_quantity' => (float) ($item->consumed_quantity ?? 0),
                'unit' => $item->unit,
                'price' => (float) ($item->price ?? 0)
            ];
        })->toArray()
    ]);
}

// CRITICAL FIX: Use pre-calculated items with availability from controller
// These are the ACTUAL purchase order items, NOT indent items
// This ensures saved items are loaded even if indent is fully consumed
$existingItemsJson = json_encode($itemsWithAvailability);

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

// Old input if any
$oldInputJson = json_encode(old('items', []));
@endphp

<script>
$(document).ready(function () {

    // Data from PHP
    var allMaterials = {!! $materialsJson !!};
    var indents = {!! $indentsJson !!};
    var gstMasters = {!! $gstMastersJson !!};
    var existingItems = {!! $existingItemsJson !!};
    var selectedIndent = {!! $selectedIndentJson ?? 'null' !!};
    var oldInput = {!! $oldInputJson !!};
    var currentIndentId = {!! $purchaseOrder->indent_id ?? 'null' !!};
    var currentTaxType = '{!! $purchaseOrder->tax_type !!}';
    var rowCounter = 0;

    // Initialize Choices.js for assign_to field
    var assignToChoices = null;
    var assignToSelectElement = document.getElementById('assign_to');


    var usersData = JSON.parse($('#users-data').text());
    var selectedAssignTo = JSON.parse($('#selected-assign-to').text());


    // Convert usersData to Choices.js format
    var usersChoicesArray = Object.keys(usersData).map(function(id) {
        return { value: id.toString(), label: usersData[id] };
    });


    if (assignToSelectElement && typeof Choices !== 'undefined') {
        try {
            // Create Choices.js instance
            assignToChoices = new Choices(assignToSelectElement, {
                removeItemButton: true,
                searchEnabled: true,
                placeholder: true,
                placeholderValue: "{{ __('Select Users ...') }}",
            });


            // Populate choices using setChoices
            assignToChoices.setChoices(usersChoicesArray, 'value', 'label', true);

            // Set initial selected values
            if (selectedAssignTo && selectedAssignTo.length > 0) {
                assignToChoices.setChoiceByValue(selectedAssignTo.map(String));
            }
        } catch (e) {
        }
    }

    // Helper: Build GST options
    function buildGstOptions(selectedId = null) {
        var options = '<option value="">{{ __("Select GST") }}</option>';
        gstMasters.forEach(function(gst) {
            var selected = (selectedId && selectedId == gst.id) ? 'selected' : '';
            options += '<option value="' + gst.id + '" data-cgst="' + gst.cgst + '" data-sgst="' + gst.sgst + '" data-igst="' + gst.igst + '" data-total-gst="' + gst.total_gst + '" ' + selected + '>' + gst.name + '</option>';
        });
        return options;
    }

    // Helper: Build material options excluding already selected
    function buildMaterialOptions(selectedId, excludeIds, isIndentItem) {
        var options = '<option value="">{{ __("Select Material") }}</option>';
        var materials = isIndentItem ? [] : allMaterials.filter(function(m) {
            return !excludeIds.includes(parseInt(m.id));
        });
        
        if (isIndentItem) {
            // For indent items, we show all available indents materials
            materials = allMaterials;
        }
        
        materials.forEach(function(m) {
            var selected = (selectedId && selectedId == m.id) ? 'selected' : '';
            options += '<option value="' + m.id + '" data-unit="' + (m.unit || '') + '" data-price="' + (m.price || 0) + '" ' + selected + '>' + m.name + '</option>';
        });
        return options;
    }

    // Helper: Calculate row subtotal
    function calculateSubtotal($row) {
        var quantity = parseFloat($row.find('.quantity').val()) || 0;
        var price = parseFloat($row.find('.price').val()) || 0;
        var subtotal = quantity * price;
        
        var discountAmount = parseFloat($row.find('.discount-amount').val()) || 0;
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
            $row.find('.discount-amount').val(discountAmount.toFixed(2));
        }
        
        var taxableValue = subtotal - discountAmount;
        
        var taxType = $('input[name="tax_type"]:checked').val();
        var $gstSelect = $row.find('.gst-select');
        var selectedGst = $gstSelect.find('option:selected');
        var cgst = parseFloat(selectedGst.data('cgst')) || 0;
        var sgst = parseFloat(selectedGst.data('sgst')) || 0;
        var igst = parseFloat(selectedGst.data('igst')) || 0;
        
        var taxAmount = 0;
        if (taxType === 'igst') {
            taxAmount = taxableValue * (igst / 100);
        } else {
            taxAmount = taxableValue * ((cgst + sgst) / 100);
        }
        
        var finalSubtotal = taxableValue + taxAmount;
        
        $row.find('.subtotal').val(finalSubtotal.toFixed(2));
        $row.find('.tax-amount').val(taxAmount.toFixed(2));
    }

    // Helper: Calculate total
    function calculateTotal() {
        var totalTaxableValue = 0;
        var totalDiscount = 0;
        var totalIgst = 0;
        var totalCgst = 0;
        var totalSgst = 0;
        var taxType = $('input[name="tax_type"]:checked').val();
        
        $('#items-tbody tr').each(function() {
            var $row = $(this);
            calculateSubtotal($row);
            
            var quantity = parseFloat($row.find('.quantity').val()) || 0;
            var price = parseFloat($row.find('.price').val()) || 0;
            var rowSubtotal = quantity * price;
            var discountAmount = parseFloat($row.find('.discount-amount').val()) || 0;
            
            totalTaxableValue += rowSubtotal;
            totalDiscount += discountAmount;
            
            var cgst = parseFloat($row.find('.gst-select option:selected').data('cgst')) || 0;
            var sgst = parseFloat($row.find('.gst-select option:selected').data('sgst')) || 0;
            var igst = parseFloat($row.find('.gst-select option:selected').data('igst')) || 0;
            var taxAmount = parseFloat($row.find('.tax-amount').val()) || 0;
            
            if (taxType === 'igst') {
                totalIgst += taxAmount;
            } else {
                totalCgst += taxAmount / 2;
                totalSgst += taxAmount / 2;
            }
        });
        
        var additionalCharge = parseFloat($('#additional_charge').val()) || 0;
        var additionalDeduction = parseFloat($('#additional_deduction').val()) || 0;
        var additionalDiscount = parseFloat($('#additional_discount').val()) || 0;
        
        var grandTotal = totalTaxableValue - totalDiscount + (taxType === 'igst' ? totalIgst : (totalCgst + totalSgst)) + additionalCharge - additionalDeduction - additionalDiscount;
        
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
        
        if (grandTotal < 0) {
            grandTotal = 0;
            $('#grand-total').addClass('text-danger');
        } else {
            $('#grand-total').removeClass('text-danger');
        }
        
        $('#grand-total').text(grandTotal.toFixed(2));
    }

    // Helper: Get all selected material IDs
    function getSelectedMaterialIds() {
        var ids = [];
        $('#items-tbody tr').each(function() {
            var materialId = $(this).data('material-id');
            if (materialId) {
                ids.push(parseInt(materialId));
            }
        });
        return ids;
    }

    // Add row function
    function addItemRow(itemData, isIndentItem) {

        var selectedIds = getSelectedMaterialIds();
        
        // For indent items, only show that specific material
        var materialOptions;
        var isDisabled = isIndentItem ? 'disabled' : '';
        var materialId = itemData ? itemData.material_id : null;
        // CRITICAL: Use available_qty from controller (not available_for_edit)
        var indentQty = itemData ? (itemData.indent_quantity ?? 0) : 0;

        // available_qty coming from controller is already correct editable max
        var editableMaxQty = itemData ? (itemData.available_qty ?? itemData.quantity ?? 0) : 0;

        var quantity = itemData ? (itemData.quantity ?? 0) : 0;
        var originalQty = itemData ? (itemData.quantity || '') : '';
        var quantity = itemData ? (itemData.quantity || availableQty || '') : '';
        var unit = itemData ? (itemData.unit || '') : '';
        var price = itemData ? (itemData.price || '') : '';
        var gstMasterId = itemData ? (itemData.gst_master_id || '') : '';
        var taxAmount = itemData ? (itemData.tax_amount || 0) : 0;
        var discountAmount = itemData ? (itemData.discount_amount || 0) : 0;
        var subtotal = itemData ? (itemData.subtotal || 0) : 0;
        var remarks = itemData ? (itemData.remarks || '') : '';

        if (isIndentItem) {
            // Show only the indent material
            materialOptions = '<option value="' + materialId + '" selected>' + (itemData.material_name || '') + '</option>';
        } else {
            // Show available materials excluding selected
            materialOptions = '<option value="">{{ __("Select Material") }}</option>';
            allMaterials.forEach(function(m) {
                if (!selectedIds.includes(parseInt(m.id))) {
                    materialOptions += '<option value="' + m.id + '" data-unit="' + (m.unit || '') + '" data-price="' + (m.price || 0) + '">' + m.name + '</option>';
                }
            });
        }
        
        var rowIndex = rowCounter++;
        var rowId = itemData && itemData.id ? itemData.id : '';
        
        var html = '<tr data-row-index="' + rowIndex + '" data-material-id="' + (materialId || '') + '" data-is-indent="' + (isIndentItem ? '1' : '0') + '">' +
            '<td>' +
                '<select name="items[' + rowIndex + '][material_id]" class="form-control material-select" required ' + isDisabled + '>' +
                    materialOptions +
                '</select>' +
                (isIndentItem ? '<input type="hidden" name="items[' + rowIndex + '][material_id]" value="' + materialId + '">' : '') +
                (rowId ? '<input type="hidden" name="items[' + rowIndex + '][id]" value="' + rowId + '">' : '') +
            '</td>' +
            '<td>' +
                '<input type="number" class="form-control available-qty" value="' + editableMaxQty + '" readonly>' +
                '<input type="hidden" name="items[' + rowIndex + '][indent_quantity]" value="' + indentQty + '">' +
            '</td>' +
            '<td>' +
                '<input type="number" name="items[' + rowIndex + '][quantity]" class="form-control quantity" ' +
                (editableMaxQty ? 'max="' + editableMaxQty + '" ' : '') +
                'min="0.001" step="0.001" value="' + quantity + '" required>' +
            '</td>' +
            '<td><input type="text" name="items[' + rowIndex + '][unit]" class="form-control unit" value="' + unit + '" required></td>' +
            '<td><input type="number" name="items[' + rowIndex + '][price]" class="form-control price" min="0" step="0.01" value="' + price + '" required></td>' +
            '<td>' +
                '<select name="items[' + rowIndex + '][gst_master_id]" class="form-control gst-select">' +
                    buildGstOptions(gstMasterId) +
                '</select>' +
            '</td>' +
            '<td><input type="number" name="items[' + rowIndex + '][tax_amount]" class="form-control tax-amount" readonly value="' + taxAmount + '"></td>' +
            '<td><input type="number" name="items[' + rowIndex + '][discount_amount]" class="form-control discount-amount" min="0" step="0.01" value="' + discountAmount + '"></td>' +
            '<td><input type="number" name="items[' + rowIndex + '][subtotal]" class="form-control subtotal" readonly value="' + subtotal + '"></td>' +
            '<td style="display: none;"><input type="text" name="items[' + rowIndex + '][remarks]" class="form-control remarks" value="' + remarks + '"></td>' +
            '<td>' +
                (isIndentItem ? '' : '<button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>') +
            '</td>' +
        '</tr>';
        
        $('#items-tbody').append(html);
        
        // Trigger initial calculation
        var $row = $('#items-tbody tr').last();
        calculateSubtotal($row);
        calculateTotal();
    }

    // Helper: Load indent materials
    function loadIndentMaterials(indentId) {
        $('#items-tbody').empty();
        rowCounter = 0;
        
        if (!indentId) {
            // No indent selected, allow adding manual rows
            addItemRow(null, false);
            return;
        }
        
        // Use selectedIndent from PHP (which has correct availability for edit)
        var indentData = selectedIndent;
        
        if (indentData && indentData.items && indentData.items.length > 0) {
            // indentData.items.forEach(function(item) {
            //     // Find matching existing item if any
            //     var existingItem = existingItems.find(function(ei) { 
            //         return ei.material_id == item.material_id; 
            //     });
                
            //     if (existingItem) {
            //         // Merge: preserve PO quantity (existingItem), use indent availability (item)
            //         var mergedItem = Object.assign({}, item, {
            //             id: existingItem.id,
            //             quantity: existingItem.quantity,
            //             unit: existingItem.unit,
            //             price: existingItem.price,
            //             gst_master_id: existingItem.gst_master_id,
            //             tax_amount: existingItem.tax_amount,
            //             discount_amount: existingItem.discount_amount,
            //             subtotal: existingItem.subtotal,
            //             remarks: existingItem.remarks
            //         });
            //         addItemRow(mergedItem, true);
            //     } else {
            //         addItemRow(item, true);
            //     }
            // });
        }
        
        // Add any manual items (items without indent quantity)
        existingItems.forEach(function(item) {
            if (!item.indent_quantity || item.indent_quantity == 0) {
                addItemRow(item, false);
            }
        });
        
        calculateTotal();
    }

    // Initial load - CRITICAL FIX: Always load PO items first, even if indent is fully consumed
    if (oldInput && oldInput.length > 0) {
        // Load from old input
        $('#items-tbody').empty();
        rowCounter = 0;
        oldInput.forEach(function(item) {
            addItemRow(item, false);
        });
    } else if (existingItems && existingItems.length > 0) {
        // CRITICAL: Always load PO items first - they contain the saved items
        // This works even if indent is fully consumed
        $('#items-tbody').empty();
        rowCounter = 0;
        
        existingItems.forEach(function(item) {
            // Use available_qty from controller for the max and available qty display
            var isIndentItem = item.indent_quantity > 0;
            addItemRow(item, isIndentItem);
        });
    } else {
        // If no existing items, add empty row
        $('#items-tbody').empty();
        rowCounter = 0;
        addItemRow(null, false);
    }
    
    calculateTotal();

    // Event: Add row button
    $('#add-item-row').on('click', function() {
        addItemRow(null, false);
    });

    // Event: Remove row
    $(document).on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        reindexRows();
        calculateTotal();
    });

    // Event: Indent selection change
    $('#indent-select').on('change', function() {
        var indentId = $(this).val();
        loadIndentMaterials(indentId ? parseInt(indentId) : null);
    });

    // Event: Tax type change
    $('input[name="tax_type"]').on('change', function() {
        calculateTotal();
    });

    // Event: Material selection
    $(document).on('change', '.material-select', function() {
        var $row = $(this).closest('tr');
        var isIndent = $row.data('is-indent') == '1';
        
        if (!isIndent) {
            var unit = $(this).find('option:selected').data('unit') || '';
            var price = $(this).find('option:selected').data('price') || 0;
            
            $row.find('.unit').val(unit);
            $row.find('.price').val(price);
            $row.find('.available-qty').val('');
            $row.find('.quantity').removeAttr('max');
            $row.data('material-id', $(this).val());
            
            calculateSubtotal($row);
            calculateTotal();
        }
    });

    // Event: GST selection
    $(document).on('change', '.gst-select', function() {
        var $row = $(this).closest('tr');
        calculateSubtotal($row);
        calculateTotal();
    });

    // Event: Quantity, price, discount changes
    $(document).on('input', '.quantity, .price, .discount-amount', function() {
        var $row = $(this).closest('tr');
        var quantity = parseFloat($row.find('.quantity').val()) || 0;
        var availableQty = parseFloat($row.find('.available-qty').val()) || 0;
        
        if (availableQty > 0 && quantity > availableQty) {
            $(this).addClass('is-invalid');
            if (!$row.find('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">{{ __("Quantity exceeds available qty") }}</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $row.find('.invalid-feedback').remove();
        }
        
        calculateSubtotal($row);
        calculateTotal();
    });

    // Event: Additional charges
    $(document).on('input', '#additional_charge, #additional_deduction, #additional_discount', function() {
        calculateTotal();
    });

    // Helper: Reindex rows
    function reindexRows() {
        rowCounter = 0;
        $('#items-tbody tr').each(function(index) {
            $(this).attr('data-row-index', index);
            $(this).find('[name]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + index + ']'));
                }
            });
            rowCounter++;
        });
    }
    
    // Form validation on submit
    $('#po-edit-form').on('submit', function(e) {
        var isValid = true;
        var errorMessage = '';
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        
        // Disable submit button to prevent double submission
        $submitBtn.prop('disabled', true);
        
        // Clear all previous validation messages
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').remove();
        
        // Check if there are items
        var rowCount = $('#items-tbody tr').length;
        if (rowCount === 0) {
            isValid = false;
            errorMessage = '{{ __("Please add at least one item") }}';
            toastrs('Error', errorMessage, 'error');
            e.preventDefault();
            $submitBtn.prop('disabled', false);
            return false;
        }
        
        // Validate supplier_id
        var supplierId = $('select[name="supplier_id"]').val();
        if (!supplierId) {
            isValid = false;
            $('select[name="supplier_id"]').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select a supplier") }}</div>');
            toastrs('Error', '{{ __("Please select a supplier") }}', 'error');
        } else {
            $('select[name="supplier_id"]').removeClass('is-invalid');
        }
        
        // Validate site_id
        var siteId = $('select[name="site_id"]').val();
        if (!siteId) {
            isValid = false;
            $('select[name="site_id"]').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select a site") }}</div>');
            toastrs('Error', '{{ __("Please select a site") }}', 'error');
        } else {
            $('select[name="site_id"]').removeClass('is-invalid');
        }
        
        // Validate po_date
        var poDate = $('input[name="po_date"]').val();
        if (!poDate) {
            isValid = false;
            $('input[name="po_date"]').addClass('is-invalid')
                .after('<div class="invalid-feedback">{{ __("Please select PO date") }}</div>');
            toastrs('Error', '{{ __("Please select PO date") }}', 'error');
        } else {
            $('input[name="po_date"]').removeClass('is-invalid');
        }
        
        // Validate indent_id
        var indentId = $('#indent-select').val();
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
        $('#items-tbody tr').each(function(index) {
            var row = $(this);
            var material = row.find('.material-select').val();
            var quantity = row.find('.quantity').val();
            var unit = row.find('.unit').val();
            var price = row.find('.price').val();
            var gst = row.find('.gst-select').val();
            
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
        var grandTotal = parseFloat($('#grand-total').text()) || 0;
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



