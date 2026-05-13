{{Form::model($workSpace,array('route' => array('workspace.update', $workSpace->id), 'method' => 'PUT', 'id' => 'workspace-edit-form', 'enctype' => 'multipart/form-data')) }}
<div class="modal-body">
    {{-- Basic Info Section --}}
    <h5 class="mb-3">{{ __('Basic Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('name', __('Name'), ['class' => 'col-form-label']) }}<x-required />
                {{ Form::text('name', old('name', $workSpace->name), ['class' => 'form-control','required'=>'required','placeholder' => __('Enter Workspace Name')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('status', __('Status'), ['class' => 'col-form-label']) }}
                {{ Form::select('status', ['1' => __('Active'), '0' => __('Inactive')], old('status', $workSpace->status), ['class' => 'form-control']) }}
            </div>
        </div>
        <div class="col-md-3 d-none">
            <div class="form-group">
                {{ Form::label('slug', __('Slug'), ['class' => 'col-form-label']) }}
                {{ Form::text('slug', old('slug', $workSpace->slug), ['class' => 'form-control','required'=>'required','placeholder' => __('Enter Workspace Slug')]) }}
                <span id="slug-msg"></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('logo', __('Logo'), ['class' => 'col-form-label']) }}
                {{ Form::file('logo', ['class' => 'form-control', 'accept' => 'image/*']) }}
                <small class="text-muted">{{ __('Max: 2MB, Supported: jpeg, jpg, png, gif, svg') }}</small>
                @php
                    $logoPath = public_path($workSpace->logo);
                    $logoUrl = !empty($workSpace->logo) && file_exists($logoPath)
                        ? asset($workSpace->logo)
                        : asset('images/material/No_Image_Available.jpeg');
                @endphp
                <div class="mt-2">
                    <img src="{{ $logoUrl }}" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                    @if(!empty($workSpace->logo) && file_exists($logoPath))
                        <a href="{{ asset($workSpace->logo) }}" target="_blank" class="btn btn-sm btn-info ms-2">{{ __('View') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="row d-none">
        
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('domain_type', __('Domain Type'), ['class' => 'col-form-label']) }}
                {{ Form::select('domain_type', ['' => __('Select Domain Type'), 'custom' => __('Custom Domain'), 'subdomain' => __('Subdomain')], old('domain_type', $workSpace->domain_type), ['class' => 'form-control']) }}
            </div>
        </div>
    </div>
    <div class="row d-none">
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('domain', __('Domain'), ['class' => 'col-form-label']) }}
                {{ Form::text('domain', old('domain', $workSpace->domain), ['class' => 'form-control','placeholder' => __('example.com')]) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('subdomain', __('Subdomain'), ['class' => 'col-form-label']) }}
                {{ Form::text('subdomain', old('subdomain', $workSpace->subdomain), ['class' => 'form-control','placeholder' => __('subdomain')]) }}
            </div>
        </div>
    </div>

    {{-- Contact Info Section --}}
    <h5 class="mt-4 mb-3">{{ __('Contact Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('contact_person', __('Contact Person'), ['class' => 'col-form-label']) }}
                {{ Form::text('contact_person', old('contact_person', $workSpace->contact_person), ['class' => 'form-control','placeholder' => __('Enter Contact Person')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('phone', __('Phone'), ['class' => 'col-form-label']) }}
                {{ Form::text('phone', old('phone', $workSpace->phone), ['class' => 'form-control','placeholder' => __('Enter Phone Number')]) }}
                @error('phone')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('email', __('Email'), ['class' => 'col-form-label']) }}
                {{ Form::email('email', old('email', $workSpace->email), ['class' => 'form-control','placeholder' => __('Enter Email Address')]) }}
                @error('email')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('website', __('Website'), ['class' => 'col-form-label']) }}
                {{ Form::text('website', old('website', $workSpace->website), ['class' => 'form-control','placeholder' => __('Enter Website URL')]) }}
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
                {{ Form::textarea('address', old('address', $workSpace->address), ['class' => 'form-control','placeholder' => __('Enter Address'),'rows' => 2]) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('city', __('City'), ['class' => 'col-form-label']) }}
                {{ Form::text('city', old('city', $workSpace->city), ['class' => 'form-control','placeholder' => __('Enter City')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('state', __('State'), ['class' => 'col-form-label']) }}
                {{ Form::text('state', old('state', $workSpace->state), ['class' => 'form-control','placeholder' => __('Enter State')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('pincode', __('Pincode'), ['class' => 'col-form-label']) }}
                {{ Form::text('pincode', old('pincode', $workSpace->pincode), ['class' => 'form-control','placeholder' => __('Enter Pincode')]) }}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {{ Form::label('country', __('Country'), ['class' => 'col-form-label']) }}
                {{ Form::text('country', old('country', $workSpace->country), ['class' => 'form-control','placeholder' => __('Enter Country')]) }}
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
                {{ Form::text('gst_number', old('gst_number', $workSpace->gst_number), ['class' => 'form-control','placeholder' => __('Enter GST Number')]) }}
                @error('gst_number')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('pan_number', __('PAN Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('pan_number', old('pan_number', $workSpace->pan_number), ['class' => 'form-control','placeholder' => __('Enter PAN Number')]) }}
                @error('pan_number')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('cin_no', __('CIN Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('cin_no', old('cin_no', $workSpace->cin_no), ['class' => 'form-control','placeholder' => __('Enter CIN Number')]) }}
            </div>
        </div>
    </div>

    
    
    

    {{-- Bank Info Section --}}
    <h5 class="mt-4 mb-3">{{ __('Bank Info') }}</h5>
    <hr class="mt-0">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('bank_name', __('Bank Name'), ['class' => 'col-form-label']) }}
                {{ Form::text('bank_name', old('bank_name', $workSpace->bank_name), ['class' => 'form-control','placeholder' => __('Enter Bank Name')]) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('account_number', __('Account Number'), ['class' => 'col-form-label']) }}
                {{ Form::text('account_number', old('account_number', $workSpace->account_number), ['class' => 'form-control','placeholder' => __('Enter Account Number')]) }}
                @error('account_number')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('ifsc_code', __('IFSC Code'), ['class' => 'col-form-label']) }}
                {{ Form::text('ifsc_code', old('ifsc_code', $workSpace->ifsc_code), ['class' => 'form-control','placeholder' => __('Enter IFSC Code')]) }}
                @error('ifsc_code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="form-group">
                {{ Form::label('terms_and_conditions', __('Terms and Conditions'), ['class' => 'col-form-label']) }}
                {{ Form::textarea('terms_and_conditions', old('terms_and_conditions', $workSpace->terms_and_conditions), ['class' => 'form-control', 'placeholder' => __('Enter Terms and Conditions'), 'rows' => 4]) }}
            </div>
        </div>
    </div>
    
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    {{Form::submit(__('Update'),array('class'=>'btn btn-primary'))}}
</div>
{{ Form::close() }}

<script>
    $('#workspace-edit-form').submit(function (e) {
        e.preventDefault();
        var slug = $('#slug').val();
        $.ajax({
            url: '{{ route('workspace.check') }}',
            type: 'POST',
            data: {
                "_token": "{{ csrf_token() }}",
                "workspace": "{{ $workSpace->id }}",
                "slug": slug,
            },
            beforeSend: function () {
                $(".loader-wrapper").removeClass('d-none');
            },
            success: function(data)
            {
                $('#slug-msg').empty();
                if(data.success)
                {
                    $('#workspace-edit-form').unbind('submit').submit();
                }
                else
                {
                    $('#slug-msg').addClass('text-danger').text(data.error);
                }
            }
        });
    });
</script>
