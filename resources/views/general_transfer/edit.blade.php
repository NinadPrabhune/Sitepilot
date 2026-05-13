{{ Form::model($machinery, ['route' => ['machineries.update', $machinery->id], 'method' => 'PUT','class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">

<!--    {{-- Owned By --}}
    <div class="form-group col-md-6">
        {{ Form::label('owned_by', __('Owned By'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::select('owned_by', ['owned' => 'Owned', 'rental' => 'Rental'], null, ['class' => 'form-control', 'id' => 'owned_by']) }}
        @error('owned_by') <small class="text-danger">{{ $message }}</small> @enderror
    </div>-->

    {{-- Supplier --}}
    <div class="form-group col-md-6" id="supplier_field" style="{{ $machinery->owned_by === 'rental' ? '' : 'display:none;' }}">
        {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::select('supplier_id', $suppliers, null, ['class' => 'form-control', 'placeholder' => __('Select Supplier')]) }}
        @error('supplier_id') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Machinery Name --}}
    <div class="form-group col-md-6">
        {{ Form::label('name', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Machinery Name')]) }}
        @error('name') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Category --}}
    <div class="form-group col-md-6">
        {{ Form::label('category_id', __('Category'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::select('category_id', $categories, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Category')]) }}
        @error('category_id') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Vehicle Number --}}
    <div class="form-group col-md-6">
        {{ Form::label('vehicle_number', __('Vehicle Number'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::text('vehicle_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Vehicle Number')]) }}
        @error('vehicle_number') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Model Number --}}
    <div class="form-group col-md-6">
        {{ Form::label('model_number', __('Model Number'), ['class' => 'form-label']) }}
        {{ Form::text('model_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Model Number')]) }}
        @error('model_number') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Manufacturer --}}
    <div class="form-group col-md-6">
        {{ Form::label('manufacturer', __('Manufacturer'), ['class' => 'form-label']) }}
        {{ Form::text('manufacturer', null, ['class' => 'form-control', 'placeholder' => __('Enter Manufacturer')]) }}
        @error('manufacturer') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Purchase Date --}}
    <div class="form-group col-md-6">
        {{ Form::label('purchase_date', __('Purchase Date'), ['class' => 'form-label']) }}
        {{ Form::date('purchase_date', $machinery->purchase_date, ['class' => 'form-control']) }}
        @error('purchase_date') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Maintenance Schedule --}}
    <div class="form-group col-md-6">
        {{ Form::label('maintenance_schedule', __('Maintenance Schedule'), ['class' => 'form-label']) }}
        {{ Form::date('maintenance_schedule', $machinery->maintenance_schedule, ['class' => 'form-control']) }}
        @error('maintenance_schedule') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Description --}}
    <div class="form-group col-md-6">
        {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
        {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter Description')]) }}
        @error('description') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Remarks --}}
    <div class="form-group col-md-6">
        {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
        {{ Form::textarea('remarks', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter Remarks')]) }}
        @error('remarks') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    {{-- Capacity --}}
    <div class="form-group col-md-6">
        {{ Form::label('capacity', __('Capacity'), ['class' => 'form-label']) }}
        {{ Form::text('capacity', null, ['class' => 'form-control', 'placeholder' => __('Enter Capacity')]) }}
        @error('capacity') <small class="text-danger">{{ $message }}</small> @enderror
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
    
    <div class="form-group col-md-6">
    <span id="site_field">
        {{ Form::label('site_id', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::select(
            'site_id',
            $sites,
            old('site_id', $machinery->site_id),   // 👈 use old() fallback to model value
            ['class' => 'form-control', 'placeholder' => __('Select Current Site')]
        ) }}
        @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
    </span>
</div>


</div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
