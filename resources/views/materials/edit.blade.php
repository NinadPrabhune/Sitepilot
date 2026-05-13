{{ Form::model($material, array('route' => array('material.update', $material->id), 'method' => 'PUT', 'enctype'=>'multipart/form-data','class' => 'needs-validation', 'novalidate')) }}

<div class="modal-body">
    <div class="text-end">
        @if (module_is_active('AIAssistant'))
            @php
                $templateName = \Workdo\AIAssistant\Entities\AssistantTemplate::where('template_module', 'material')->where('module', 'Pos')->get();
            @endphp
            @if($templateName->isEmpty())
                @include('aiassistant::ai.generate_ai_btn',['template_module' => 'material','module'=>'General'])
            @else
                @include('aiassistant::ai.generate_ai_btn',['template_module' => 'material','module'=>'Pos'])
            @endif
        @endif
    </div>

    <div class="row">
        {{-- Name --}}
        <div class="form-group col-md-12">
            {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => true, 'placeholder' => 'Enter Name']) }}
        </div>

        {{-- SKU (Read-only) --}}
        <div class="form-group col-md-12">
            {{ Form::label('sku', __('SKU'), ['class' => 'form-label']) }}
            {{ Form::text('sku', null, ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        {{-- HSN/SAC --}}
        <div class="form-group col-md-6">
            {{ Form::label('hsn_sac', __('HSN/SAC'), ['class' => 'form-label']) }}
            {{ Form::text('hsn_sac', null, ['class' => 'form-control', 'placeholder' => 'Enter HSN/SAC Code']) }}
        </div>

        {{-- GST Master --}}
        <div class="form-group col-md-6">
            {{ Form::label('gst_master_id', __('GST Rate'), ['class' => 'form-label']) }}
            {{ Form::select('gst_master_id', $gstMasters, old('gst_master_id', $material->gst_master_id), ['class' => 'form-control', 'placeholder' => 'Select GST Rate']) }}
        </div>


        {{-- Category --}}
        <div class="form-group col-md-6">
            {{ Form::label('category_id', __('Category'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('category_id', $categories, null, ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Unit --}}
        <div class="form-group col-md-6">
            {{ Form::label('unit_id', __('Unit'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('unit_id', $units, null, ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Description --}}
        <div class="form-group col-md-12">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Enter Description']) }}
        </div>

        {{-- Price --}}
        <div class="form-group col-md-4">
            {{ Form::label('price', __('Price'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('price', null, ['class' => 'form-control', 'required' => true, 'step' => '0.01', 'placeholder' => 'Enter Price']) }}
        </div>

        {{-- Reorder Level --}}
        <div class="form-group col-md-4">
            {{ Form::label('reorder_level', __('Reorder Level'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('reorder_level', null, ['class' => 'form-control', 'required' => true, 'placeholder' => 'Enter Reorder Level']) }}
        </div>

        {{-- Status --}}
        <div class="form-group col-md-4">
            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('status', ['active' => 'Active', 'inactive' => 'Inactive'], null, ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Image --}}
        <div class="form-group col-md-6">
            {{ Form::label('image', __('Image'), ['class' => 'form-label']) }}
            {{ Form::file('image', ['class' => 'form-control', 'accept' => 'image/*', 'onchange' => 'previewImage(event)']) }}
            <small class="text-muted">Max size: 2MB</small>
            
        </div>
@php
    use Illuminate\Support\Facades\File;

    $imagePath = public_path($material->image);
    $imageUrl = File::exists($imagePath) && !empty($material->image)
        ? asset($material->image)
        : asset('images/material/No_Image_Available.jpeg');
@endphp
        <div class="form-group col-md-6">            
            <div class="mt-2">
                <img id="imagePreview" src="{{asset($imageUrl)}}" alt="Image Preview" style="max-width: 100px; " />
            </div>
        </div>


        {{-- Custom Fields --}}
        @if(module_is_active('CustomField') && !$customFields->isEmpty())
            <div class="col-md-12 form-group">
                <div class="tab-pane fade show form-label" id="tab-2" role="tabpanel">
                    @include('custom-field::formBuilder')
                </div>
            </div>
        @endif
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}


    <script src="{{ asset('js/jquery-ui.min.js') }}"></script>
    <script>
    function generateSKU(){
        var sku = 'SKU-' + Math.random().toString(24).substr(2, 7);
        $('input[name=sku]').val(sku.toUpperCase());
    }

    function previewImage(event) {
        const input = event.target;
        const preview = document.getElementById('imagePreview');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '#';
            preview.style.display = 'none';
        }
    }
</script>

