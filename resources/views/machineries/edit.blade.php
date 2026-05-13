{{ Form::model($machinery, ['route' => ['machineries.update', $machinery->id], 'method' => 'PUT','class' => 'needs-validation', 'novalidate', 'data-size' => 'xl']) }}
<div class="modal-body">
    <div class="row">

        {{-- Machine ID --}}
        <div class="form-group col-md-4">
            {{ Form::label('machine_id', __('Machine ID'), ['class' => 'form-label']) }}
            {{ Form::text('machine_id', $machinery->machine_id, ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- Owned By --}}
        <div class="form-group col-md-4">
            {{ Form::label('owned_by', __('Ownership'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('owned_by', ['owned' => 'Owned', 'rental' => 'Rental'], $machinery->owned_by, ['class' => 'form-control', 'id' => 'owned_by']) }}
            @error('owned_by') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-4" id="supplier_field" style="display: none;">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('supplier_id', $suppliers, $machinery->supplier_id, ['class' => 'form-control', 'placeholder' => __('Select Supplier')]) }}
            @error('supplier_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Machinery Name --}}
        <div class="form-group col-md-4">
            {{ Form::label('name', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Machinery Name')]) }}
            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Category --}}
        <div class="form-group col-md-4">
            {{ Form::label('category_id', __('Machine Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('category_id', $categories, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Machine Type')]) }}
            @error('category_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Vehicle Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('vehicle_number', __('Machine Number'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('vehicle_number', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Machine Number')]) }}
            @error('vehicle_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Model Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('model_number', __('Model Number'), ['class' => 'form-label']) }}
            {{ Form::text('model_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Model Number')]) }}
            @error('model_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Manufacturer --}}
        <div class="form-group col-md-4">
            {{ Form::label('manufacturer', __('Manufacturer'), ['class' => 'form-label']) }}
            {{ Form::text('manufacturer', null, ['class' => 'form-control', 'placeholder' => __('Enter Manufacturer')]) }}
            @error('manufacturer') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        

        {{-- Operational Status --}}
        <div class="form-group col-md-4">
            {{ Form::label('operational_status', __('Status'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('operational_status', [
                'active' => 'Active',
                'breakdown' => 'Breakdown',
                'scrap' => 'Scrap'
            ], null, ['class' => 'form-control', 'required' => 'required']) }}
            @error('operational_status') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        
        
        {{-- Current Site --}}
        <div class="form-group col-md-8">
            {{ Form::label('site_id', __('Current Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, old('site_id', $machinery->site_id), ['class' => 'form-control', 'placeholder' => __('Select Current Site')]) }}
            @error('site_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <hr>
        

        {{-- Rental Fields Section --}}
        <div id="rental_fields" style="display: none;">
            <h6 class="mb-3">{{ __('Rental Fields') }}</h6>
            <div class="row">
                {{-- Rate Type --}}
                <div class="form-group col-md-4">
                {{ Form::label('rate_type', __('Rate Type'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::select('rate_type', [
                    'hourly' => 'Hourly',
                    'daily' => 'Daily',
                    'monthly' => 'Monthly'
                ], $machinery->rate_type, ['class' => 'form-control', 'placeholder' => __('Select Rate Type')]) }}
                @error('rate_type') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Rate --}}
            <div class="form-group col-md-4">
                {{ Form::label('rate', __('Rate (Rs.)'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::number('rate', $machinery->rate, ['class' => 'form-control', 'step' => '0.01', 'placeholder' => __('Enter Rate')]) }}
                @error('rate') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Minimum Billing Hours --}}
            <div class="form-group col-md-4">
                {{ Form::label('minimum_billing_hours', __('Minimum Billing Hours'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::number('minimum_billing_hours', $machinery->minimum_billing_hours, ['class' => 'form-control', 'step' => '0.01', 'placeholder' => __('Enter Minimum Billing Hours')]) }}
                @error('minimum_billing_hours') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Diesel By Company - Company Policy: Always false - Hidden from UI --}}
                {{-- Supplier bears diesel costs for all rental machinery --}}
                <div class="form-group col-md-2" style="display: none;">
                    {{ Form::label('diesel_by_company', __('Diesel By Company'), ['class' => 'form-label']) }}
                    <div class="form-check mt-2">
                        {{ Form::checkbox('diesel_by_company', '1', false, ['class' => 'form-check-input', 'id' => 'diesel_by_company']) }}
                        {{ Form::label('diesel_by_company', __('Yes'), ['class' => 'form-check-label']) }}
                    </div>
                    @error('diesel_by_company') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                {{-- Operator By Supplier - Company Policy: Always true - Hidden from UI --}}
                {{-- Supplier provides operators for all rental machinery --}}
                <div class="form-group col-md-2" style="display: none;">
                    {{ Form::label('operator_by_supplier', __('Operator By Supplier'), ['class' => 'form-label']) }}
                    <div class="form-check mt-2">
                        {{ Form::checkbox('operator_by_supplier', '1', true, ['class' => 'form-check-input', 'id' => 'operator_by_supplier']) }}
                        {{ Form::label('operator_by_supplier', __('Yes'), ['class' => 'form-check-label']) }}
                    </div>
                    @error('operator_by_supplier') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

            {{-- Number of Operators --}}
            <div class="form-group col-md-4">
                {{ Form::label('number_of_operators', __('Number of Operators'), ['class' => 'form-label']) }}
                {{ Form::number('number_of_operators', $machinery->number_of_operators, ['class' => 'form-control', 'placeholder' => __('Enter Number of Operators')]) }}
                @error('number_of_operators') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Rental Agreement File --}}
                <div class="form-group col-md-4">
                    {{ Form::label('rental_agreement_file', __('Rental Agreement'), ['class' => 'form-label']) }}
                    @if($machinery->rental_agreement_file)
                        <div>
                            <a href="{{ asset('storage/machinery_documents/' . $machinery->rental_agreement_file) }}" target="_blank" class="btn btn-sm btn-info mb-2">{{ __('Download Current File') }}</a>
                        </div>
                    @endif
                    {{ Form::file('rental_agreement_file', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx']) }}
                    <small class="text-muted">{{ __('Allowed: pdf, doc, docx') }}</small>
                    @error('rental_agreement_file') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>
        </div>

        <hr>
       

        {{-- Owned Fields Section --}}
        <div id="owned_fields" style="display: none;">
             <h6 class="mb-3">{{ __('Owned Machinery Fields') }}</h6>
            <div class="row">
                {{-- Purchase Date --}}
                <div class="form-group col-md-4">
                {{ Form::label('purchase_date', __('Purchase Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('purchase_date', $machinery->purchase_date, ['class' => 'form-control']) }}
                @error('purchase_date') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Purchase Value --}}
            <div class="form-group col-md-4">
                {{ Form::label('purchase_value', __('Purchase Value (Rs.)'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::number('purchase_value', $machinery->purchase_value, ['class' => 'form-control', 'step' => '0.01', 'placeholder' => __('Enter Purchase Value')]) }}
                @error('purchase_value') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Insurance Due Date --}}
            <div class="form-group col-md-4">
                {{ Form::label('insurance_due_date', __('Insurance Due Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('insurance_due_date', $machinery->insurance_due_date, ['class' => 'form-control']) }}
                @error('insurance_due_date') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- PUC Due Date --}}
            <div class="form-group col-md-4">
                {{ Form::label('puc_due_date', __('PUC Due Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('puc_due_date', $machinery->puc_due_date, ['class' => 'form-control']) }}
                @error('puc_due_date') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Fitness Due Date --}}
            <div class="form-group col-md-4">
                {{ Form::label('fitness_due_date', __('Fitness Due Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('fitness_due_date', $machinery->fitness_due_date, ['class' => 'form-control']) }}
                @error('fitness_due_date') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Last Service Date --}}
            <div class="form-group col-md-4">
                {{ Form::label('last_service_date', __('Last Service Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('last_service_date', $machinery->last_service_date, ['class' => 'form-control']) }}
                @error('last_service_date') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Next Service Due Date --}}
            <div class="form-group col-md-4">
                {{ Form::label('maintenance_schedule', __('Next Service Due Date'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::date('maintenance_schedule', $machinery->maintenance_schedule, ['class' => 'form-control']) }}
                @error('maintenance_schedule') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            {{-- Ownership Documents File --}}
                <div class="form-group col-md-4">
                    {{ Form::label('ownership_documents_file', __('Ownership Documents (RC/Invoice)'), ['class' => 'form-label']) }}
                    @if($machinery->ownership_documents_file)
                        <div>
                            <a href="{{ asset('storage/machinery_documents/' . $machinery->ownership_documents_file) }}" target="_blank" class="btn btn-sm btn-info mb-2">{{ __('Download Current File') }}</a>
                        </div>
                    @endif
                    {{ Form::file('ownership_documents_file', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png']) }}
                    <small class="text-muted">{{ __('Allowed: pdf, doc, docx, jpg, jpeg, png') }}</small>
                    @error('ownership_documents_file') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>
        </div>

        {{-- Description --}}
        <div class="form-group col-md-4">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter Description')]) }}
            @error('description') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Remarks --}}
        <div class="form-group col-md-4">
            {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
            {{ Form::textarea('remarks', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter Remarks')]) }}
            @error('remarks') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Capacity --}}
        <div class="form-group col-md-4">
            {{ Form::label('capacity', __('Capacity'), ['class' => 'form-label']) }}
            {{ Form::text('capacity', null, ['class' => 'form-control', 'placeholder' => __('Enter Capacity')]) }}
            @error('capacity') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}

<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script>
    $(document).ready(function () {
        function toggleOwnershipFields() {
            const ownedBy = $('#owned_by').val();
            if (ownedBy === 'rental') {
                $('#rental_fields').show().find(':input').prop('disabled', false);
                $('#owned_fields').hide().find(':input').prop('disabled', true);
                $('#supplier_field').show();
            } else if (ownedBy === 'owned') {
                $('#rental_fields').hide().find(':input').prop('disabled', true);
                $('#owned_fields').show().find(':input').prop('disabled', false);
                $('#supplier_field').hide();
            } else {
                $('#rental_fields').hide().find(':input').prop('disabled', true);
                $('#owned_fields').hide().find(':input').prop('disabled', true);
                $('#supplier_field').hide();
            }
        }

        $('#owned_by').on('change', toggleOwnershipFields);
        toggleOwnershipFields(); // Initial check
    });
</script>
