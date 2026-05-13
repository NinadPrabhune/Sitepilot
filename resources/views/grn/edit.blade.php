{{ Form::open(['route' => ['grn.update', $grn->id], 'method' => 'PUT', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation grn-form', 'novalidate', 'id' => 'grn-edit-form']) }}

@php 
use App\Models\Grn;
$isDirectGrn = $grn->isDirectGrn();
@endphp

<input type="hidden" name="grn_type" value="{{ $isDirectGrn ? 'direct' : 'against_po' }}">

<div class="modal-body">
    <!-- GRN Type Display -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="alert alert-info">
                <strong>{{ __('GRN Type:') }}</strong> 
                @if($isDirectGrn)
                    <span class="badge bg-success">{{ __('Direct GRN') }}</span>
                @else
                    <span class="badge bg-primary">{{ __('Against PO') }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Common Fields -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('grn_number', __('GRN Number'), ['class' => 'form-label']) }}
                {{ Form::text('grn_number', $grn->grn_number, ['class' => 'form-control', 'readonly' => true, 'id' => 'grn_number']) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('grn_date', __('GRN Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('grn_date', old('grn_date', $grn->grn_date), ['class' => 'form-control', 'required' => true]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('received_by', __('Received By'), ['class' => 'form-label']) }}
                {{ Form::text('received_by', old('received_by', $grn->received_by), ['class' => 'form-control', 'placeholder' => __('Enter receiver name')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('delivery_challan_number', __('Delivery Challan Number'), ['class' => 'form-label']) }}
                {{ Form::text('delivery_challan_number', old('delivery_challan_number', $grn->delivery_challan_number), ['class' => 'form-control', 'placeholder' => __('Enter delivery challan number')]) }}
            </div>
        </div>
    </div>

    <!-- Assign To -->
    @php
        $selectedAssignTo = $grn->assign_to ? explode(',', $grn->assign_to) : [];
    @endphp
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="form-group">
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
        </div>
    </div>

    <!-- PO Details (for PO-based GRN) -->
    @if(!$isDirectGrn)
    <div id="po-details">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('po_number', __('PO Number'), ['class' => 'form-label']) }}
                    {{ Form::text('po_number', $grn->purchaseOrder->po_number ?? '', ['class' => 'form-control', 'readonly' => true, 'id' => 'po_number']) }}
                    {{ Form::hidden('po_id', $grn->po_id, ['id' => 'po_id_hidden']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('po_date', __('PO Date'), ['class' => 'form-label']) }}
                    {{ Form::text('po_date', $grn->purchaseOrder->po_date ?? '', ['class' => 'form-control', 'readonly' => true, 'id' => 'po_date']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('supplier_name', __('Supplier'), ['class' => 'form-label']) }}
                    {{ Form::text('supplier_name', $grn->supplier->name ?? '', ['class' => 'form-control', 'readonly' => true, 'id' => 'supplier_name']) }}
                    {{ Form::hidden('po_supplier_id', $grn->supplier_id, ['id' => 'po_supplier_id']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {{ Form::label('site_name', __('Site'), ['class' => 'form-label']) }}
                    {{ Form::text('site_name', $grn->site->name ?? '', ['class' => 'form-control', 'readonly' => true, 'id' => 'site_name']) }}
                    {{ Form::hidden('po_site_id', $grn->site_id, ['id' => 'po_site_id']) }}
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Direct GRN Details -->
    @if($isDirectGrn)
    <div id="direct-grn-section">
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('direct_supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::select('direct_supplier_id', $suppliers ?? [], $grn->supplier_id, ['class' => 'form-control', 'required' => true, 'id' => 'direct_supplier_id']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('direct_site_name', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::text('direct_site_name', $grn->site->name ?? '', ['class' => 'form-control', 'readonly' => true, 'id' => 'direct_site_name']) }}
                    {{ Form::hidden('direct_site_id', $grn->site_id, ['id' => 'direct_site_id']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('tax_type', __('Tax Type'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::select('tax_type', ['cgst' => 'CGST/SGST', 'igst' => 'IGST'], $grn->tax_type, ['class' => 'form-control', 'required' => true, 'id' => 'tax_type']) }}
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('supplier_invoice_number', __('Supplier Invoice Number'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::text('supplier_invoice_number', old('supplier_invoice_number', $grn->supplier_invoice_number), ['class' => 'form-control', 'required' => true, 'placeholder' => __('Enter supplier invoice number')]) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('supplier_invoice_date', __('Supplier Invoice Date'), ['class' => 'form-label']) }}
                    {{ Form::date('supplier_invoice_date', old('supplier_invoice_date', $grn->supplier_invoice_date), ['class' => 'form-control']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {{ Form::label('vehicle_number_direct', __('Vehicle Number'), ['class' => 'form-label']) }}
                    {{ Form::text('vehicle_number_direct', old('vehicle_number_direct', $grn->vehicle_number), ['class' => 'form-control', 'placeholder' => __('Enter vehicle number'), 'id' => 'vehicle_number_direct']) }}
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('delivery_challan_file_direct', __('Delivery Challan File'), ['class' => 'form-label']) }}
                    {{ Form::file('delivery_challan_file_direct', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'delivery_challan_file_direct']) }}
                    <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png') }}</small>
                    @if($grn->delivery_challan_file)
                        <div class="mt-2">
                            <a href="{{ asset($grn->delivery_challan_file) }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="ti ti-file"></i> {{ __('View File') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('reference_file_direct', __('Reference File'), ['class' => 'form-label']) }}
                    {{ Form::file('reference_file_direct', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'reference_file_direct']) }}
                    @if($grn->reference_file)
                        <div class="mt-2">
                            <a href="{{ asset($grn->reference_file) }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="ti ti-file"></i> {{ __('View File') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Delivery Details -->
    <div class="row mb-3">
        @if(!$isDirectGrn)
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('vehicle_number_po', __('Vehicle Number'), ['class' => 'form-label']) }}
                {{ Form::text('vehicle_number_po', old('vehicle_number_po', $grn->vehicle_number), ['class' => 'form-control', 'placeholder' => __('Enter vehicle number'), 'id' => 'vehicle_number_po']) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('gate_entry_number', __('Gate Entry Number'), ['class' => 'form-label']) }}
                {{ Form::text('gate_entry_number', old('gate_entry_number', $grn->gate_entry_number), ['class' => 'form-control', 'placeholder' => __('Enter gate entry number')]) }}
            </div>
        </div>
        @endif
        @if(!$isDirectGrn)
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('delivery_challan_file_po', __('Delivery Challan File'), ['class' => 'form-label']) }}
                {{ Form::file('delivery_challan_file_po', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'delivery_challan_file_po']) }}
                <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png (Max: 10MB)') }}</small>
                @if($errors && $errors->has('delivery_challan_file_po'))
                    <span class="text-danger">{{ $errors->first('delivery_challan_file_po') }}</span>
                @endif
                @if($grn->delivery_challan_file)
                    <div class="mt-2">
                        <a href="{{ asset($grn->delivery_challan_file) }}" target="_blank" class="btn btn-sm btn-info">
                            <i class="ti ti-file"></i> {{ __('View File') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('reference_file_po', __('Reference File'), ['class' => 'form-label']) }}
                {{ Form::file('reference_file_po', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png', 'id' => 'reference_file_po']) }}
                <small class="text-muted">{{ __('Accepted: pdf, doc, docx, jpg, jpeg, png (Max: 10MB)') }}</small>
                @if($errors && $errors->has('reference_file_po'))
                    <span class="text-danger">{{ $errors->first('reference_file_po') }}</span>
                @endif
                @if($grn->reference_file)
                    <div class="mt-2">
                        <a href="{{ asset($grn->reference_file) }}" target="_blank" class="btn btn-sm btn-info">
                            <i class="ti ti-file"></i> {{ __('View File') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- GRN Items Table -->
    <div class="row">
        <div class="col-md-12">
            <h5 class="mb-2">{{ __('GRN Items') }}</h5>
            <div class="table-responsive">
                <table class="table table-striped" id="grn-items-table">
                    <thead>
                        <tr>
                            <th>{{ __('Material') }}</th>
                            <th>{{ __('Unit') }}</th>
                            <th>{{ __('Ordered') }}</th>
                            <th>{{ __('Received') }}</th>
                            <th>{{ __('Accepted') }}</th>
                            <th>{{ __('Rejected') }}</th>
                            @if($isDirectGrn)
                            <th>{{ __('Price') }}</th>
                            <th>{{ __('Tax Amount') }}</th>
                            <th>{{ __('Subtotal') }}</th>
                            @endif
                            <th>{{ __('Remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $grnItems = $grn->items ?? collect(); @endphp
                        @forelse($grnItems as $item)
                        <tr>
                            <td>{{ optional($item->material)->name ?? 'N/A' }}</td>
                            <td>{{ optional(optional($item->material)->unit)->name ?? ($item->poItem->unit ?? '-') }}</td>
                            <td>{{ number_format($item->ordered_qty ?? 0, 2) }}</td>
                            <td>{{ number_format($item->received_qty ?? 0, 2) }}</td>
                            <td>{{ number_format($item->accepted_qty ?? 0, 2) }}</td>
                            <td>{{ number_format($item->rejected_qty ?? 0, 2) }}</td>
                            @if($isDirectGrn)
                            <td>{{ number_format($item->price ?? 0, 2) }}</td>
                            <td>{{ number_format($item->tax_amount ?? 0, 2) }}</td>
                            <td>{{ number_format($item->subtotal ?? 0, 2) }}</td>
                            @endif
                            <td>{{ $item->remarks ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $isDirectGrn ? 10 : 7 }}" class="text-center">{{ __('No items found') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Description & Remarks -->
    <div class="row mb-3">
        <div class="form-group col-md-6">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', old('description', $grn->description), ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter description')]) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
            {{ Form::textarea('remarks', old('remarks', $grn->remarks), ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter remarks')]) }}
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-primary" id="submit-grn">
        <i class="ti ti-save"></i> {{ __('Update GRN') }}
    </button>
</div>

{{ Form::close() }}

<script>
$(document).ready(function() {

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
            } else {
            }
        } catch (e) {
        }
    }

    // Form submission
    $('#grn-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        // Disable unused flow fields before building FormData
        var grnType = $('input[name="grn_type"]').val();
        if (grnType === 'direct') {
            $('#po-details').find('input, select').prop('disabled', true);
        } else {
            $('#direct-grn-section').find('input, select').prop('disabled', true);
        }
        
        var formData = new FormData(this);
        
        // Re-enable fields after capturing FormData
        $('#po-details, #direct-grn-section').find('input, select').prop('disabled', false);
        
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
                $('#po-details, #direct-grn-section').find('input, select').prop('disabled', false);
                var message = xhr.responseJSON.message || '{{ __("Failed to update GRN") }}';
                toastrs('Error', message, 'error');
            }
        });
    });
});
</script>
