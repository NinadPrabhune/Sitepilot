{{-- DPR Edit Form - Completely Clean Version (NO INLINE SCRIPTS) --}}
{{-- This version contains ZERO inline JavaScript - all handled externally --}}

{{ Form::model($report, ['route' => ['daily-progress-reports.update', $report->id], 'class' => 'needs-validation', 'novalidate', 'files' => true]) }}
{{ Form::hidden('_method', 'PUT') }}

<div class="modal-body">
    <div class="row">
        {{-- Machinery Name --}}
        <div class="form-group col-md-3">
            {{ Form::label('machinery_id', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('machinery_id', ['' => __('Select Machinery')] + $machineryList->pluck('name', 'id')->toArray(), null, ['class' => 'form-control', 'required' => 'required', 'id' => 'machinery_id']) }}
        </div>

        {{-- Date --}}
        <div class="form-group col-md-3">
            {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('date', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
    </div>

    {{-- Machine Readings Section --}}
    <div class="row mt-3">
        <div class="form-group col-md-3">
            {{ Form::label('machine_start_reading', __('Start Reading'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('machine_start_reading', null, ['class' => 'form-control', 'required' => 'required', 'id' => 'machine_start_reading', 'step' => '0.01']) }}
            <div id="startReadingError" class="text-danger small"></div>
        </div>

        <div class="form-group col-md-3">
            {{ Form::label('machine_end_reading', __('End Reading'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('machine_end_reading', null, ['class' => 'form-control', 'required' => 'required', 'id' => 'machine_end_reading', 'step' => '0.01']) }}
            <div id="endReadingError" class="text-danger small"></div>
        </div>

        <div class="form-group col-md-3">
            {{ Form::label('machine_idle_reading', __('Idle Hours'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('machine_idle_reading', null, ['class' => 'form-control', 'required' => 'required', 'id' => 'machine_idle_reading', 'step' => '0.01']) }}
            <div id="idleHoursError" class="text-danger small"></div>
        </div>
    </div>

    {{-- Calculation Preview --}}
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6>{{ __('Calculation Preview') }}</h6>
                </div>
                <div class="card-body">
                    <div id="calculation-preview">
                        <p class="text-muted">{{ __('Enter machine readings to see calculation preview') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fuel Consumption Section --}}
    <div class="form-group col-md-12" id="fuel-consumption-form">
        <button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">{{ __('Add Item') }}</button>
        <table class="table table-bordered mt-2" id="consumption-items-table">
            <thead>
                <tr>
                    <th style="width: 30%;">{{ __('Material') }}</th>
                    <th>{{ __('Current Stock') }}</th>
                    <th>{{ __('Quantity | Unit') }}</th>
                    <th>{{ __('Remarks') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {{-- Items will be added dynamically --}}
            </tbody>
        </table>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>

{{ Form::close() }}

{{-- Data attributes for safe JavaScript access --}}
<div id="dpr-data" style="display: none;"
     data-materials="{{ json_encode($materials) }}"
     data-machinery="{{ json_encode($machinery) }}"
     data-is-rental="{{ json_encode($isRental) }}"
     data-report="{{ json_encode($report) }}"
     data-report-items="{{ json_encode($report->items ?? []) }}">
</div>

{{-- Load external JavaScript handler --}}
<script src="{{ asset('js/dpr-ajax-handler-v2.js') }}"></script>
