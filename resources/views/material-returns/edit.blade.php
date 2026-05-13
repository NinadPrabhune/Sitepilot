@extends('layouts.main')

@section('title')
    {{ __('Edit Material Return') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Edit Material Return') }}</h5>
                </div>
                <div class="card-body">
                    {{ Form::open(['route' => ['material-returns.update', $materialReturn->id], 'method' => 'PUT', 'id' => 'material-return-form']) }}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                {{ Form::label('issue_id', __('Material Issue'), ['class' => 'form-label']) }}
                                <select name="issue_id" id="issue_id" class="form-control" required>
                                    <option value="">{{ __('Select Issue') }}</option>
                                    @foreach($issues as $issue)
                                        <option value="{{ $issue->id }}" {{ $materialReturn->issue_id == $issue->id ? 'selected' : '' }}>{{ $issue->issue_number }} - {{ $issue->issue_to_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {{ Form::label('return_date', __('Return Date'), ['class' => 'form-label']) }}
                                {{ Form::date('return_date', $materialReturn->return_date, ['class' => 'form-control', 'required' => 'required']) }}
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                {{ Form::label('remarks', __('Remarks'), ['class' => 'form-label']) }}
                                {{ Form::textarea('remarks', $materialReturn->remarks, ['class' => 'form-control', 'rows' => 2]) }}
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>{{ __('Items') }}</h5>
                            <table class="table table-bordered" id="items-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Issue Item') }}</th>
                                        <th>{{ __('Material') }}</th>
                                        <th>{{ __('Issued Qty') }}</th>
                                        <th>{{ __('Already Returned') }}</th>
                                        <th>{{ __('Remaining') }}</th>
                                        <th>{{ __('Return Qty') }}</th>
                                        <th>{{ __('Remarks') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($materialReturn->items as $index => $item)
                                        <tr class="item-row">
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][issue_item_id]" value="{{ $item->issue_item_id }}">
                                                <span class="issue-item-info">{{ $item->issueItem->material->name ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                <span class="material-name">{{ $item->material->name ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                <span class="issued-qty">{{ $item->issueItem->quantity ?? 0 }}</span>
                                            </td>
                                            <td>
                                                <span class="already-returned">{{ $item->issueItem->already_returned_qty ?? 0 }}</span>
                                            </td>
                                            <td>
                                                <span class="remaining-qty">{{ $item->issueItem->remaining_qty ?? 0 }}</span>
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $index }}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" value="{{ $item->quantity }}" required>
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
                            <a href="{{ route('material-returns.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
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
        let itemIndex = {{ count($materialReturn->items) }};
        let issueItems = [];

        // Load issue items when issue is selected
        $('#issue_id').change(function() {
            let issueId = $(this).val();
            if (issueId) {
                $.ajax({
                    url: "{{ route('material-returns.get-issue-details') }}",
                    type: 'POST',
                    data: {
                        issue_id: issueId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            issueItems = response.issue.items;
                            updateItemsTable();
                        }
                    }
                });
            } else {
                issueItems = [];
                updateItemsTable();
            }
        });

        function updateItemsTable() {
            let tbody = $('#items-table tbody');
            tbody.empty();
            itemIndex = 0;

            issueItems.forEach(function(issueItem) {
                if (issueItem.remaining_qty > 0) {
                    let row = `
                        <tr class="item-row">
                            <td>
                                <input type="hidden" name="items[${itemIndex}][issue_item_id]" value="${issueItem.id}">
                                <span class="issue-item-info">${issueItem.material.name}</span>
                            </td>
                            <td>
                                <span class="material-name">${issueItem.material.name}</span>
                            </td>
                            <td>
                                <span class="issued-qty">${issueItem.quantity}</span>
                            </td>
                            <td>
                                <span class="already-returned">${issueItem.already_returned_qty}</span>
                            </td>
                            <td>
                                <span class="remaining-qty">${issueItem.remaining_qty}</span>
                            </td>
                            <td>
                                <input type="number" name="items[${itemIndex}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" max="${issueItem.remaining_qty}" required>
                            </td>
                            <td>
                                <input type="text" name="items[${itemIndex}][remarks]" class="form-control">
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-item"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                    itemIndex++;
                }
            });
        }

        // Add item row manually
        $('#add-item').click(function() {
            if (issueItems.length === 0) {
                alert('{{ __("Please select an issue first") }}');
                return;
            }

            let availableItems = issueItems.filter(item => item.remaining_qty > 0);
            if (availableItems.length === 0) {
                alert('{{ __("No items available for return") }}');
                return;
            }

            let options = '<option value="">{{ __("Select Item") }}</option>';
            availableItems.forEach(function(item) {
                options += `<option value="${item.id}" data-material="${item.material.name}" data-issued="${item.quantity}" data-returned="${item.already_returned_qty}" data-remaining="${item.remaining_qty}">${item.material.name} (Remaining: ${item.remaining_qty})</option>`;
            });

            let row = `
                <tr class="item-row">
                    <td>
                        <select name="items[${itemIndex}][issue_item_id]" class="form-control issue-item-select" required>
                            ${options}
                        </select>
                    </td>
                    <td>
                        <span class="material-name">-</span>
                    </td>
                    <td>
                        <span class="issued-qty">-</span>
                    </td>
                    <td>
                        <span class="already-returned">-</span>
                    </td>
                    <td>
                        <span class="remaining-qty">-</span>
                    </td>
                    <td>
                        <input type="number" name="items[${itemIndex}][quantity]" class="form-control quantity-input" step="0.01" min="0.01" required>
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

        // Update row when issue item is selected
        $(document).on('change', '.issue-item-select', function() {
            let row = $(this).closest('tr');
            let selectedOption = $(this).find('option:selected');
            
            row.find('.material-name').text(selectedOption.data('material'));
            row.find('.issued-qty').text(selectedOption.data('issued'));
            row.find('.already-returned').text(selectedOption.data('returned'));
            row.find('.remaining-qty').text(selectedOption.data('remaining'));
            row.find('.quantity-input').attr('max', selectedOption.data('remaining'));
        });

        // Remove item row
        $(document).on('click', '.remove-item', function() {
            $(this).closest('tr').remove();
        });

        // Form validation
        $('#material-return-form').submit(function(e) {
            let hasItems = $('.item-row').length > 0;
            if (!hasItems) {
                e.preventDefault();
                alert('{{ __("Please add at least one item") }}');
                return false;
            }

            // Validate quantities don't exceed remaining
            let valid = true;
            $('.item-row').each(function() {
                let row = $(this);
                let quantity = parseFloat(row.find('.quantity-input').val()) || 0;
                let remaining = parseFloat(row.find('.remaining-qty').text()) || 0;
                
                if (quantity > remaining) {
                    valid = false;
                    alert('{{ __("Return quantity cannot exceed remaining quantity") }}');
                    return false;
                }
            });

            if (!valid) {
                e.preventDefault();
                return false;
            }
        });
    });
</script>
@endsection
