@extends('layouts.main')

@section('page-title')
    Monthly Cost Report
@endsection

@section('page-breadcrumb')
    Reports / Monthly Cost Report
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
                <form method="GET" action="{{ route('reports.monthly-cost') }}">
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
                            <label for="year">Year</label>
                            <select class="form-select" id="year" name="year">
                                @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="month">Month</label>
                            <select class="form-select" id="month" name="month">
                                <option value="">All Months</option>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                @endfor
                            </select>
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
                        <h6 class="text-muted">Total Debit (Cost)</h6>
                        <h3 class="display-6 text-danger">₹{{ number_format($totalDebit, 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Net Balance</h6>
                        <h3 class="display-6 @if(($totalCredit - $totalDebit) >= 0) text-success @else text-danger @endif">₹{{ number_format($totalCredit - $totalDebit, 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cost by Type -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Cost by Type</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Total Amount</th>
                                <th>Count</th>
                                <th>Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($costByType as $type)
                            <tr>
                                <td>{{ ucfirst($type['type']) }}</td>
                                <td class="text-danger">₹{{ number_format($type['total'], 2) }}</td>
                                <td>{{ $type['count'] }}</td>
                                <td>₹{{ number_format($type['total'] / $type['count'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
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
