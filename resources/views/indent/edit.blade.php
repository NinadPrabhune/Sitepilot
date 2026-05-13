{{-- Edit Indent Form for AJAX Modal --}}
{{ Form::open(['route' => ['indent.update', $indent->id], 'enctype'=>'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
@method('PUT')
<div class="modal-body">
    <div class="row">
        {{-- Indent Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('indent_number', __('Indent Number'), ['class' => 'form-label']) }}
            {{ Form::text('indent_number', $indent->indent_number, ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- Indent Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('indent_date', __('Indent Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('indent_date', $indent->indent_date, ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-3 d-none">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}
            {{ Form::select('supplier_id', $suppliers->pluck('name', 'id')->prepend(__('Select Supplier'), ''), $indent->supplier_id, ['class' => 'form-control']) }}
        </div>

        {{-- Site --}}
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}
            {{ Form::select('site_id', $sites->pluck('name', 'id')->prepend(__('Select Site'), ''), $indent->site_id, ['class' => 'form-control']) }}
        </div>

        {{-- Assign To (Multiple Select) --}}
        @php
            $selectedAssignTo = $indent->assign_to ? explode(',', $indent->assign_to) : [];
        @endphp
        <div class="form-group col-md-4">
            {{ Form::label('assign_to', __('Assign To'), ['class' => 'form-label']) }}<x-required></x-required>
            
            <select class="multi-select choices" id="assign_to" name="assign_to[]" multiple="multiple" 
                    data-placeholder="{{ __('Select Users ...') }}" required>
                @foreach($users as $id => $name)
                <option value="{{ $id }}" {{ in_array($id, $selectedAssignTo) ? 'selected' : '' }}>
                    {{ $name }}
                </option>
                @endforeach
            </select>

            <p class="text-danger d-none" id="user_validation">{{ __('Assign To field is required.') }}</p>
        </div>

        {{-- Delivery Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('delivery_date', __('Delivery Date'), ['class' => 'form-label']) }}
            {{ Form::date('delivery_date', $indent->delivery_date, ['class' => 'form-control']) }}
            @if ($errors->has('delivery_date'))
                <span class="text-danger">{{ $errors->first('delivery_date') }}</span>
            @endif
        </div>

        {{-- Reference File --}}
        <div class="form-group col-md-4">
            {{ Form::label('reference_file', __('Reference File'), ['class' => 'form-label']) }}
            {{ Form::file('reference_file', ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png']) }}
            <small class="text-muted">{{ __('Accepted file types: pdf, doc, docx, jpg, jpeg, png (Max: 10MB)') }}</small>
            @if ($errors->has('reference_file'))
                <span class="text-danger">{{ $errors->first('reference_file') }}</span>
            @endif
            @if(!empty($indent->reference_file) && file_exists(public_path($indent->reference_file)))
                <div class="mt-2">
                    <strong>{{ __('Current File') }}:</strong>
                    <a href="{{ asset($indent->reference_file) }}" target="_blank" class="btn btn-sm btn-info">
                        <i class="ti ti-file"></i> {{ __('View File') }}
                    </a>
                </div>
            @endif
        </div>

        

        {{-- Items Table --}}
        <div class="form-group col-md-12" id="indent-items-table-div">
            <label class="form-label">{{ __('Indent Materials') }}</label>
            <button type="button" class="btn btn-sm btn-primary float-end" id="add-item-row">{{ __('Add Item') }}</button>
            
            <table class="table table-bordered" id="indent-items-table">
                <thead>
                    <tr>
                        <th style="width:20%;">{{ __('Category') }}</th>
                        <th style="width:30%;">{{ __('Material') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Subtotal') }}</th>
                        <th>{{ __('Remarks') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $itemIndex = 0; @endphp
                    @foreach($indent->items as $item)
                    <tr class="item-row" data-item-id="{{ $item->id }}" data-material-id="{{ $item->material_id }}" data-material-name="{{ $item->material->name ?? '' }}" data-category-id="{{ $item->material->category_id ?? '' }}" data-unit="{{ $item->unit }}" data-price="{{ $item->price }}">
                        <td>
                            <select class="form-control category-select"></select>
                        </td>
                        <td>
                            <select name="items[{{ $itemIndex }}][material_id]" class="form-control material-select"></select>
                            <input type="hidden" name="items[{{ $itemIndex }}][id]" value="{{ $item->id }}">
                        </td>
                        <td>
                            <input type="number" name="items[{{ $itemIndex }}][quantity]" class="form-control quantity" step="1" min="1" value="{{ $item->quantity }}" required>
                        </td>
                        <td>
                            <input type="text" name="items[{{ $itemIndex }}][unit]" class="form-control unit" value="{{ $item->unit }}" readonly>
                        </td>
                        <td>
                            <input type="number" name="items[{{ $itemIndex }}][price]" class="form-control price" step="1" min="0" value="{{ $item->price }}" required>
                        </td>
                        <td>
                            <span class="subtotal">{{ number_format($item->subtotal, 2) }}</span>
                        </td>
                        <td>
                            <input type="text" name="items[{{ $itemIndex }}][remarks]" class="form-control remarks" value="{{ $item->remarks }}" placeholder="{{ __('Remarks') }}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>
                        </td>
                    </tr>
                    @php $itemIndex++; @endphp
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>{{ __('Total Amount') }}:</strong></td>
                        <td><span id="total-amount">{{ number_format($indent->total_amount, 2) }}</span></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        {{-- Description --}}
        <div class="form-group col-md-6">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', $indent->description, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter description')]) }}
        </div>

        {{-- Remark --}}
        <div class="form-group col-md-6">
            {{ Form::label('remark', __('Remark'), ['class' => 'form-label']) }}
            {{ Form::textarea('remark', $indent->remark, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter remark')]) }}
            @if ($errors->has('remark'))
                <span class="text-danger">{{ $errors->first('remark') }}</span>
            @endif
        </div>
    </div>
</div>

<div class="modal-footer">
    {{ Form::submit(__('Update Indent'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}

<script>
// Store categories in data attribute for use by global script
$('#indent-items-table').data('categories', {!! json_encode($categories) !!});
</script>
