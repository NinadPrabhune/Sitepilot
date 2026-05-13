@push('css')
<style>

#createSupplierModal {
    z-index: 1080; /* higher than the first modal */
}
.modal-backdrop.show:nth-of-type(2) {
    z-index: 1079; /* backdrop above the first modal but below the second */
}
</style>
@endpush
{{ Form::open(['route' => 'purchase-invoice.store', 'enctype'=>'multipart/form-data','class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="text-end">
        @if (module_is_active('AIAssistant'))
        @php
        $templateName = \Workdo\AIAssistant\Entities\AssistantTemplate::where('template_module', 'purchase-invoice')->where('module', 'Pos')->get();
        @endphp
        @if($templateName->isEmpty())
        @include('aiassistant::ai.generate_ai_btn',['template_module' => 'purchase-invoice','module'=>'General'])
        @else
        @include('aiassistant::ai.generate_ai_btn',['template_module' => 'purchase-invoice','module'=>'Pos'])
        @endif
        @endif
    </div>

    <div class="row">
        {{-- Invoice Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('invoice_number', __('Invoice Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('invoice_number', isset($nextInvoiceNumber) ? $nextInvoiceNumber : '', ['class' => 'form-control', 'required' => true, 'readonly' => true, 'placeholder' => 'Auto-generated']) }}
        </div>

        {{-- Invoice Date --}}
        <div class="form-group col-md-6">
            {{ Form::label('invoice_date', __('Invoice Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('invoice_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Supplier Invoice Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('supplier_invoice_number', __('Supplier Invoice Number'), ['class' => 'form-label']) }}
            {{ Form::text('supplier_invoice_number', null, ['class' => 'form-control', 'required' => true, 'readonly' => false, 'placeholder' => 'Enter Supplier Invoice Number']) }}
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-6">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>           
                {{ Form::select('supplier_id', $suppliers, null, ['class' => 'form-control', 'required' => true]) }}
                <div class="text-xs">
                    {{ __('Please add Branch. ') }}<a id="BtnOpenCreateSupplierModal" ><b>{{ __('+ Create New Supplier') }}</b></a>
                </div>      
        </div>



        {{-- Site --}}
        <div class="form-group col-md-6">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, null, ['class' => 'form-control', 'required' => true]) }}
        </div>



        {{-- Invoice File --}}
        <div class="form-group col-md-6">
            {{ Form::label('invoice_file', __('Invoice File'), ['class' => 'form-label']) }}
            {{ Form::file('invoice_file', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx']) }}
            <small class="text-muted">Allowed: pdf, jpg, jpeg, png, doc, docx</small>
        </div>
        
        <div class="form-group col-md-12">
            <label for="invoice_type" class="form-label">{{ __('Invoice Type') }}</label><x-required></x-required>
            <select name="invoice_type" id="invoice_type" class="form-control" required>
                <option value="general_po">{{ __('General PO') }}</option>
                <option value="minor_misc_service">{{ __('Minor Miscellaneous Service Bills') }}</option>
            </select>
        </div>


        <div class="form-group col-md-12" id="invoice-items-table-div">
            <label class="form-label">{{ __('Invoice Material') }}</label>

            <button type="button" class="btn btn-sm btn-primary" style="float:right;" id="add-item-row">{{ __('Add Item') }}</button>


            <table class="table table-bordered" id="invoice-items-table">
                <thead>
                    <tr>
                        <th>{{ __('Material') }}</th>
                        <th>{{ __('Quantity | Unit')  }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Subtotal') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            

        </div>
        
        <div class="text-end mt-2">
            <label for="total_amount" class="form-label fw-bold">{{ __('Total Amount') }}:</label>
            <input type="text" id="total_amount" name="total_amount" class="form-control d-inline-block" style="width:150px; font-weight:bold;" readonly value="0.00" />
        </div>

        {{-- Custom Fields --}}
        @if(module_is_active('CustomField') && isset($customFields) && !$customFields->isEmpty())
        <div class="col-md-12 form-group">
            <div class="tab-pane fade show form-label" id="tab-2" role="tabpanel">
                @include('custom-field::formBuilder')
            </div>
        </div>
        @endif
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}


<!-- Create Supplier Modal -->
<div class="modal fade" id="createSupplierModal" tabindex="-1" aria-labelledby="createSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSupplierModalLabel">{{ __('Create New Supplier') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <form id="createSupplierForm" enctype="multipart/form-data">
                    <input type="hidden" name="insert_from" value="modal">

                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('Name') }}</label><x-required></x-required>
                        <input type="text" name="name" class="form-control" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">{{ __('Category') }}</label><x-required></x-required>
                        <select name="category_id" class="form-control" required>
                            @foreach($supplierCategories as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="upi_screenshot_1" class="form-label">{{ __('UPI Screenshot') }}</label>
                        <input type="file" name="upi_screenshot_1" class="form-control" accept="image/*">
                    </div>
                </form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" id="saveSupplierBtn" class="btn btn-primary">    {{ __('Save Supplier') }}</button>
            </div>
        </div>
    </div>
</div>



<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    const forms = document.querySelectorAll('.needs-validation');

    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        }, false);
    });
    
    
    
    
});

document.getElementById('BtnOpenCreateSupplierModal').addEventListener('click', function () {
    var secondModal = new bootstrap.Modal(document.getElementById('createSupplierModal'), {
        backdrop: 'static' // prevents backdrop from closing both
    });
    secondModal.show();
});



$('#saveSupplierBtn').on('click', function () {
    let formData = new FormData($('#createSupplierForm')[0]);

    $.ajax({
        url: "{{ route('supplier.store') }}",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}" },
        success: function (response) {
            if (response.success) {
                alert(response.message);

                if (response.supplier) {
                    $('#supplier_id').append(
                        $('<option>', {
                            value: response.supplier.id,
                            text: response.supplier.name,
                            selected: true
                        })
                    );
                }

                $('#createSupplierModal').modal('hide');
            }
        },
        error: function (xhr) {
            if (xhr.status === 422) {
                let errors = xhr.responseJSON.errors;
                $('.error-text').remove(); // clear old errors
                $.each(errors, function (field, messages) {
                    let input = $('[name="' + field + '"]');
                    if (input.length) {
                        input.after('<div class="text-danger error-text">' + messages.join(', ') + '</div>');
                    }
                });
            }
        }
    });
});



//
//document.addEventListener('DOMContentLoaded', function () {
//    const form = document.getElementById('createSupplierForm');
//
//    form.addEventListener('submit', function (e) {
//        e.preventDefault();
//        
//        
//
////        let formData = new FormData(form);
////
////        fetch("{{ route('supplier.store') }}", {
////            method: "POST",
////            headers: {
////                "X-CSRF-TOKEN": "{{ csrf_token() }}"
////            },
////            body: formData
////        })
////        .then(response => response.json())
////        .then(data => {
////            if (data.success) {
////                // Show success message
////                alert(data.message);
////
////                // Optionally add new supplier to dropdown in first modal
////                if (data.supplier) {
////                    const supplierSelect = document.getElementById('supplier_id');
////                    const option = document.createElement('option');
////                    option.value = data.supplier.id;
////                    option.text = data.supplier.name;
////                    option.selected = true;
////                    supplierSelect.appendChild(option);
////                }
////
////                // Close modal
////                const modalEl = document.getElementById('createSupplierModal');
////                const modal = bootstrap.Modal.getInstance(modalEl);
////                modal.hide();
////            } else {
////                alert("Something went wrong.");
////            }
////        })
////        .catch(error => {
////            console.error(error);
////            alert("Error saving supplier.");
////        });
//    });
//});




</script>

<script>
    $(document).ready(function() {
    function recalculateSubtotal(row) {
    var qty = parseFloat(row.find('.item-quantity').val()) || 0;
    var price = parseFloat(row.find('.item-price').val()) || 0;
    row.find('.item-subtotal').val((qty * price).toFixed(2));
    recalculateTotalAmount();
    }

    function recalculateTotalAmount() {
    var total = 0;
    $('#invoice-items-table .item-subtotal').each(function() {
    total += parseFloat($(this).val()) || 0;
    });
    $('#total_amount').val(total.toFixed(2));
    }

    function addItemRow() {
    var materials = @json($materials);


    var materialOptions = '<option value="">Select Material</option>';
    $.each(materials, function(id, material) {
        materialOptions += '<option value="' + id + '">' + material.name + '</option>';
    });

    var index = $('#invoice-items-table tbody tr').length;

    var row = $('<tr>');
    row.append('<td><select name="items[' + index + '][material_id]" class="form-control item-material" style="min-width:220px;" required>' + materialOptions + '</select></td>');
    row.append('<td><div class="input-group">'
            + '<input type="number" name="items[' + index + '][quantity]" class="form-control item-quantity" min="1" value="1" required />'
            + '<input type="hidden" name="items[' + index + '][unit]" class="form-control item-unit" value="" required />'
            + '<span class="input-group-text item-unit-label">unit</span>'
            + '</div></td>');
    // Update unit label in input-group on material change
    $('#invoice-items-table').on('change', '.item-material', function() {
    var materialId = $(this).val();
    var row = $(this).closest('tr');
    var unitInput = row.find('.item-unit');
    var unitLabel = row.find('.item-unit-label');
    if (materialId) {
    $.ajax({
    url: '/material/' + materialId + '/unit',
            type: 'GET',
            success: function(response) {
            unitInput.val(response.unit || '');
            unitLabel.text(response.unit || 'unit');
            },
            error: function() {
            unitInput.val('');
            unitLabel.text('unit');
            }
    });
    } else {
    unitInput.val('');
    unitLabel.text('unit');
    }
    });
    row.append('<td><input type="number" name="items[' + index + '][price]" class="form-control item-price" min="0" step="0.01" value="0.00" /></td>');
    row.append('<td><input type="text" name="items[' + index + '][subtotal]" class="form-control item-subtotal" readonly value="0.00" /></td>');
    row.append('<td><button type="button" class="btn btn-danger btn-sm remove-item-row">&times;</button></td>');
    // Set unit on material change



    $('#invoice-items-table').on('change', '.item-material', function() {
    var materialId = $(this).val();
    var row = $(this).closest('tr');

    if (materialId && materials[materialId]) {
        var material = materials[materialId];
        var unitName = material.unit?.name || 'unit'; // fallback if unit is missing

        // Set price
        row.find('.item-price').val(parseFloat(material.price).toFixed(2));

        // Set unit value and label
        row.find('.item-unit').val(unitName);
        row.find('.item-unit-label').text(unitName);
    } else {
        // Reset if no material selected
        row.find('.item-price').val('0.00');
        row.find('.item-unit').val('');
        row.find('.item-unit-label').text('unit');
    }

    recalculateSubtotal(row);
});


    $('#invoice-items-table tbody').append(row);
    }

    $('#add-item-row').on('click', function() {
    // Validate all item rows before form submit
    $('form').on('submit', function(e) {
    var valid = true;
    $('#invoice-items-table tbody tr').each(function() {
        var material = $(this).find('.item-material').val();
        var quantity = $(this).find('.item-quantity').val();
        var price = $(this).find('.item-price').val();

        let hasError = false;
        if (!material) {
            $(this).find('.item-material').addClass('is-invalid');
            hasError = true;
        } else {
            $(this).find('.item-material').removeClass('is-invalid');
        }

        if (!quantity) {
            $(this).find('.item-quantity').addClass('is-invalid');
            hasError = true;
        } else {
            $(this).find('.item-quantity').removeClass('is-invalid');
        }

        if (!price) {
            $(this).find('.item-price').addClass('is-invalid');
            hasError = true;
        } else {
            $(this).find('.item-price').removeClass('is-invalid');
        }

        if (hasError) {
            valid = false;
            $(this).addClass('table-danger');
        } else {
            $(this).removeClass('table-danger');
        }
    });

    if (!valid) {
        e.preventDefault();
        alert('Please fill all required fields in each item row before submitting.');
    }
});




    var canAdd = true;
    $('#invoice-items-table tbody tr').each(function() {
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
    $('#invoice-items-table').on('input', '.item-quantity, .item-price', function() {
    var row = $(this).closest('tr');
    recalculateSubtotal(row);
    });
    $('#invoice-items-table').on('change', '.item-material', function() {
    recalculateTotalAmount();
    });
    $('#invoice-items-table').on('click', '.remove-item-row', function() {
    $(this).closest('tr').remove();
    recalculateTotalAmount();
    });
    // Add initial row
    addItemRow();
    recalculateTotalAmount();
    
    
    
// cache selectors
    var $invoiceTypeSelect = $('#invoice_type');
    var $itemsSection = $('#invoice-items-table-div');
    
    var $totalAmount = $('#total_amount');

    function toggleItemsTable() {
        
        if ($invoiceTypeSelect.val() === 'minor_misc_service') {
            $itemsSection.hide();
        } else {
            $itemsSection.show();
        }
    }
    
    function toggleTotalAmount() {
        if ($invoiceTypeSelect.val() === 'minor_misc_service') {
            $totalAmount.prop('readonly', false); // allow typing
        } else {
            $totalAmount.prop('readonly', true);  // lock field
            recalculateTotalAmount();
        }
    }

    // run once on page load
    toggleItemsTable();

    // run whenever the dropdown changes
    $invoiceTypeSelect.on('change', toggleItemsTable);
    
    toggleTotalAmount(); // run once on load
    $invoiceTypeSelect.on('change', toggleTotalAmount);
    
    
    var $invoiceTypeSelect = $('#invoice_type');

    function toggleRowRequirements() {
        if ($invoiceTypeSelect.val() === 'minor_misc_service') {
            // Service bills → rows not required
            $('#invoice-items-table tbody tr').find('.item-material, .item-quantity, .item-price')
                .prop('required', false);   // remove required
        } else {
            // General PO → rows required
            $('#invoice-items-table tbody tr').find('.item-material, .item-quantity, .item-price')
                .prop('required', true);    // enforce required
        }
    }

    // Run once on page load
    toggleRowRequirements();

    // Run whenever invoice type changes
    $invoiceTypeSelect.on('change', toggleRowRequirements);
    
    
    
    
    
    });
    
</script>