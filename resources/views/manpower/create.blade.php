{{ Form::open(['url' => route('manpower.store'), 'class' => 'needs-validation', 'novalidate', 'id' => 'manpowerAjaxForm']) }}

{{-- Hidden field for activity_completed_id --}}
@if(isset($activityCompletedId))
<input type="hidden" name="activity_completed_id" value="{{ $activityCompletedId }}">
@endif

<div class="modal-body">
    <div class="row">

        {{-- Work Date --}}
        <div class="form-group col-md-4">
            {{ Form::label('work_date', __('Work Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('work_date', \Carbon\Carbon::now()->format('Y-m-d'), ['class' => 'form-control', 'required' => true]) }}
        </div>

        {{-- Supplier --}}
        <div class="form-group col-md-4">
            {{ Form::label('supplier_id', __('Supplier'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('supplier_id', $suppliers, null, ['class' => 'form-control select', 'required' => true]) }}
        </div>

        {{-- Site --}}
        <div class="form-group col-md-4">
            {{ Form::label('site_id', __('Site'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('site_id', $sites, null, ['class' => 'form-control select', 'required' => true]) }}
        </div>

    </div>

    <hr>

    {{-- ================= MANPOWER CARD START ================= --}}
    <div class="card shadow-sm mt-3">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Manpower Counts') }}</h5>

            <button type="button" id="add-manpower-row" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-plus"></i> {{ __('Add Row') }}
            </button>
        </div>

        <div class="card-body">

            {{-- Dynamic Rows Container --}}
            <div id="manpower-rows-container">

                <div class="row manpower-row mb-3">

                    <div class="col-md-5">
                        {{ Form::label('manpower_type_id', __('Manpower Type'), ['class' => 'form-label']) }}<x-required></x-required>
                        {{ Form::select(
                            'details[0][manpower_type_id]',
                            $manpowerTypes->pluck('name', 'id'),
                            null,
                            [
                                'class' => 'form-control select manpower-type-select',
                                'required' => true,
                                'placeholder' => __('Select Manpower Type')
                            ]
                        ) }}
                    </div>

                    <div class="col-md-5">
                        {{ Form::label('count', __('Count'), ['class' => 'form-label']) }}<x-required></x-required>
                        {{ Form::number(
                            'details[0][count]',
                            0,
                            [
                                'class' => 'form-control count-input',
                                'min' => 0,
                                'onclick' => 'this.select()',
                                'required' => true
                            ]
                        ) }}
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger w-100 remove-row">
                            {{ __('Remove') }}
                        </button>
                    </div>

                </div>

            </div>

            {{-- Total Count --}}
            <div class="form-group mt-4">
                {{ Form::label('total_count', __('Total Count'), ['class' => 'form-label']) }}
                {{ Form::number('total_count', 0, [
                    'class' => 'form-control',
                    'readonly' => true,
                    'id' => 'total_count'
                ]) }}
            </div>

            {{-- Warning --}}
            <div id="total-warning" class="alert alert-warning mt-2" style="display:none;">
                {{ __('Total count must be greater than zero to submit.') }}
            </div>

        </div>
    </div>
    {{-- ================= MANPOWER CARD END ================= --}}

    {{ Form::hidden('created_by', creatorId()) }}

    {{-- Custom Fields --}}
    @if(module_is_active('CustomField') && !$customFields->isEmpty())
        <div class="col-md-12 form-group mt-3">
            @include('custom-field::formBuilder')
        </div>
    @endif

</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>

{{ Form::close() }}

<script src="{{ asset('js/jquery-ui.min.js') }}"></script>
<script>
$(document).ready(function () {
    // Handle form submission via AJAX
    $('#manpowerAjaxForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var url = $(this).attr('action');
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if(response.status) {
                    // Close modal and reload
                    $('#commonModal').modal('hide');
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    alert(xhr.responseJSON.message);
                } else {
                    alert('Error saving manpower');
                }
            }
        });
    });
    
    let rowIndex = 0;
        let $container = $('#manpower-rows-container');
        
        // Manpower types for dropdown
        const manpowerTypes = @json($manpowerTypes->pluck('name', 'id'));

        function updateTotalCount() {
            let total = 0;

            $('.count-input').each(function () {
                const val = parseInt($(this).val()) || 0;
                total += val;
            });

            $('#total_count').val(total);

            // Enable or disable submit button
            if (total > 0) {
                $('.btn-primary[type="submit"]').prop('disabled', false);
                $('#total-warning').hide();
            } else {
                $('.btn-primary[type="submit"]').prop('disabled', true);
                $('#total-warning').show();
            }
        }

        function reindexRows() {
            rowIndex = 0;
            $('.manpower-row').each(function() {
                const $row = $(this);
                
                // Update select name
                $row.find('.manpower-type-select').attr('name', 'details[' + rowIndex + '][manpower_type_id]');
                
                // Update count input name
                $row.find('.count-input').attr('name', 'details[' + rowIndex + '][count]');
                
                rowIndex++;
            });
        }

        // Add new row
        $('#add-manpower-row').on('click', function () {
            rowIndex = $('.manpower-row').length;
            
            let options = '<option value="">{{ __("Select Manpower Type") }}</option>';
            for (const [id, name] of Object.entries(manpowerTypes)) {
                options += `<option value="${id}">${name}</option>`;
            }

            const newRow = `
                <div class="row manpower-row mb-3">
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Manpower Type') }}<x-required></x-required></label>
                        <select name="details[${rowIndex}][manpower_type_id]" class="form-control select manpower-type-select" required>
                            ${options}
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Count') }}<x-required></x-required></label>
                        <input type="number" name="details[${rowIndex}][count]" class="form-control count-input" value="0" min="0" onclick="this.select()" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger remove-row">{{ __('Remove') }}</button>
                    </div>
                </div>
            `;
            
            $container.append(newRow);
            
            // Reinitialize select2 if used
            if ($().select2) {
                $('.manpower-type-select').select2({
                    allowClear: true
                });
            }
            
            updateTotalCount();
        });

        // Remove row
        $container.on('click', '.remove-row', function () {
            const $row = $(this).closest('.manpower-row');
            const rowCount = $container.find('.manpower-row').length;
            
            if (rowCount > 1) {
                $row.remove();
                reindexRows();
                updateTotalCount();
            } else {
                alert('{{ __("At least one row is required.") }}');
            }
        });

        // Attach input listener for total count
        $container.on('input', '.count-input', updateTotalCount);

        // Initialize on load
        updateTotalCount();
        
        // Initialize select2
        if ($().select2) {
            $('.manpower-type-select').select2({
                allowClear: true
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

