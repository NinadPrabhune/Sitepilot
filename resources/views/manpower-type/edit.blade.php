
{{ Form::model($manpower_type, ['route' => ['manpower-type.update', $manpower_type->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}

<div class="modal-body">
    <div class="row">
        {{-- Name --}}
        <div class="form-group col-md-12">
            @php
                $labelText = __('Name');
                $showTooltip = strlen($labelText) > 15;
            @endphp
            <label for="name" class="form-label"
                   @if($showTooltip)
                       data-bs-toggle="tooltip"
                       title="{{ $labelText }}"
                   @endif>
                {{ \Illuminate\Support\Str::limit($labelText, 15, '...') }}
            </label>
            <x-required />
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => true, 'placeholder' => __('Enter Name')]) }}
        </div>


        {{-- Custom Fields --}}
        @if(module_is_active('CustomField') && !$customFields->isEmpty())
            <div class="form-group col-md-12">
                <div class="tab-pane fade show form-label" id="tab-2" role="tabpanel">
                    @include('custom-field::formBuilder', ['model' => $manpower_type])
                </div>
            </div>
        @endif
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
