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
        <!--        <div class="form-group col-md-4">
                    {{ Form::label('tools_and_equipment_id', __('Tools & Equipment'), ['class' => 'form-label']) }}
                    {{ Form::select(
                    'tools_and_equipment_id',
                    $tools_and_equipment_Id
                    ? [$tools_and_equipment_Id => $tools[$tools_and_equipment_Id] ?? '']
                    : $tools,
                    $tools_and_equipment_Id,
                    ['class' => 'form-control select2']
                    ) }}
                    @error('tools_and_equipment_id') <small class="text-danger">{{ $message }}</small> @enderror
                </div>-->
        @endif

        @if($transfer_type === 'tools_and_equipment')
        <div class="form-group col-md-4">
            {{ Form::label('tools_and_equipment_id', __('Tools & Equipment'), ['class' => 'form-label']) }}
            <select name="tools_and_equipment_id" id="tools_and_equipment_id" class="form-control select2">
                @foreach($tools as $id => $tool)
                @php
                $avaliable_qty = $tool['quantity'];
                @endphp
                <option value="{{ $id }}" data-stock="{{ $tool['quantity'] }}"
                        {{ $tools_and_equipment_Id == $id ? 'selected' : '' }}>
                    {{ $tool['name'] }}
                </option>
                @endforeach
            </select>
            @error('tools_and_equipment_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- ✅ Quantity field --}}
        <div class="form-group col-md-4">
            {{ Form::label('tools_and_equipment_qty', __('Quantity'), ['class' => 'form-label']) }}
            <input type="number" name="tools_and_equipment_qty" id="tools_and_equipment_qty"
                   class="form-control" min="1" value="{{ $avaliable_qty ? $avaliable_qty : 1 }}"   max="{{ $avaliable_qty ? $avaliable_qty : 1 }}">
            @error('tools_and_equipment_qty') <small class="text-danger">{{ $message }}</small> @enderror
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
        <div class="form-group col-md-4">
            {{ Form::label('from_site_id', __('From Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('from_site_id', $from_site_id, $fromSiteId, [
            'class' => 'form-control select2',
            'required' => 'required'
            ]) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>


        {{-- To Site --}}
        <div class="form-group col-md-4">
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
    const qtyInput = document.getElementById('tools_and_equipment_qty');
    const toolSelect = document.getElementById('tools_and_equipment_id');

    // When user types in quantity
    qtyInput.addEventListener('input', function () {
        let selectedOption = toolSelect.options[toolSelect.selectedIndex];
        let availableQty = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        let enteredQty = parseInt(this.value);

        if (enteredQty > availableQty) {
            alert('Quantity cannot exceed available stock (' + availableQty + ')');
            this.value = availableQty; // reset to max
        }
    });

    // When user changes tool, reset qty field
    toolSelect.addEventListener('change', function () {
        let selectedOption = this.options[this.selectedIndex];
        let availableQty = parseInt(selectedOption.getAttribute('data-stock')) || 0;

        qtyInput.value = '';
        qtyInput.setAttribute('max', availableQty);
    });
</script>







