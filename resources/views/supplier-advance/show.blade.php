@extends('layouts.main')

@section('page-title', 'Supplier Advance Details')

@permission('supplier-advance show')

@section('content')
<div class="page-header">
    <h1>Supplier Advance #{{ $advance->advance_number }}</h1>
    <a href="{{ route('supplier-advance.index') }}" class="btn btn-secondary">Back to List</a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Advance Details</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Advance Number:</strong> {{ $advance->advance_number }}</p>
                <p><strong>Date:</strong> {{ $advance->advance_date }}</p>
                <p><strong>Supplier:</strong> {{ $advance->supplier->name ?? '-' }}</p>
                <p><strong>PO Number:</strong> {{ $advance->po ? $advance->po->po_number : 'Manual' }}</p>
                <p><strong>Site:</strong> {{ $advance->site ? $advance->site->name : '-' }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Amount:</strong> ₹{{ number_format($advance->amount, 2) }}</p>
                <p><strong>Available Balance:</strong> ₹{{ number_format($advance->getAvailableBalanceAttribute(), 2) }}</p>
                <p><strong>Utilized Amount:</strong> ₹{{ number_format($advance->utilized_amount, 2) }}</p>
                <p><strong>Status:</strong> {{ ucfirst($advance->status) }}</p>
                <p><strong>Created By:</strong> {{ $advance->creator ? $advance->creator->name : '-' }}</p>
            </div>
        </div>
        @if($advance->remarks)
        <p><strong>Remarks:</strong> {{ $advance->remarks }}</p>
        @endif
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title">Actions</h5>
    </div>
    <div class="card-body">
        @if($advance->status === \App\Models\SupplierAdvance::STATUS_PENDING)
            <a href="{{ route('supplier-advance.approve', $advance->id) }}" class="btn btn-success">Approve</a>
            <form action="{{ route('supplier-advance.reject', $advance->id) }}" method="POST" style="display: inline;">
                @csrf
                <input type="text" name="rejection_reason" placeholder="Rejection reason" required>
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
        @endif

        @if($advance->status === \App\Models\SupplierAdvance::STATUS_APPROVED)
            <a href="{{ route('supplier-advance.payment-form', $advance->id) }}" class="btn btn-info">Record Payment</a>
        @endif

        @if($advance->status === \App\Models\SupplierAdvance::STATUS_PAID)
            <a href="{{ route('supplier-advance.timeline', $advance->id) }}" class="btn btn-secondary">View Timeline</a>
        @endif

        @if(in_array($advance->status, [\App\Models\SupplierAdvance::STATUS_PENDING, \App\Models\SupplierAdvance::STATUS_CANCELLED]))
            <form action="{{ route('supplier-advance.destroy', $advance->id) }}" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
        @endif
    </div>
</div>

@endsection
@endpermission
