@php use App\Models\Grn; @endphp
{{ Form::open(['route' => 'grn.store', 'enctype'=>'multipart/form-data', 'class' => 'needs-validation grn-form', 'novalidate', 'id' => 'grn-create-form']) }}

<div class="modal-body">
    <!-- GRN Type Toggle -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('grn_type', __('GRN Type'), ['class' => 'form-label']) }}<x-required></x-required>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="grn_type" id="grn_type_po" value="against_po" checked>
                    <label class="btn btn-outline-primary" for="grn_type_po">
                        <i class="ti ti-file-invoice"></i> {{ __('Against PO') }}
                    </label>
                    <input type="radio" class="btn-check" name="grn_type" id="grn_type_direct" value="direct">
                    <label class="btn btn-outline-primary" for="grn_type_direct">
                        <i class="ti ti-truck-delivery"></i> {{ __('Direct GRN') }}
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Common Fields -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('grn_number', __('GRN Number'), ['class' => 'form-label']) }}
                {{ Form::text('grn_number', Grn::generateGrnNumber(getActiveProject()), ['class' => 'form-control', 'readonly' => true, 'id' => 'grn_number']) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('grn_date', __('GRN Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('grn_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('received_by', __('Received By'), ['class' => 'form-label']) }}
                {{ Form::text('received_by', null, ['class' => 'form-control', 'placeholder' => __('Enter receiver name')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('delivery_challan_number', __('Delivery Challan Number'), ['class' => 'form-label']) }}
                {{ Form::text('delivery_challan_number', null, ['class' => 'form-control', 'placeholder' => __('Enter delivery challan number')]) }}
            </div>
        </div>
    </div>

    <!-- Assign To -->
    <div class="row mb-3">
        
    

        <!-- PO Selection (for PO-based GRN) -->
        <div id="po-selection-section" class="col-md-6">
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('po_id', __('Select Purchase Order'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::select('po_id', $purchaseOrders->pluck('po_number', 'id')->prepend(__('Select PO'), ''), $selectedPoId ?? null, ['class' => 'form-control', 'required' => true, 'id' => 'po_id']) }}
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('assign_to', __('Assign To'), ['class' => 'form-label']) }}
                {{-- Pass users data to JavaScript --}}
                <span style="display:none;" id="users-data">{{ json_encode($users) }}</span>
                <select class="multi-select" id="assign_to" name="assign_to[]" multiple="multiple"
                        data-placeholder="{{ __('Select Users ...') }}">
                </select>
                <p class="text-danger d-none" id="user_validation">{{ __('Assign To field is required.') }}</p>
            </div>
        </div>
    
    </div>

    <!-- Supplier & Site Selection (for Direct GRN) -->
    <div id="direct-grn-section" style="display: none;">
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('direct_supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::select('direct_supplier_id', $suppliers, null, ['class' => 'form-control', 'required' => true, 'id' => 'direct_supplier_id']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('direct_site_name', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::text('direct_site_name', \Workdo\Taskly\Entities\Project::where('id', getActiveProject())->first()->name ?? '', ['class' => 'form-control', 'readonly' => true, 'id' => 'direct_site_name']) }}
                    {{ Form::hidden('direct_site_id', getActiveProject(), ['id' => 'direct_site_id']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('tax_type', __('Tax Type'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::select('tax_type', ['cgst' => 'CGST/SGST', 'igst' => 'IGST'], 'cgst', ['class' => 'form-control', 'required' => true, 'id' => 'tax_type']) }}
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('supplier_invoice_number', __('Supplier Invoice Number'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::text('supplier_invoice_number', null, ['class' => 'form-control', 'required' => true, 'placeholder' => __('Enter supplier invoice number')]) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('supplier_invoice_date', __('Supplier Invoice Date'), ['class' => 'form-label']) }}
                    {{ Form::date('supplier_invoice_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('vehicle_number_direct', __('Vehicle Number'), ['class' => 'form-label']) }}
                    {{ Form::text('vehicle_number_direct', null, ['class' => 'form-control', 'placeholder' => __('Enter vehicle number'), 'id' => 'vehicle_number_direct']) }}
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('delivery_challan_file_direct', __('Delivery Challan File'), ['class' => 'form-label']) }}
                    {{ Form::file('delivery_challan_file_direct', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'delivery_challan_file_direct']) }}
                    <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png') }}</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('reference_file_direct', __('Reference File'), ['class' => 'form-label']) }}
                    {{ Form::file('reference_file_direct', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'reference_file_direct']) }}
                    <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png') }}</small>
                </div>
            </div>
        </div>

        <!-- Direct GRN Items Table -->
        <div class="row">
            <div class="col-md-12">
                <h5 class="mb-2">{{ __('Items') }}</h5>
                <button type="button" class="btn btn-sm btn-primary float-end" id="add-direct-item">
                    <i class="ti ti-plus"></i> {{ __('Add Item') }}
                </button>
                    <table class="table table-bordered" id="direct-grn-items-table">
                        <thead>
                            <tr>
                                <th style="width:15%">{{ __('Category') }}</th>
                                <th style="width:30%">{{ __('Material') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('GST') }}</th>
                                <th class="accepted-col" style="display:none;">{{ __('Accepted Qty') }}</th>
                                <th class="rejected-col" style="display:none;">{{ __('Rejected Qty') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="direct-grn-items-body">
                            <tr id="no-items-row">
                                <td colspan="7" class="text-center">{{ __('Click "Add Item" to add materials') }}</td>
                            </tr>
                        </tbody>
                    </table>
               
                
            </div>
        </div>
    </div>

    <!-- PO Details (Auto-filled for PO-based GRN) -->
    <div id="po-details" style="display: none;">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('po_number', __('PO Number'), ['class' => 'form-label']) }}
                    {{ Form::text('po_number', null, ['class' => 'form-control', 'readonly' => true, 'id' => 'po_number']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('po_date', __('PO Date'), ['class' => 'form-label']) }}
                    {{ Form::text('po_date', null, ['class' => 'form-control', 'readonly' => true, 'id' => 'po_date']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('supplier_name', __('Supplier'), ['class' => 'form-label']) }}
                    {{ Form::text('supplier_name', null, ['class' => 'form-control', 'readonly' => true, 'id' => 'supplier_name']) }}
                    {{ Form::hidden('po_supplier_id', null, ['id' => 'po_supplier_id']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('site_name', __('Site'), ['class' => 'form-label']) }}
                    {{ Form::text('site_name', null, ['class' => 'form-control', 'readonly' => true, 'id' => 'site_name']) }}
                    {{ Form::hidden('po_site_id', null, ['id' => 'po_site_id']) }}
                </div>
            </div>
        </div>

        <!-- Delivery Details Row 1 -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('vehicle_number_po', __('Vehicle Number'), ['class' => 'form-label']) }}
                    {{ Form::text('vehicle_number_po', null, ['class' => 'form-control', 'placeholder' => __('Enter vehicle number'), 'id' => 'vehicle_number_po']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('gate_entry_number', __('Gate Entry Number'), ['class' => 'form-label']) }}
                    {{ Form::text('gate_entry_number', null, ['class' => 'form-control', 'placeholder' => __('Enter gate entry number')]) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('delivery_challan_file_po', __('Delivery Challan File'), ['class' => 'form-label']) }}
                    {{ Form::file('delivery_challan_file_po', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'delivery_challan_file_po']) }}
                    <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('reference_file_po', __('Reference File'), ['class' => 'form-label']) }}
                    {{ Form::file('reference_file_po', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'reference_file_po']) }}
                    <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png') }}</small>
                </div>
            </div>
        </div>

        <!-- PO Items Table -->
        <div class="row">
            <div class="col-md-12">
                <h5 class="mb-2">{{ __('PO Items') }}</h5>
                <div class="table-responsive">
                    <table class="table table-striped" id="po-items-table">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Ordered') }}</th>
                                <th>{{ __('Received') }}</th>
                                <th>{{ __('Remaining') }}</th>
                                <th>{{ __('Received Qty') }}</th>
                                <th>{{ __('Accepted') }}</th>
                                <th>{{ __('Rejected') }}</th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body">
                            <!-- Items will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Common Fields - Description & Remarks -->
    <div class="row mb-3">
        <div class="form-group col-md-6">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter description')]) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
            {{ Form::textarea('remarks', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter remarks')]) }}
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-primary" id="submit-grn">
        <i class="ti ti-save"></i> {{ __('Save GRN') }}
    </button>
</div>

{{ Form::close() }}

<script>
$(document).ready(function() {

    var directItemIndex = 0;
    var gstMasters = @json($gstMasters);
    var categories = @json($categories);

    // Initialize Choices.js for assign_to field
    var assignToChoices = null;
    var assignToSelectElement = document.getElementById('assign_to');
    var usersData = JSON.parse($('#users-data').text());

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
        } catch (e) {
            console.error('Error initializing Choices.js for assign_to:', e);
        }
    }

    // GRN Type Toggle
    $('input[name="grn_type"]').change(function() {
        var grnType = $(this).val();
        
        if (grnType === 'direct') {
            // Hide PO sections
            $('#po-selection-section').hide();
            $('#po-details').hide();
            
            // Reset PO fields
            $('#po-details').find('input, select').not('[type="hidden"]').val('');
            $('#po_id').val('').trigger('change');
            
            // Clear PO items table
            $('#po-items-body').html('');
            
            // Clear assign_to when switching to Direct GRN
            if (assignToChoices) {
                assignToChoices.removeActiveItems();
            }

            // Show Direct section
            $('#direct-grn-section').slideDown();
            
            // Hide accepted/rejected columns for Direct GRN
            $('.accepted-col, .rejected-col').hide();
            
            // Toggle required attributes
            $('#po_id').removeAttr('required');
            $('#direct_supplier_id').attr('required', true);
            $('#direct_site_id').attr('required', true);
        } else {
            // Hide Direct section
            $('#direct-grn-section').hide();
            
            // Reset Direct fields
            $('#direct-grn-section').find('input, select').not('[type="hidden"]').val('').trigger('change');
            
            // Clear Direct items table
            $('#direct-grn-items-body').html(`
                <tr id="no-items-row">
                    <td colspan="7" class="text-center">{{ __('Click "Add Item" to add materials') }}</td>
                </tr>
            `);
            directItemIndex = 0;
            
            // Show PO sections
            $('#po-selection-section').slideDown();
            
            // Toggle required attributes
            $('#direct_supplier_id').removeAttr('required');
            $('#direct_site_id').removeAttr('required');
            $('#po_id').attr('required', true);
        }
    });

    var gstOptions = '<option value="">{{ __("Select GST") }}</option>';
    gstMasters.forEach(function(gst) {
        gstOptions += '<option value="' + gst.id + '" data-cgst="' + gst.cgst + '" data-sgst="' + gst.sgst + '" data-igst="' + gst.igst + '">' + gst.name + ' (' + gst.cgst + '%/' + gst.sgst + '%/' + gst.igst + '%)</option>';
    });

    function initDirectRow(row) {
        var categorySelect = row.find('.category-select')[0];
        var materialSelect = row.find('.material-select')[0];
        var materialsData = {};

        // Populate category options
        categorySelect.innerHTML = '<option value="">Select Category</option>';
        categories.forEach(function(cat) {
            var option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            categorySelect.appendChild(option);
        });

        // Initialize Choices.js for category
        var categoryChoices = new Choices(categorySelect, {
            placeholder: true,
            placeholderValue: 'Select Category',
            searchEnabled: true,
            removeItemButton: true,
            shouldSort: false
        });

        // Initialize Choices.js for material (disabled initially)
        materialSelect.disabled = true;
        materialSelect.innerHTML = '<option value="">Select Material</option>';

        var materialChoices = new Choices(materialSelect, {
            placeholder: true,
            placeholderValue: 'Type to search materials...',
            searchEnabled: true,
            removeItemButton: true,
            shouldSort: false,
            itemSelectText: 'Press to select'
        });

        materialChoices.disable();

        // Category change handler
        categorySelect.addEventListener('change', function() {
            var categoryId = this.value;

            materialChoices.clearStore();
            materialChoices.clearInput();
            materialSelect.innerHTML = '<option value="">Select Material</option>';
            materialChoices.setChoices([], 'value');

            materialsData = {};

            if (categoryId) {
                materialChoices.enable();
            } else {
                materialChoices.disable();
            }

            row.find('.unit-input').val('');
            row.find('.price-input').val('');
            row.find('.gst-select').val('');

            if (categoryId) {
                $.ajax({
                    url: '/materials/ajax',
                    data: { category_id: categoryId },
                    dataType: 'json',
                    success: function(data) {
                        if (data && data.data) {
                            if (data.data.length === 0) {
                                materialChoices.setChoices([{ value: '', label: 'No materials found in this category', disabled: true }], 'value');
                            } else {
                                data.data.forEach(function(item) {
                                    materialsData[String(item.id)] = {
                                        unit: item.unit ? item.unit.name : 'PCS',
                                        price: item.price || 0,
                                        gst: item.gst_master_id || ''
                                    };
                                });

                                var choices = data.data.map(function(item) {
                                    return { value: String(item.id), label: item.name };
                                });
                                materialChoices.setChoices(choices, 'value');
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to fetch materials:', xhr);
                    }
                });
            }
        });

        // Material selection handler
        materialChoices.passedElement.element.addEventListener('change', function() {
            var selectedValue = materialSelect.value;

            if (selectedValue && materialsData[selectedValue]) {
                var data = materialsData[selectedValue];
                row.find('.unit-input').val(data.unit || '');
                row.find('.price-input').val(data.price || 0);
                if (data.gst) {
                    row.find('.gst-select').val(data.gst);
                }
            }
        });
    }

    // Add Direct GRN Item
    $('#add-direct-item').click(function() {
        // Validate last row before adding new row
        var lastRow = $('#direct-grn-items-body tr.direct-item-row').last();
        if (lastRow.length > 0) {
            var category = lastRow.find('.category-select').val();
            var material = lastRow.find('.material-select').val();
            var quantity = lastRow.find('.quantity-input').val();
            var price = lastRow.find('.price-input').val();

            if (!category || !material || !quantity || !price) {
                toastrs('Error', '{{ __("Please complete the previous row before adding a new one.") }}', 'error');
                if (!category) lastRow.find('.category-select').addClass('is-invalid');
                if (!material) lastRow.find('.material-select').addClass('is-invalid');
                if (!quantity) lastRow.find('.quantity-input').addClass('is-invalid');
                if (!price) lastRow.find('.price-input').addClass('is-invalid');
                return false;
            }

            lastRow.find('.is-invalid').removeClass('is-invalid');
        }

        var html = `
            <tr class="direct-item-row" data-index="${directItemIndex}">
                <td>
                    <select class="form-control category-select">
                    </select>
                </td>
                <td>
                    <select name="items[${directItemIndex}][material_id]" class="form-control material-select" disabled required>
                        <option value="">Select Material</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="items[${directItemIndex}][unit]" class="form-control unit-input" readonly>
                </td>
                <td>
                    <input type="number" name="items[${directItemIndex}][quantity]" class="form-control quantity-input" min="0.001" step="0.001" required>
                </td>
                <td>
                    <input type="number" name="items[${directItemIndex}][price]" class="form-control price-input" min="0" step="0.01" required>
                </td>
                <td>
                    <select name="items[${directItemIndex}][gst_master_id]" class="form-control gst-select" required>
                        ${gstOptions}
                    </select>
                </td>
                <td class="accepted-col" style="display:none;">
                    <input type="hidden" name="items[${directItemIndex}][accepted_qty]" class="accepted-qty" value="0">
                </td>
                <td class="rejected-col" style="display:none;">
                    <input type="hidden" name="items[${directItemIndex}][rejected_qty]" class="rejected-qty" value="0">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-direct-item">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
                <input type="hidden" name="items[${directItemIndex}][received_qty]" class="received-qty-hidden" value="0">
            </tr>
        `;

        $('#no-items-row').remove();
        $('#direct-grn-items-body').append(html);
        initDirectRow($('#direct-grn-items-body tr.direct-item-row').last());
        directItemIndex++;
    });

    // Remove Direct GRN Item
    $(document).on('click', '.remove-direct-item', function() {
        $(this).closest('tr').remove();
        if ($('#direct-grn-items-body tr').length === 0) {
            $('#direct-grn-items-body').html('<tr id="no-items-row"><td colspan="7" class="text-center">{{ __("Click Add Item to add materials") }}</td></tr>');
        }
    });

    // Quantity change for Direct GRN - auto-set accepted, rejected, received
    $(document).on('input', '.quantity-input', function() {
        var row = $(this).closest('tr');
        var quantity = parseFloat(row.find('.quantity-input').val()) || 0;

        // Direct GRN: accepted = quantity, rejected = 0, received = quantity
        row.find('.accepted-qty').val(quantity);
        row.find('.rejected-qty').val(0);
        row.find('.received-qty-hidden').val(quantity);
    });

    // PO Selection
    $('#po_id').change(function() {
        var poId = $(this).val();

        if (poId) {
            $('#po-items-body').html('<tr><td colspan="8" class="text-center">{{ __("Loading...") }}</td></tr>');

            $.ajax({
                url: '{{ route("grn.get-po-details") }}',
                type: 'GET',
                data: { po_id: poId },
                success: function(response) {
                    if (response.success) {
                        $('#po_number').val(response.po.po_number);
                        $('#po_date').val(response.po.po_date);
                        $('#supplier_name').val(response.po.supplier_name);
                        $('#site_name').val(response.po.site_name);
                        $('#po_supplier_id').val(response.po.supplier_id);
                        $('#po_site_id').val(response.po.site_id);
                        $('#po_id').val(response.po.id);

                        // Populate assign_to from PO using Choices.js
                        if (response.po.assign_to && assignToChoices) {
                            var assignToArray = response.po.assign_to.split(',').map(function(id) {
                                return id.trim();
                            });
                            assignToChoices.removeActiveItems();
                            assignToChoices.setChoiceByValue(assignToArray);
                        } else if (assignToChoices) {
                            assignToChoices.removeActiveItems();
                        }

                        $('#po-details').slideDown();

                        if (response.items && response.items.length > 0) {
                            var html = '';
                            response.items.forEach(function(item, index) {
                                if (item.remaining_qty > 0) {
                                    html += `
                                        <tr>
                                            <td>${item.material_name}</td>
                                            <td>${item.material_unit}</td>
                                            <td>${item.ordered_qty}</td>
                                            <td>${item.received_qty}</td>
                                            <td><span class="badge bg-info">${item.remaining_qty}</span></td>
                                            <td>
                                                <input type="number" name="items[${index}][received_qty]" 
                                                    class="form-control form-control-sm received-qty" 
                                                    data-index="${index}"
                                                    data-max="${item.remaining_qty}"
                                                    min="0" max="${item.remaining_qty}" 
                                                    step="0.001" value="${item.remaining_qty}" required>
                                                <input type="hidden" name="items[${index}][po_item_id]" value="${item.id}">
                                                <input type="hidden" name="items[${index}][material_id]" value="${item.material_id}">
                                                <input type="hidden" name="items[${index}][ordered_qty]" value="${item.ordered_qty}">
                                            </td>
                                            <td>
                                                <input type="number" name="items[${index}][accepted_qty]" 
                                                    class="form-control form-control-sm accepted-qty" 
                                                    data-index="${index}"
                                                    min="0" step="0.001" value="${item.remaining_qty}" required>
                                            </td>
                                            <td>
                                                <input type="number" name="items[${index}][rejected_qty]" 
                                                    class="form-control form-control-sm rejected-qty" 
                                                    data-index="${index}"
                                                    min="0" step="0.001" value="0" required readonly>
                                            </td>
                                        </tr>
                                    `;
                                }
                            });
                            $('#po-items-body').html(html || '<tr><td colspan="8" class="text-center">{{ __("No items to receive") }}</td></tr>');
                        } else {
                            $('#po-items-body').html('<tr><td colspan="8" class="text-center">{{ __("No items found") }}</td></tr>');
                        }
                    }
                },
                error: function(xhr) {
                    toastrs('Error', '{{ __("Failed to load PO details") }}', 'error');
                    $('#po-details').hide();
                }
            });
        } else {
            $('#po-details').hide();
        }
    });

    // When received_qty changes: set accepted_qty to same value and rejected_qty to 0
    $(document).on('input', '.received-qty[data-index]', function() {
        var index = $(this).data('index');
        var receivedQty = parseFloat($(this).val()) || 0;
        
        // Set accepted_qty to received_qty and rejected_qty to 0
        $('.accepted-qty[data-index="' + index + '"]').val(receivedQty);
        $('.rejected-qty[data-index="' + index + '"]').val(0);
    });

    // When accepted_qty is manually edited: recalculate rejected_qty as received_qty - accepted_qty
    $(document).on('input', '.accepted-qty[data-index]', function() {
        var index = $(this).data('index');
        var receivedQty = parseFloat($('.received-qty[data-index="' + index + '"]').val()) || 0;
        var acceptedQty = parseFloat($(this).val()) || 0;
        var rejectedQty = receivedQty - acceptedQty;
        
        if (rejectedQty < 0) {
            rejectedQty = 0;
            $(this).val(receivedQty);
            acceptedQty = receivedQty;
        }
        
        $('.rejected-qty[data-index="' + index + '"]').val(rejectedQty);
    });

    // Validate received qty for PO-based GRN
    $(document).on('input', '.received-qty', function() {
        var max = parseFloat($(this).data('max')) || 0;
        var val = parseFloat($(this).val()) || 0;
        
        if (val > max) {
            toastrs('Error', '{{ __("Received quantity cannot exceed remaining") }}', 'error');
            $(this).val(max);
            $(this).trigger('input');
        }
    });

    // Form submission
    $('#grn-create-form').on('submit', function(e) {
        e.preventDefault();
        
        var grnType = $('input[name="grn_type"]:checked').val();
        var hasItems = false;
        var hasError = false;
        
        // Disable unused flow fields before building FormData
        if (grnType === 'direct') {
            $('#po-selection-section, #po-details').find('input, select').prop('disabled', true);
        } else {
            $('#direct-grn-section').find('input, select').prop('disabled', true);
        }
        
        if (grnType === 'direct') {
            // Validate Direct GRN
            $('.quantity-input').each(function() {
                if (parseFloat($(this).val()) > 0) {
                    hasItems = true;
                }
            });
            
            if (!hasItems) {
                toastrs('Error', '{{ __("Please add at least one item") }}', 'error');
                $('#po-selection-section, #po-details, #direct-grn-section').find('input, select').prop('disabled', false);
                return false;
            }
            
            $('.quantity-input').each(function() {
                var row = $(this).closest('tr');
                var quantity = parseFloat($(this).val()) || 0;
                var accepted = parseFloat(row.find('.accepted-qty').val()) || 0;
                var rejected = parseFloat(row.find('.rejected-qty').val()) || 0;
                
                if (Math.abs((accepted + rejected) - quantity) > 0.001) {
                    toastrs('Error', '{{ __("Accepted + Rejected must equal Quantity") }}', 'error');
                    hasError = true;
                }
            });
        } else {
            // Validate PO-based GRN
            $('.received-qty').each(function() {
                if (parseFloat($(this).val()) > 0) {
                    hasItems = true;
                }
            });
            
            if (!hasItems) {
                toastrs('Error', '{{ __("Please enter at least one received quantity") }}', 'error');
                $('#po-selection-section, #po-details, #direct-grn-section').find('input, select').prop('disabled', false);
                return false;
            }
            
            $('.received-qty').each(function(index) {
                var received = parseFloat($(this).val()) || 0;
                var accepted = parseFloat($('.accepted-qty[data-index="' + index + '"]').val()) || 0;
                var rejected = parseFloat($('.rejected-qty[data-index="' + index + '"]').val()) || 0;
                
                if (Math.abs((accepted + rejected) - received) > 0.001) {
                    toastrs('Error', '{{ __("Accepted + Rejected must equal Received") }}', 'error');
                    hasError = true;
                }
            });
        }
        
        if (hasError) {
            // Re-enable all fields on validation error
            $('#po-selection-section, #po-details, #direct-grn-section').find('input, select').prop('disabled', false);
            return false;
        }

        var formData = new FormData(this);

        // Extract assign_to values from Choices.js and add to FormData
        if (assignToChoices) {
            var selectedValues = assignToChoices.getValue(true);
            // Remove existing assign_to values from FormData
            formData.delete('assign_to[]');
            // Add each selected value as an array element
            selectedValues.forEach(function(value) {
                formData.append('assign_to[]', value);
            });
        }

        // Re-enable fields after capturing FormData
        $('#po-selection-section, #po-details, #direct-grn-section').find('input, select').prop('disabled', false);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastrs('Success', response.message, 'success');
                    setTimeout(function() {
                        $('.modal').modal('hide');
                        if (typeof grnDataTable !== 'undefined') {
                            grnDataTable.ajax.reload();
                        } else {
                            location.reload();
                        }
                    }, 1500);
                } else {
                    toastrs('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                // Re-enable all fields on error
                $('#po-selection-section, #po-details, #direct-grn-section').find('input, select').prop('disabled', false);
                var message = xhr.responseJSON.message || '{{ __("Failed to create GRN") }}';
                toastrs('Error', message, 'error');
            }
        });
    });

    // Check if there's a pre-selected PO
    var selectedPoId = '{{ $selectedPoId ?? "" }}';
    if (selectedPoId) {
        $('#po_id').val(selectedPoId).trigger('change');
    }

    // Initial load: hide accepted/rejected columns if Direct GRN is selected
    var initialGrnType = $('input[name="grn_type"]:checked').val();
    if (initialGrnType === 'direct') {
        $('.accepted-col, .rejected-col').hide();
    }
});
</script>
