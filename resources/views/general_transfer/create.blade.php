{{ Form::open(['route' => 'general_transfer.store', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">

        {{-- Transfer Type --}}
        <div class="form-group col-md-4">
            {{ Form::label('transfer_type', __('Transfer Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select(
            'transfer_type',
            [$transfer_type => __(ucwords(str_replace('_', ' ', $transfer_type)))],
            $transfer_type,
            ['class' => 'form-control select2', 'required' => 'required']
            ) }}
            @error('transfer_type') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Transfer Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('transfer_date', __('Transfer Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('transfer_date', now()->toDateString(), ['class' => 'form-control', 'required' => 'required']) }}
            @error('transfer_date') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        @if($transfer_type === 'machinery')
        {{-- Machinery --}}
        <div class="form-group col-md-4">
            {{ Form::label('machinery_id', __('Machinery'), ['class' => 'form-label']) }}
            {{ Form::select('machinery_id', [$machineryId => $machineries[$machineryId]], $machineryId, [
            'class' => 'form-control select2'
            ]) }}
            @error('machinery_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        @endif



        @if($transfer_type === 'tools_and_equipment')
            <div class="form-group col-md-4">
                {{ Form::label('tools_and_equipment_id', __('Tools & Equipment'), ['class' => 'form-label']) }}
                {{ Form::select(
                    'tools_and_equipment_id',
                    $tools_and_equipment_Id
                        ? [$tools_and_equipment_Id => $tools[$tools_and_equipment_Id]['name']]
                        : $tools->mapWithKeys(fn($tool) => [$tool->id => $tool->material->name]),
                    $tools_and_equipment_Id,
                    ['class' => 'form-control select2']
                ) }}
                @error('tools_and_equipment_id')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
        @endif




        @if($transfer_type === 'employee')
        <div class="form-group col-md-4">
            {{ Form::label('employee_id', __('Employee'), ['class' => 'form-label']) }}
            {{ Form::select(
            'employee_id',
            $employee_Id
            ? [$employee_Id => $employees[$employee_Id] ?? '']
            : $employees,
            $employee_Id,
            ['class' => 'form-control select2']
            ) }}
            @error('employee_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        @endif





        <!--        {{-- Transfer End Date --}}
                <div class="form-group col-md-6">
                    {{ Form::label('transfer_date_end', __('Transfer End Date'), ['class' => 'form-label']) }}
                    {{ Form::date('transfer_date_end', null, ['class' => 'form-control']) }}
                    @error('transfer_date_end') <small class="text-danger">{{ $message }}</small> @enderror
                </div>-->

        {{-- From Site --}}
        <div class="form-group {{ $transfer_type === 'tools_and_equipment' ? 'col-md-4' : 'col-md-6' }}">
            {{ Form::label('from_site_id', __('From Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('from_site_id', $from_site_id, $fromSiteId, [
            'class' => 'form-control select2',
            'required' => 'required'
            ]) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        
        @if($transfer_type === 'tools_and_equipment')
        <div class="form-group col-md-2">
            {{ Form::label('avaliable_qty', __('Available Qty'), ['class' => 'form-label']) }}

            {{ Form::text(
                'avaliable_qty',
                $tools_and_equipment_Id
                    ? ($tools[$tools_and_equipment_Id]['quantity'] ?? '')
                    : '',
                ['class' => 'form-control', 'required' => true, 'readonly' => true, 'placeholder' => 'Enter Available Qty']
            ) }}
        </div>

        <div class="form-group col-md-2">
            {{ Form::label('transfer_qty', __('Transfer Qty'), ['class' => 'form-label']) }}
            {{ Form::text('transfer_qty', null, ['class' => 'form-control', 'required' => true, 'readonly' => false, 'placeholder' => 'Enter Transfer Qty']) }}
        </div>
        @endif

        {{-- To Site --}}
        <div class="form-group {{ $transfer_type === 'tools_and_equipment' ? 'col-md-4' : 'col-md-6' }}">
            {{ Form::label('to_site_id', __('To Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('to_site_id', $to_site_id, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Site')]) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>








    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create Transfer') }}" class="btn btn-primary">
</div>
{{ Form::close() }}


<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script>
    $(document).ready(function () {
        function toggleSupplierField() {
            if ($('#owned_by').val() === 'rental') {
                $('#supplier_field').show();
            } else {
                $('#supplier_field').hide();
            }
        }

        $('#owned_by').on('change', toggleSupplierField);
        toggleSupplierField(); // Initial check
    });
</script>
<script>
$(document).ready(function () {
    // Listen for changes in transfer_qty
    $('#transfer_qty').on('input change', function () {
        var transferQty = parseInt($(this).val()) || 0;
        var availableQty = parseInt($('#avaliable_qty').val()) || 0;

        // Call your function
        checkTransferQty(transferQty, availableQty);
    });

    function checkTransferQty(transferQty, availableQty) {
        if (transferQty > availableQty) {
            alert("Transfer quantity cannot be greater than available quantity.");
            // Optionally reset the field
            $('#transfer_qty').val(availableQty);
        }
    }
});
</script>



