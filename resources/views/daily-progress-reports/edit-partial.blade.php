{{-- DPR Edit Form - HTML Only (No Inline Scripts) --}}
{{-- This template contains only HTML - all JavaScript is handled externally --}}

<div class="row">
    {{-- Machinery Name --}}
    <div class="form-group col-md-3">
        {{ Form::label('machinery_id', __('Machinery Name'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::select('machinery_id', ['' => __('Select Machinery')] + $machineryList->pluck('name', 'id')->toArray(), $report->machinery_id, ['class' => 'form-control', 'required' => 'required', 'id' => 'machinery_id']) }}
    </div>

    {{-- Date --}}
    <div class="form-group col-md-3">
        {{ Form::label('date', __('Date'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::date('date', $report->date, ['class' => 'form-control', 'required' => 'required']) }}
    </div>
</div>

{{-- Machine Readings Section --}}
<div class="row mt-3">
    <div class="form-group col-md-3">
        {{ Form::label('machine_start_reading', __('Start Reading'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::number('machine_start_reading', $report->start_reading, ['class' => 'form-control', 'required' => 'required', 'id' => 'machine_start_reading', 'step' => '0.01']) }}
        <div id="startReadingError" class="text-danger small"></div>
    </div>

    <div class="form-group col-md-3">
        {{ Form::label('machine_end_reading', __('End Reading'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::number('machine_end_reading', $report->end_reading, ['class' => 'form-control', 'required' => 'required', 'id' => 'machine_end_reading', 'step' => '0.01']) }}
        <div id="endReadingError" class="text-danger small"></div>
    </div>

    <div class="form-group col-md-3">
        {{ Form::label('machine_idle_reading', __('Idle Hours'), ['class' => 'form-label']) }}<x-required></x-required>
        {{ Form::number('machine_idle_reading', $report->idle_hours, ['class' => 'form-control', 'required' => 'required', 'id' => 'machine_idle_reading', 'step' => '0.01']) }}
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
@if(!$isRental || !empty($report->items))
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
                @if(!$isRental || !empty($report->items))
                    @foreach($report->items as $index => $item)
                        <tr>
                            <td>
                                <select name="items[{{ $index }}][material_id]" class="form-control item-material" required>
                                    <option value="">{{ __('Select Material') }}</option>
                                    @foreach($materials as $id => $material)
                                        <option value="{{ $id }}" {{ $item->material_id == $id ? 'selected' : '' }}>
                                            {{ $material['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input type="text" class="form-control item-stock" readonly
                                           value="{{ $materials[$item->material_id]['total_qty'] ?? 0 }}"/>
                                    <span class="input-group-text item-stock-unit">
                                        {{ $materials[$item->material_id]['unit'] ?? 'unit' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input type="number" name="items[{{ $index }}][quantity]" class="form-control item-quantity"
                                           min="1" value="{{ $item->quantity }}" required>
                                    <input type="hidden" name="items[{{ $index }}][unit]" class="item-unit"
                                           value="{{ $item->unit_name }}">
                                    <span class="input-group-text item-unit-label">
                                        {{ $item->unit_name }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <input type="text" name="items[{{ $index }}][remarks]" class="form-control"
                                       value="{{ $item->remarks }}">
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger remove-item-row">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
@endif

{{-- Hidden Data for JavaScript --}}
<div id="dpr-data" style="display: none;"
     data-materials="{{ json_encode($materials) }}"
     data-machinery="{{ json_encode($machinery) }}"
     data-is-rental="{{ json_encode($isRental) }}"
     data-report-items="{{ json_encode($report->items ?? []) }}">
</div>
