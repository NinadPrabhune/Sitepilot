@extends('layouts.main')

@section('page-title')
    {{ __('Create Material Return') }}
@endsection

@section('page-breadcrumb')
    {{ __('Material Return') }}
@endsection

@section('page-action')
    <div class="d-flex">
        <a href="{{ route('material-returns.index') }}" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <x-card title="Create Material Return">
                {{-- Validation Errors Display --}}
                @if ($errors->any())
                    <x-alert type="error" :dismissible="true">
                        <strong>{{ __('Please fix the following errors:') }}</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                {{ Form::open(['route' => 'material-returns.store', 'method' => 'POST', 'id' => 'material-return-form']) }}
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="issue_id" class="form-label">{{ __('Link to Issue (Optional)') }}</label>
                            <select name="issue_id" id="issue_id" class="form-select">
                                <option value="">{{ __('Select Issue (Optional)') }}</option>
                                @foreach($issues as $issue)
                                    <option value="{{ $issue->id }}" {{ $selectedIssueId == $issue->id ? 'selected' : '' }}>{{ $issue->issue_number }} - {{ $issue->issue_date->format('d-m-Y') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <x-form-input
                            name="return_date"
                            label="Return Date"
                            type="date"
                            :value="\Carbon\Carbon::now()->toDateString()"
                            :required="true"
                        />
                    </div>
                    <div class="col-md-6">
                        <x-form-input
                            name="remarks"
                            label="Remarks"
                            type="textarea"
                            :rows="2"
                        />
                    </div>
                </div>

                <hr>
                <h5>{{ __('Material Items') }}</h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="items-table">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Remarks') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="items[0][material_id]" class="form-select material-select" required>
                                        <option value="">{{ __('Select Material') }}</option>
                                        @foreach($materials as $material)
                                            <option value="{{ $material->id }}" data-unit="{{ $material->unit ? $material->unit->name : '' }}">{{ $material->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]" class="form-control quantity-input" step="0.01" min="0.01" required>
                                    <div class="quantity-feedback"></div>
                                </td>
                                <td>
                                    <input type="number" name="items[0][rate]" class="form-control rate-input" step="0.01" min="0">
                                </td>
                                <td>
                                    <input type="text" name="items[0][remarks]" class="form-control">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="add-row"><i class="ti ti-plus"></i> {{ __('Add Row') }}</button>

                <hr>
                <div class="row">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">{{ __('Create Material Return') }}</button>
                    </div>
                </div>
                {{ Form::close() }}
            </x-card>
        </div>
    </div>
@endsection

@push('script-page')
<script>
    $(document).ready(function() {
        var rowIndex = 1;
        var issues = @json($issues);

        // Issue select change - load items
        $('#issue_id').change(function() {
            var issueId = $(this).val();
            
            if (issueId) {
                // Show loading state
                $('#items-table tbody').html('<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">{{ __("Loading...") }}</span></div></td></tr>');
                
                $.ajax({
                    url: "{{ route('material-returns.get-issue-details') }}",
                    type: 'POST',
                    data: {
                        issue_id: issueId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            var issue = response.issue;
                            $('#items-table tbody').empty();
                            rowIndex = 0;
                            
                            $.each(issue.items, function(index, item) {
                                var newRow = `
                                    <tr>
                                        <td>
                                            <select name="items[${rowIndex}][material_id]" class="form-select material-select" required>
                                                <option value="">{{ __('Select Material') }}</option>
                                                @foreach($materials as $material)
                                                <option value="{{ $material->id }}" data-unit="{{ $material->unit ? $material->unit->name : '' }}" ${item.material_id == {{ $material->id }} ? 'selected' : ''}>{{ $material->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" value="${item.quantity}" max="${item.quantity}" required>
                                            <div class="quantity-feedback"></div>
                                        </td>
                                        <td>
                                            <input type="number" name="items[${rowIndex}][rate]" class="form-control rate-input" step="0.01" min="0" value="${item.rate || ''}">
                                        </td>
                                        <td>
                                            <input type="text" name="items[${rowIndex}][remarks]" class="form-control">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>
                                        </td>
                                    </tr>
                                `;
                                $('#items-table tbody').append(newRow);
                                rowIndex++;
                            });
                        }
                    },
                    error: function(xhr) {
                        $('#items-table tbody').html('<tr><td colspan="5" class="text-center text-danger">{{ __("Failed to load issue details") }}</td></tr>');
                    }
                });
            }
        });

        // Add row
        $('#add-row').click(function() {
            var newRow = `
                <tr>
                    <td>
                        <select name="items[${rowIndex}][material_id]" class="form-select material-select" required>
                            <option value="">{{ __('Select Material') }}</option>
                            @foreach($materials as $material)
                            <option value="{{ $material->id }}" data-unit="{{ $material->unit ? $material->unit->name : '' }}">{{ $material->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" required>
                        <div class="quantity-feedback"></div>
                    </td>
                    <td>
                        <input type="number" name="items[${rowIndex}][rate]" class="form-control rate-input" step="0.01" min="0">
                    </td>
                    <td>
                        <input type="text" name="items[${rowIndex}][remarks]" class="form-control">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button>
                    </td>
                </tr>
            `;
            $('#items-table tbody').append(newRow);
            rowIndex++;
        });

        // Remove row
        $(document).on('click', '.remove-row', function() {
            if ($('#items-table tbody tr').length > 1) {
                $(this).closest('tr').remove();
            } else {
                showValidationError('{{ __("At least one item is required") }}');
            }
        });

        // Quantity validation against issued quantity
        $(document).on('input change', '.quantity-input', function() {
            var $input = $(this);
            var quantity = parseFloat($input.val()) || 0;
            var maxReturn = parseFloat($input.attr('max')) || 0;
            var $row = $input.closest('tr');
            var $feedback = $row.find('.quantity-feedback');
            
            // Clear previous feedback
            $feedback.empty();
            
            if (maxReturn > 0 && quantity > maxReturn) {
                $input.val(maxReturn);
                $input.addClass('is-invalid');
                $feedback.html('<div class="text-danger small mt-1">{{ __("Return quantity cannot exceed issued quantity. Maximum: ") }}' + maxReturn + '</div>');
            } else if (quantity > 0) {
                $input.removeClass('is-invalid').addClass('is-valid');
                $feedback.html('<div class="text-success small mt-1">{{ __("Valid quantity") }}</div>');
            } else {
                $input.removeClass('is-invalid is-valid');
            }
        });

        // Show validation error in UI
        function showValidationError(message) {
            // Remove existing error alerts
            $('.validation-error-alert').remove();
            
            // Create error alert
            var errorHtml = `
                <div class="alert alert-danger alert-dismissible fade show validation-error-alert" role="alert">
                    <i class="ti ti-circle-x me-2"></i>
                    <strong>{{ __("Error") }}</strong>
                    <p class="mb-0 mt-1">${message}</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Insert before form
            $('#material-return-form').before(errorHtml);
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $('.validation-error-alert').offset().top - 100
            }, 500);
        }

        // Form validation
        $('#material-return-form').submit(function(e) {
            var isValid = true;
            var errorMessages = [];
            
            $('#items-table tbody tr').each(function(index) {
                var materialId = $(this).find('.material-select').val();
                var quantity = parseFloat($(this).find('.quantity-input').val());
                
                if (!materialId) {
                    errorMessages.push('{{ __("Row") }} ' + (index + 1) + ': {{ __("Please select a material") }}');
                    isValid = false;
                    return false;
                }
                
                if (!quantity || quantity <= 0) {
                    errorMessages.push('{{ __("Row") }} ' + (index + 1) + ': {{ __("Quantity must be greater than zero") }}');
                    isValid = false;
                    return false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showValidationError(errorMessages.join('<br>'));
            }
        });
    });
</script>
@endpush
