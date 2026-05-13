{{ Form::model($supplier, array('route' => array('supplier.update', $supplier->id), 'method' => 'PUT', 'enctype'=>'multipart/form-data','class' => 'needs-validation', 'novalidate')) }}

<div class="modal-body">
    <div class="text-end">
        @if (module_is_active('AIAssistant'))
            @php
                $templateName = \Workdo\AIAssistant\Entities\AssistantTemplate::where('template_module', 'supplier')->where('module', 'Pos')->get();
            @endphp
            @if($templateName->isEmpty())
                @include('aiassistant::ai.generate_ai_btn',['template_module' => 'supplier','module'=>'General'])
            @else
                @include('aiassistant::ai.generate_ai_btn',['template_module' => 'supplier','module'=>'Pos'])
            @endif
        @endif
    </div>

    <div class="row">

        {{-- Supplier Name --}}
        <div class="form-group col-md-6">
            {{ Form::label('name', __('Supplier Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required', 'placeholder' => __('Enter Supplier Name')]) }}
            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Category --}}
        <div class="form-group col-md-6">
            {{ Form::label('category_id', __('Category'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('category_id', $categories, null, ['class' => 'form-control', 'required', 'placeholder' => __('Select Category')]) }}
            @error('category_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Type --}}
        <div class="form-group col-md-6">
            {{ Form::label('type', __('Type'), ['class' => 'form-label']) }}
            {{ Form::select('type', ['company' => 'Company', 'individual' => 'Individual'], $supplier->type, ['class' => 'form-control', 'placeholder' => __('Select Type')]) }}
            @error('type') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Contact Person --}}
        <div class="form-group col-md-6">
            {{ Form::label('contact_person', __('Contact Person'), ['class' => 'form-label']) }}
            {{ Form::text('contact_person', null, ['class' => 'form-control', 'placeholder' => __('Enter Contact Person')]) }}
            @error('contact_person') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Phone --}}
        <div class="form-group col-md-6">
            {{ Form::label('phone', __('Phone'), ['class' => 'form-label']) }}
            {{ Form::text('phone', null, ['class' => 'form-control', 'placeholder' => __('Enter Phone Number')]) }}
            @error('phone') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Email --}}
        <div class="form-group col-md-6">
            {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
            {{ Form::email('email', null, ['class' => 'form-control', 'placeholder' => __('Enter Email')]) }}
            @error('email') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Address --}}
        <div class="form-group col-md-12">
            {{ Form::label('address', __('Address'), ['class' => 'form-label']) }}
            {{ Form::textarea('address', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('Enter Address')]) }}
            @error('address') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- City --}}
        <div class="form-group col-md-6">
            {{ Form::label('city', __('City'), ['class' => 'form-label']) }}
            {{ Form::text('city', null, ['class' => 'form-control', 'placeholder' => __('Enter City')]) }}
            @error('city') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- State --}}
        <div class="form-group col-md-6">
            {{ Form::label('state', __('State'), ['class' => 'form-label']) }}
            {{ Form::text('state', 'Maharashtra', ['class' => 'form-control', 'placeholder' => __('Enter State')]) }}
            @error('state') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Pincode --}}
        <div class="form-group col-md-6">
            {{ Form::label('pincode', __('Pincode'), ['class' => 'form-label']) }}
            {{ Form::text('pincode', null, ['class' => 'form-control', 'placeholder' => __('Enter Pincode')]) }}
            @error('pincode') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Country --}}
        <div class="form-group col-md-6">
            {{ Form::label('country', __('Country'), ['class' => 'form-label']) }}
            {{ Form::text('country', 'India', ['class' => 'form-control', 'placeholder' => __('Enter Country')]) }}
            @error('country') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- UPI Screenshot 1 --}}
        <div class="form-group col-md-6">
            {{ Form::label('upi_screenshot_1', __('UPI Screenshot 1'), ['class' => 'form-label']) }}
            {{ Form::file('upi_screenshot_1', ['class' => 'form-control', 'accept' => 'image/*', 'onchange' => 'previewUPI1(event)']) }}
            <small class="text-muted">Max size: 2MB</small>
            @error('upi_screenshot_1') <small class="text-danger">{{ $message }}</small> @enderror

            @php
                use Illuminate\Support\Facades\File;

                $imagePath1 = public_path($supplier->upi_screenshot_1);
                $imageUrl1 = File::exists($imagePath1) && !empty($supplier->upi_screenshot_1)
                    ? asset($supplier->upi_screenshot_1)
                    : asset('images/item/No_Image_Available.jpeg');
                    
                $imagePath2 = public_path($supplier->upi_screenshot_2);
                $imageUrl2 = File::exists($imagePath2) && !empty($supplier->upi_screenshot_2)
                    ? asset($supplier->upi_screenshot_2)
                    : asset('images/item/No_Image_Available.jpeg');
            @endphp
            
            <div class="mt-2">
                <img id="upiPreview1" src="{{ $imageUrl1 }}" alt="UPI Screenshot 1 Preview" style="max-width: 100px; display: block;" />
            </div>
        </div>

        {{-- UPI Screenshot 2 --}}
        <div class="form-group col-md-6">
            {{ Form::label('upi_screenshot_2', __('UPI Screenshot 2'), ['class' => 'form-label']) }}
            {{ Form::file('upi_screenshot_2', ['class' => 'form-control', 'accept' => 'image/*', 'onchange' => 'previewUPI2(event)']) }}
            <small class="text-muted">Max size: 2MB</small>
            @error('upi_screenshot_2') <small class="text-danger">{{ $message }}</small> @enderror

            <div class="mt-2">
                <img id="upiPreview2" src="{{ $imageUrl2 }}" alt="UPI Screenshot 2 Preview" style="max-width: 100px; display: block;" />
            </div>
        </div>


        {{-- GST Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('gst_number', __('GST Number'), ['class' => 'form-label']) }}
            {{ Form::text('gst_number', null, ['class' => 'form-control', 'placeholder' => __('Enter GST Number')]) }}
            @error('gst_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- PAN Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('pan_number', __('PAN Number'), ['class' => 'form-label']) }}
            {{ Form::text('pan_number', null, ['class' => 'form-control', 'placeholder' => __('Enter PAN Number')]) }}
            @error('pan_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Registration Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('registration_number', __('Registration Number'), ['class' => 'form-label']) }}
            {{ Form::text('registration_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Registration Number')]) }}
            @error('registration_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Bank Name --}}
        <div class="form-group col-md-6">
            {{ Form::label('bank_name', __('Bank Name'), ['class' => 'form-label']) }}
            {{ Form::text('bank_name', null, ['class' => 'form-control', 'placeholder' => __('Enter Bank Name')]) }}
            @error('bank_name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Account Number --}}
        <div class="form-group col-md-6">
            {{ Form::label('account_number', __('Account Number'), ['class' => 'form-label']) }}
            {{ Form::text('account_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Account Number')]) }}
            @error('account_number') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- IFSC Code --}}
        <div class="form-group col-md-6">
            {{ Form::label('ifsc_code', __('IFSC Code'), ['class' => 'form-label']) }}
            {{ Form::text('ifsc_code', null, ['class' => 'form-control', 'placeholder' => __('Enter IFSC Code')]) }}
            @error('ifsc_code') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        {{-- Payment Terms --}}
        <div class="form-group col-md-6">
            {{ Form::label('payment_terms', __('Payment Terms'), ['class' => 'form-label']) }}
            {{ Form::text('payment_terms', null, ['class' => 'form-control', 'placeholder' => __('Enter Payment Terms')]) }}
            @error('payment_terms') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        
        
        

    <div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>
{{ Form::close() }}

<script>
function previewUPI1(event) {
    const reader = new FileReader();
    reader.onload = function(){
        document.getElementById('upiPreview1').src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}

function previewUPI2(event) {
    const reader = new FileReader();
    reader.onload = function(){
        document.getElementById('upiPreview2').src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}
</script>
