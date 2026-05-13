@extends('layouts.main')

@section('page-title', 'Advance Timeline')

@permission('supplier-advance show')

@section('content')
<div class="page-header">
    <h1>Timeline for Advance #{{ $advance->advance_number }}</h1>
    <a href="{{ route('supplier-advance.show', $advance->id) }}" class="btn btn-secondary">Back to Advance</a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Allocation History</h5>
    </div>
    <div class="card-body">
        @if($advance->utilizations->count() > 0)
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($advance->utilizations as $utilization)
                    <tr>
                        <td>{{ $utilization->invoice ? $utilization->invoice->invoice_number : '-' }}</td>
                        <td>₹{{ number_format($utilization->utilized_amount, 2) }}</td>
                        <td>{{ ucfirst($utilization->status) }}</td>
                        <td>{{ $utilization->created_at }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No allocations yet.</p>
        @endif
    </div>
</div>

@endsection
@endpermission
