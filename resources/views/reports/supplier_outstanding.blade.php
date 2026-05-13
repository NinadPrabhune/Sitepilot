@extends('layouts.main')

@section('page-title')
    Supplier Outstanding
@endsection

@section('page-breadcrumb')
    Reports / Supplier Outstanding
@endsection

@section('page-action')
    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-secondary">
        <i class="ti ti-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Filters</strong>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('reports.supplier-outstanding') }}">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="supplier_id">Supplier</label>
                            <select class="form-select" id="supplier_id" name="supplier_id">
                                <option value="">All Suppliers</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Supplier Balances -->
        <div class="card">
            <div class="card-header">
                <strong>Supplier Balances</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Total Credit (Supplied)</th>
                                <th>Total Debit (Paid)</th>
                                <th>Outstanding Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($supplierBalances as $balance)
                            <tr>
                                <td>{{ optional($balance['supplier'])->name ?? 'Unknown' }}</td>
                                <td class="text-success">₹{{ number_format($balance['total_credit'], 2) }}</td>
                                <td class="text-danger">₹{{ number_format($balance['total_debit'], 2) }}</td>
                                <td class="@if($balance['balance'] > 0) text-success @elseif($balance['balance'] < 0) text-danger @else text-muted @endif">
                                    ₹{{ number_format($balance['balance'], 2) }}
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="showEntries({{ $balance['supplier']->id ?? 0 }})">
                                        View Entries
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Entries Modal -->
<div class="modal fade" id="entriesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ledger Entries</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="entriesContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showEntries(supplierId) {
    // This would typically load entries via AJAX
    // For now, just show a placeholder
    document.getElementById('entriesContent').innerHTML = '<p>Loading entries for supplier ID: ' + supplierId + '</p>';
    new bootstrap.Modal(document.getElementById('entriesModal')).show();
}
</script>
@endsection
