{{ Form::open(['route' => 'workspace.store', 'enctype' => 'multipart/form-data']) }}
<div class="modal-body">
    {{-- Basic Info Section --}}
    <h5 class="mb-3">{{ __('Basic Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('name', __('Name'), ['class' => 'col-form-label']) }}<x-required />
                {{ Form::text('name', null, ['class' => 'form-control','required'=>'required','placeholder' => __('Enter Workspace Name')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('status', __('Status'), ['class' => 'col-form-label']) }}
                {{ Form::select('status', ['1' => __('Active'), '0' => __('Inactive')], null, ['class' => 'form-control']) }}
            </div>
        </div>
        <div class="col-md-3 d-none">
            <div class="form-group">
                {{ Form::label('slug', __('Slug'), ['class' => 'col-form-label']) }}
                {{ Form::text('slug', null, ['class' => 'form-control','placeholder' => __('Enter Workspace Slug')]) }}
            </div>
        </div>
         <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('logo', __('Logo'), ['class' => 'col-form-label']) }}
                {{ Form::file('logo', ['class' => 'form-control', 'accept' => 'image/*']) }}
                <small class="text-muted">{{ __('Max: 2MB, Supported: jpeg, jpg, png, gif, svg') }}</small>
            </div>
        </div>
    </div>
    <div class="row d-none">
        
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('domain_type', __('Domain Type'), ['class' => 'col-form-label']) }}
                {{ Form::select('domain_type', ['' => __('Select Domain Type'), 'custom' => __('Custom Domain'), 'subdomain' => __('Subdomain')], null, ['class' => 'form-control']) }}
            </div>
        </div>
    </div>
    <div class="row d-none">
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('domain', __('Domain'), ['class' => 'col-form-label']) }}
                {{ Form::text('domain', null, ['class' => 'form-control','placeholder' => __('example.com')]) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('subdomain', __('Subdomain'), ['class' => 'col-form-label']) }}
                {{ Form::text('subdomain', null, ['class' => 'form-control','placeholder' => __('subdomain')]) }}
            </div>
        </div>
    </div>

    {{-- Contact Info Section --}}
    <h5 class="mt-4 mb-3">{{ __('Contact Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('contact_person', __('Contact Person'), ['class' => 'col-form-label']) }}
                {{ Form::text('contact_person', null, ['class' => 'form-control','placeholder' => __('Enter Contact Person')]) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('phone', __('Phone'), ['class' => 'col-form-label']) }}
                {{ Form::text('phone', null, ['class' => 'form-control','placeholder' => __('Enter Phone Number')]) }}
                @error('phone')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('email', __('Email'), ['class' => 'col-form-label']) }}
                {{ Form::email('email', null, ['class' => 'form-control','placeholder' => __('Enter Email Address')]) }}
                @error('email')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- Address Section --}}
    <h5 class="mt-4 mb-3">{{ __('Address') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-12">
            <div class="form-group">
                {{ Form::label('address', __('Address'), ['class' => 'col-form-label']) }}
                {{ Form::textarea('address', null, ['class' => 'form-control','placeholder' => __('Enter Address'),'rows' => 2]) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('city', __('City'), ['class' => 'col-form-label']) }}
                {{ Form::text('city', null, ['class' => 'form-control','placeholder' => __('Enter City')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('state', __('State'), ['class' => 'col-form-label']) }}
                {{ Form::text('state', null, ['class' => 'form-control','placeholder' => __('Enter State')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('pincode', __('Pincode'), ['class' => 'col-form-label']) }}
                {{ Form::text('pincode', null, ['class' => 'form-control','placeholder' => __('Enter Pincode')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('country', __('Country'), ['class' => 'col-form-label']) }}
                {{ Form::text('country', null, ['class' => 'form-control','placeholder' => __('Enter Country')]) }}
            </div>
        </div>
    </div>
   

    {{-- Tax Info Section --}}
    <h5 class="mt-4 mb-3">{{ __('Tax Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('gst_number', __('GST Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('gst_number', null, ['class' => 'form-control','placeholder' => __('Enter GST Number')]) }}
                @error('gst_number')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('pan_number', __('PAN Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('pan_number', null, ['class' => 'form-control','placeholder' => __('Enter PAN Number')]) }}
                @error('pan_number')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- Additional Business Details --}}
    <h5 class="mt-4 mb-3">{{ __('Additional Details') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('website', __('Website'), ['class' => 'col-form-label']) }}
                {{ Form::text('website', null, ['class' => 'form-control','placeholder' => __('Enter Website URL')]) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('cin_no', __('CIN Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('cin_no', null, ['class' => 'form-control','placeholder' => __('Enter CIN Number')]) }}
            </div>
        </div>
       
    </div>
    <div class="row">
        <div class="col-12">
            <div class="form-group">
                {{ Form::label('terms_and_conditions', __('Terms and Conditions'), ['class' => 'col-form-label']) }}
                {{ Form::textarea('terms_and_conditions', null, ['class' => 'form-control', 'placeholder' => __('Enter Terms and Conditions'), 'rows' => 4]) }}
            </div>
        </div>
    </div>

    {{-- Bank Info Section --}}
    <h5 class="mt-4 mb-3">{{ __('Bank Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('bank_name', __('Bank Name'), ['class' => 'col-form-label']) }}
                {{ Form::text('bank_name', null, ['class' => 'form-control','placeholder' => __('Enter Bank Name')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('account_number', __('Account Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('account_number', null, ['class' => 'form-control','placeholder' => __('Enter Account Number')]) }}
                @error('account_number')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('ifsc_code', __('IFSC Code'), ['class' => 'col-form-label']) }}
                {{ Form::text('ifsc_code', null, ['class' => 'form-control','placeholder' => __('Enter IFSC Code')]) }}
                @error('ifsc_code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>
   
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    {{ Form::submit(__('Create'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}
