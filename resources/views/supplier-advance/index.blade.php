@extends('layouts.main')

@section('page-title', 'Supplier Advances')

@permission('supplier-advance show')

@section('content')
<div class="page-header">
    <h1>Supplier Advances</h1>
</div>

<!-- KPI Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Advances</h5>
                <h3 class="card-text">₹{{ number_format($totalAdvances, 2) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Available Balance</h5>
                <h3 class="card-text">₹{{ number_format($availableBalance, 2) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Utilized Amount</h5>
                <h3 class="card-text">₹{{ number_format($utilizedAmount, 2) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Pending Approval</h5>
                <h3 class="card-text">{{ $pendingApproval }}</h3>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title">Filters</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Supplier</label>
                    <select class="form-control" id="supplier_filter" name="supplier_id">
                        <option value="">All Suppliers</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" id="status_filter" name="status">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTable -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Advance List</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped" id="supplier-advance-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Advance No.</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>PO No.</th>
                    <th>Amount</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th>Site</th>
                    <th>Created By</th>
                    <th>Created At</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@endsection
@endpermission

@push('scripts')
<script>
$(document).ready(function() {
    // Load suppliers for filter
    $.get('{{ route("suppliers.index") }}', function(data) {
        $.each(data.data, function(key, value) {
            $('#supplier_filter').append('<option value="' + value.id + '">' + value.name + '</option>');
        });
    });

    // Initialize DataTable
    var table = $('#supplier-advance-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("supplier-advance.index") }}',
            type: 'GET',
            data: function(d) {
                d.supplier_id = $('#supplier_filter').val();
                d.status = $('#status_filter').val();
                d.start_date = $('#start_date').val();
                d.end_date = $('#end_date').val();
            }
        },
        columns: [
            { data: 'action', orderable: false },
            { data: 'advance_number' },
            { data: 'advance_date' },
            { data: 'supplier_name' },
            { data: 'po_number' },
            { data: 'amount' },
            { data: 'remaining_amount' },
            { data: 'status' },
            { data: 'site_name' },
            { data: 'created_by_name' },
            { data: 'created_at' }
        ],
        order: [[1, 'desc']]
    });

    // Delete advance handler
    $(document).on('click', '.delete-advance', function() {
        var advanceId = $(this).data('id');
        if (confirm('Are you sure you want to delete this advance?')) {
            $.ajax({
                url: '{{ route("supplier-advance.destroy", ":id") }}'.replace(':id', advanceId),
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    table.ajax.reload();
                    alert('Advance deleted successfully');
                },
                error: function(xhr) {
                    alert('Error deleting advance: ' + xhr.responseJSON.message);
                }
            });
        }
    });
});

function applyFilters() {
    $('#supplier-advance-table').DataTable().ajax.reload();
}

function clearFilters() {
    $('#supplier_filter').val('');
    $('#status_filter').val('');
    $('#start_date').val('');
    $('#end_date').val('');
    $('#supplier-advance-table').DataTable().ajax.reload();
}
</script>
@endpush
