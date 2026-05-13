{{ Form::open(['route' => 'opening-stock.store', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('project_id', __('Project / Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('project_id', $projects, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Project')]) }}
            @error('project_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('material_id', __('Material'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('material_id', $materials, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Material')]) }}
            @error('material_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('quantity', __('Quantity'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('quantity', null, ['class' => 'form-control', 'required' => 'required', 'min' => '0.0001', 'step' => '0.0001', 'placeholder' => __('Enter Quantity')]) }}
            @error('quantity') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('rate', __('Rate (Optional)'), ['class' => 'form-label']) }}
            {{ Form::number('rate', null, ['class' => 'form-control', 'min' => '0', 'step' => '0.01', 'placeholder' => __('Enter Rate')]) }}
            @error('rate') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('remarks', __('Remarks (Optional)'), ['class' => 'form-label']) }}
            {{ Form::textarea('remarks', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter Remarks')]) }}
            @error('remarks') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
