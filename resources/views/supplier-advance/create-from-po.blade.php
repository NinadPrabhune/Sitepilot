@extends('layouts.main')

@section('page-title', 'Request Advance')

@permission('supplier-advance create')

@section('content')
<div class="page-header">
    <h1>Request Advance for PO #{{ $po->po_number }}</h1>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Advance Request</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>Supplier:</strong> {{ $po->supplier->name }}</p>
                <p><strong>PO Amount:</strong> ₹{{ number_format($po->grand_total, 2) }}</p>
            </div>
        </div>

        <form action="{{ route('supplier-advance.store') }}" method="POST">
            @csrf
            <input type="hidden" name="po_id" value="{{ $po->id }}">
            <input type="hidden" name="source" value="po">

            <div class="form-group">
                <label>Advance Amount</label>
                <input type="number" name="amount" class="form-control" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Advance Date</label>
                <input type="date" name="advance_date" class="form-control" required value="{{ now()->toDateString() }}">
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a href="{{ route('purchase-order.show', $po->id) }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

@endsection
@endpermission
