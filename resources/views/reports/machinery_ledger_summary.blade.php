@extends('layouts.main')

@section('page-title')
    Machinery Ledger Summary
@endsection

@section('page-breadcrumb')
    Reports / Machinery Ledger Summary
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
                <form method="GET" action="{{ route('reports.machinery-ledger-summary') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="machinery_id">Machinery</label>
                            <select class="form-select" id="machinery_id" name="machinery_id">
                                <option value="">All Machinery</option>
                                @foreach($machineries as $machinery)
                                    <option value="{{ $machinery->id }}" {{ $machineryId == $machinery->id ? 'selected' : '' }}>{{ $machinery->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Credit</h6>
                        <h3 class="display-6 text-success">₹{{ number_format($totalCredit, 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Debit</h6>
                        <h3 class="display-6 text-danger">₹{{ number_format($totalDebit, 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Final Balance</h6>
                        <h3 class="display-6 @if($finalBalance >= 0) text-success @else text-danger @endif">₹{{ number_format($finalBalance, 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Entries -->
        <div class="card">
            <div class="card-header">
                <strong>Ledger Entries</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Machinery</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Credit</th>
                                <th>Debit</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $entry)
                            <tr>
                                <td>{{ $entry->date->format('d-M-Y') }}</td>
                                <td>{{ optional($entry->machinery)->name ?? '-' }}</td>
                                <td>{{ $entry->entry_type }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-success">
                                    @if($entry->entry_direction == 'credit')
                                        ₹{{ number_format($entry->amount, 2) }}
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
                                <td>₹{{ number_format($entry->running_balance, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
