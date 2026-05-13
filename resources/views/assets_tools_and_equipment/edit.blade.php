{{ Form::model($assetsToolsAndEquipment, ['route' => ['assets_tools_and_equipment.update', $assetsToolsAndEquipment->id], 'method' => 'PATCH', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">

        {{-- Material --}}
        <div class="form-group col-md-6">
            {{ Form::label('material_id', __('Material'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('material_id', $materials, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Material')]) }}
            @error('material_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

       {{-- Quantity --}}
        <div class="form-group col-md-6">
            {{ Form::label('quantity', __('Quantity'), ['class' => 'form-label']) }}<x-required></x-required>
            <div class="input-group">
                {{ Form::number('quantity', null, ['class' => 'form-control', 'required' => 'required', 'min' => 1]) }}
                <span class="input-group-text" id="unit-label">
                    {{ optional($assetsToolsAndEquipment->material->unit)->name ?? 'unit' }}
                </span>
            </div>
            @error('quantity') <small class="text-danger">{{ $message }}</small> @enderror
        </div>


        {{-- Site --}}
        <div class="form-group col-md-6">
           {{ Form::label('site_id', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $site, null, ['class' => 'form-control select2', 'placeholder' => __('Select Site')]) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Operational Status --}}
        <div class="form-group col-md-6">
            {{ Form::label('operational_status', __('Operational Status'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('operational_status', [
                'active' => 'Active',
                'breakdown' => 'Breakdown',
                'scrap' => 'Scrap'
            ], null, ['class' => 'form-control', 'required' => 'required']) }}
            @error('operational_status') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}

<script>
    $(document).ready(function () {
        $('#material_id').on('change', function () {
            let materialId = $(this).val();
            if (materialId) {
                $.ajax({
                    url: '/material/' + materialId + '/unit',
                    type: 'GET',
                    success: function (response) {
                        $('#unit-label').text(response.unit || 'unit');
                    },
                    error: function () {
                        $('#unit-label').text('unit');
                    }
                });
            } else {
                $('#unit-label').text('unit');
            }
        });

        // Trigger on page load
        $('#material_id').trigger('change');
    });
</script>

