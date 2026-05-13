@extends('layouts.main')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Machinery Ledger</h4>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" action="{{ route('ledger.index') }}" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="machinery_id" class="form-label">Machinery</label>
                    <select class="form-select" id="machinery_id" name="machinery_id">
                        <option value="">All Machinery</option>
                        @foreach($machineries as $machinery)
                            <option value="{{ $machinery->id }}" {{ request('machinery_id') == $machinery->id ? 'selected' : '' }}>
                                {{ $machinery->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ request('date_from') ?? date('Y-m-01') }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ request('date_to') ?? date('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <label for="entry_type" class="form-label">Entry Type</label>
                    <select class="form-select" id="entry_type" name="entry_type">
                        <option value="">All Types</option>
                        <option value="reading_credit" {{ request('entry_type') == 'reading_credit' ? 'selected' : '' }}>Reading Credit</option>
                        <option value="diesel_debit" {{ request('entry_type') == 'diesel_debit' ? 'selected' : '' }}>Diesel Debit</option>
                        <option value="maintenance_debit" {{ request('entry_type') == 'maintenance_debit' ? 'selected' : '' }}>Maintenance Debit</option>
                        <option value="payment_debit" {{ request('entry_type') == 'payment_debit' ? 'selected' : '' }}>Payment Debit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <a href="{{ route('ledger.index') }}" class="btn btn-secondary w-100">Clear</a>
                </div>
            </form>

            <!-- Ledger Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Machinery</th>
                            <th>Entry Type</th>
                            <th>Source</th>
                            <th>Reference ID</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Running Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgerEntries as $entry)
                        <tr>
                            <td>{{ $entry->date->format('d-M-Y') }}</td>
                            <td>{{ $entry->machinery->name ?? 'N/A' }}</td>
                            <td>
                                @php
                                    $typeLabels = [
                                        'reading_credit' => 'Reading Credit',
                                        'diesel_debit' => 'Diesel Debit',
                                        'maintenance_debit' => 'Maintenance Debit',
                                        'advance_debit' => 'Advance Debit',
                                        'payment_debit' => 'Payment Debit',
                                        'transfer_debit' => 'Transfer Debit',
                                    ];
                                @endphp
                                {{ $typeLabels[$entry->entry_type] ?? $entry->entry_type }}
                            </td>
                            <td>
                                @php
                                    $sourceLabels = [
                                        'DailyProgressReport' => 'DPR',
                                        'DailyConsumptionMaster' => 'Diesel',
                                        'MaintenanceLog' => 'Maintenance',
                                        'MachineryPayment' => 'Payment',
                                        'MachineryPaymentRequest' => 'Payment Request',
                                        'GeneralTransfer' => 'Transfer',
                                    ];
                                @endphp
                                {{ $sourceLabels[$entry->reference_type] ?? $entry->reference_type }}
                                @if($entry->is_reversal)
                                    <span class="badge bg-danger ms-1">Reversal</span>
                                @elseif($entry->reversed_entry_id)
                                    <span class="badge bg-warning ms-1">Reversed</span>
                                @endif
                            </td>
                            <td>
                                @if($entry->reference_id)
                                    @php
                                        $sourceRoute = match($entry->reference_type) {
                                            'DailyProgressReport' => route('daily-progress-reports.show', $entry->reference_id),
                                            'DailyConsumptionMaster' => route('daily-consumption.show', $entry->reference_id),
                                            'MaintenanceLog' => route('maintenance.show', $entry->reference_id),
                                            default => '#',
                                        };
                                    @endphp
                                    <a href="{{ $sourceRoute }}" class="btn btn-sm btn-outline-primary">
                                        #{{ $entry->reference_id }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-danger">
                                @if($entry->entry_direction == 'debit')
                                    ₹{{ number_format($entry->amount, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-success">
                                @if($entry->entry_direction == 'credit')
                                    ₹{{ number_format($entry->amount, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="fw-bold">₹{{ number_format($entry->running_balance, 2) }}</td>
                            <td>
                                @if($entry->reference_type == 'MachineryPayment' && $entry->reference_id)
                                    <a href="{{ route('machinery-payment.show', $entry->reference_id) }}" class="btn btn-sm btn-info">
                                        View PR
                                    </a>
                                @elseif($entry->reference_type == 'MachineryPaymentRequest' && $entry->reference_id)
                                    <a href="{{ route('machinery-payment.show', $entry->reference_id) }}" class="btn btn-sm btn-info">
                                        View Request
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center">No ledger entries found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $ledgerEntries->links() }}
        </div>
    </div>
</div>
@endsection
