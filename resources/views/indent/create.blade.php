{{-- Blade Template --}}
{{ Form::open(['route' => 'indent.store', 'enctype'=>'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        {{-- Indent Number --}}
        <div class="form-group col-md-4">
            {{ Form::label('indent_number', __('Indent Number'), ['class' => 'form-label']) }}
            {{ Form::text('indent_number', \App\Models\Indent::generateIndentNumber($selectedSiteId ?? null), ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- Indent Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('indent_date', __('Indent Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('indent_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-3 d-none">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}
            {{ Form::select('supplier_id', $suppliers->pluck('name', 'id')->prepend(__('Select Supplier'), ''), null, ['class' => 'form-control']) }}
        </div>

        {{-- Site --}}
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}
            {{ Form::select('site_id', $sites->pluck('name', 'id')->prepend(__('Select Site'), ''), $selectedSiteId ?? null, ['class' => 'form-control']) }}
        </div>

        <div class="form-group col-md-4">
            {{ Form::label('assign_to', __('Assign To'), ['class' => 'form-label']) }}<x-required></x-required>

            <select class="multi-select choices" id="assign_to" name="assign_to[]" multiple="multiple" 
                    data-placeholder="{{ __('Select Users ...') }}" required>
                @foreach($users as $id => $name)
                <option value="{{ $id }}">
                    {{ $name }}
                </option>
                @endforeach

            </select>

            <p class="text-danger d-none" id="user_validation">{{ __('Assign To field is required.') }}</p>
        </div>

       

        

        {{-- Delivery Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('delivery_date', __('Delivery Date'), ['class' => 'form-label']) }}
            {{ Form::date('delivery_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control']) }}
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
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>{{ __('Total Amount') }}:</strong></td>
                        <td><span id="total-amount">0.00</span></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
         {{-- Description --}}
        <div class="form-group col-md-6">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter description')]) }}
        </div>

        {{-- Remark --}}
        <div class="form-group col-md-6">
            {{ Form::label('remark', __('Remark'), ['class' => 'form-label']) }}
            {{ Form::textarea('remark', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter remark')]) }}
            @if ($errors->has('remark'))
                <span class="text-danger">{{ $errors->first('remark') }}</span>
            @endif
        </div>
    </div>
</div>

<div class="modal-footer">
    {{ Form::submit(__('Create Indent'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}

<script>
// Store categories in data attribute for use by global script
$('#indent-items-table').data('categories', {!! json_encode($categories) !!});
</script>
