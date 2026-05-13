@extends('layouts.main')

@section('page-title', 'Record Payment')

@permission('supplier-advance payment')

@section('content')
<div class="page-header">
    <h1>Record Payment for Advance #{{ $advance->advance_number }}</h1>
    <a href="{{ route('supplier-advance.show', $advance->id) }}" class="btn btn-secondary">Back to Advance</a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Payment Details</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>Supplier:</strong> {{ $advance->supplier->name }}</p>
                <p><strong>Amount:</strong> ₹{{ number_format($advance->amount, 2) }}</p>
            </div>
        </div>

        <form action="{{ route('supplier-advance.record-payment', $advance->id) }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="payment_date" class="form-control" required value="{{ now()->toDateString() }}">
            </div>

            <div class="form-group">
                <label>Payment Mode</label>
                <select name="payment_mode" class="form-control" required>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="upi">UPI</option>
                </select>
            </div>

            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" class="form-control">
            </div>

            <div class="form-group">
                <label>Payment Proof File</label>
                <input type="file" name="payment_proof_file" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">Record Payment</button>
            <a href="{{ route('supplier-advance.show', $advance->id) }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

@endsection
@endpermission
