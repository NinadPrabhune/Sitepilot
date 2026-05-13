{{ Form::open(array('route' => array('invoice.payment.store', $invoice->id),'method'=>'post','enctype' => 'multipart/form-data', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
<div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('date', __('Date'),['class'=>'form-label']) }}
            <div class="form-icon-user">
                {{Form::date('date',null,array('class'=>'form-control ','required'=>'required','placeholder'=>'Select Date'))}}
            </div>
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('amount', __('Amount'),['class'=>'form-label']) }}
            <div class="form-icon-user">
                {{ Form::number('amount',$invoice->getDue(), array('class' => 'form-control','required'=>'required','step'=>'0.01','max' => $invoice->getDue())) }}
            </div>
        </div>
        @if(module_is_active('Account'))
            <div class="form-group col-md-6">
                    {{ Form::label('account_id', __('Account'),['class'=>'form-label']) }}
                    {{ Form::select('account_id',$accounts,null, array('class' => 'form-control', 'required'=>'required','placeholder'=>'Select Account')) }}
            </div>
        @endif
        <div class="form-group {{ (module_is_active('Account')) ? 'col-md-6' : 'col-md-12'}}">
            {{ Form::label('reference', __('Reference'),['class'=>'form-label']) }}
            <div class="form-icon-user">
                {{ Form::tel('reference',null, array('class' => 'form-control','required'=>'required','placeholder'=>'Enter Reference')) }}
            </div>
        </div>
        <div class="form-group  col-md-12">
            {{ Form::label('description', __('Description'),['class'=>'form-label']) }}
            {{ Form::textarea('description', '', array('class' => 'form-control','rows'=>3, 'placeholder'=>'Enter Description')) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('add_receipt', __('Payment Receipt'), ['class' => 'form-label']) }}
            <div class="choose-files ">
                <label for="add_receipt">
                    <div class=" bg-primary "> <i class="ti ti-upload px-1"></i>{{ __('Choose file here') }}</div>
                    <input type="file" class="form-control file" name="add_receipt" id="add_receipt"
                        onchange="document.getElementById('blah').src = window.URL.createObjectURL(this.files[0])"
                        data-filename="add_receipt" required>
                        <p class="text-danger d-none" id="validation">
                        {{ __('This field is required.') }}</p>
                    <img id="blah" width="100" src="" />
                </label>
            </div>
        </div>

    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn  btn-primary" id="submit">
</div>
{{ Form::close() }}
<script>
    $(".needs-validation").on('submit',function() {
            var skill = $('#add_receipt').val();
            if (skill == '') {
                $('#validation').removeClass('d-none')
                return false;
            } else {
                $('#validation').addClass('d-none')
            }
        });
</script>
