@extends('layouts.main')

@section('title')
    {{ __('Edit Material Issue') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Edit Material Issue') }}</h5>
                </div>
                <div class="card-body">
                    {{ Form::open(['route' => ['material-issues.update', $materialIssue->id], 'method' => 'PUT', 'id' => 'material-issue-form']) }}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                {{ Form::label('issue_to_type', __('Issue To Type'), ['class' => 'form-label']) }}
                                {{ Form::select('issue_to_type', ['user' => __('User'), 'supplier' => __('Supplier')], $materialIssue->issue_to_type, ['class' => 'form-control', 'id' => 'issue_to_type', 'required' => 'required']) }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {{ Form::label('issue_to_id', __('Issue To'), ['class' => 'form-label']) }}
                                <select name="issue_to_id" id="issue_to_id" class="form-control" required>
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" data-type="user" {{ $materialIssue->issue_to_type == 'user' && $materialIssue->issue_to_id == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" data-type="supplier" {{ $materialIssue->issue_to_type == 'supplier' && $materialIssue->issue_to_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                {{ Form::label('issue_date', __('Issue Date'), ['class' => 'form-label']) }}
                                {{ Form::date('issue_date', $materialIssue->issue_date, ['class' => 'form-control', 'required' => 'required']) }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
                                {{ Form::textarea('remarks', $materialIssue->remarks, ['class' => 'form-control', 'rows' => 2]) }}
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>{{ __('Items') }}</h5>
                            <table class="table table-bordered" id="items-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Material') }}</th>
                                        <th>{{ __('Available Stock') }}</th>
                                        <th>{{ __('Quantity') }}</th>
                                        <th>{{ __('Rate') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Remarks') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($materialIssue->items as $index => $item)
                                        <tr class="item-row">
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                                <select name="items[{{ $index }}][material_id]" class="form-control material-select" required>
                                                    <option value="">{{ __('Select Material') }}</option>
                                                    @foreach($materials as $material)
                                                        <option value="{{ $material->id }}" data-rate="{{ $material->price }}" {{ $item->material_id == $material->id ? 'selected' : '' }}>{{ $material->name }} ({{ $material->unit->name ?? '' }})</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <span class="available-stock">-</span>
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $index }}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" value="{{ $item->quantity }}" required>
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $index }}][rate]" class="form-control rate-input" step="0.01" min="0" value="{{ $item->rate }}">
                                            </td>
                                            <td>
                                                <span class="amount">{{ $item->amount ?? 0 }}</span>
                                            </td>
                                            <td>
                                                <input type="text" name="items[{{ $index }}][remarks]" class="form-control" value="{{ $item->remarks }}">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm remove-item"><i class="ti ti-trash"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-primary btn-sm" id="add-item"><i class="ti ti-plus"></i> {{ __('Add Item') }}</button>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12 text-end">
                            <a href="{{ route('material-issues.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                            {{ Form::submit(__('Update'), ['class' => 'btn btn-primary']) }}
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        let itemIndex = {{ count($materialIssue->items) }};

        // Filter issue_to_id based on issue_to_type
        $('#issue_to_type').change(function() {
            let selectedType = $(this).val();
            $('#issue_to_id option').hide();
            $('#issue_to_id option[data-type="' + selectedType + '"]').show();
            $('#issue_to_id').val('');
        });

        // Initial filter
        $('#issue_to_type').trigger('change');

        // Add item row
        $('#add-item').click(function() {
            let row = `
                <tr class="item-row">
                    <td>
                        <select name="items[${itemIndex}][material_id]" class="form-control material-select" required>
                            <option value="">{{ __('Select Material') }}</option>
                            @foreach($materials as $material)
                                <option value="{{ $material->id }}" data-rate="{{ $material->price }}">{{ $material->name }} ({{ $material->unit->name ?? '' }})</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <span class="available-stock">-</span>
                    </td>
                    <td>
                        <input type="number" name="items[${itemIndex}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="number" name="items[${itemIndex}][rate]" class="form-control rate-input" step="0.01" min="0">
                    </td>
                    <td>
                        <span class="amount">0</span>
                    </td>
                    <td>
                        <input type="text" name="items[${itemIndex}][remarks]" class="form-control">
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-item"><i class="ti ti-trash"></i></button>
                    </td>
                </tr>
            `;
            $('#items-table tbody').append(row);
            itemIndex++;
        });

        // Remove item row
        $(document).on('click', '.remove-item', function() {
            $(this).closest('tr').remove();
        });

        // Update available stock and rate when material is selected
        $(document).on('change', '.material-select', function() {
            let row = $(this).closest('tr');
            let materialId = $(this).val();
            let rate = $(this).find('option:selected').data('rate');

            if (materialId) {
                $.ajax({
                    url: "{{ route('material-issues.get-available-stock') }}",
                    type: 'POST',
                    data: {
                        material_id: materialId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            row.find('.available-stock').text(response.available_stock);
                            if (rate) {
                                row.find('.rate-input').val(rate);
                            }
                            calculateAmount(row);
                        }
                    }
                });
            } else {
                row.find('.available-stock').text('-');
            }
        });

        // Calculate amount when quantity or rate changes
        $(document).on('input', '.quantity-input, .rate-input', function() {
            let row = $(this).closest('tr');
            calculateAmount(row);
        });

        function calculateAmount(row) {
            let quantity = parseFloat(row.find('.quantity-input').val()) || 0;
            let rate = parseFloat(row.find('.rate-input').val()) || 0;
            let amount = quantity * rate;
            row.find('.amount').text(amount.toFixed(2));
        }

        // Form validation
        $('#material-issue-form').submit(function(e) {
            let hasItems = $('.item-row').length > 0;
            if (!hasItems) {
                e.preventDefault();
                alert('{{ __("Please add at least one item") }}');
                return false;
            }
        });
    });
</script>
@endsection
