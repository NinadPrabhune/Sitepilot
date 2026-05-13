@extends('layouts.main')

@section('page-title')
    {{ __('Machinery Details') }}
@endsection

@section('page-breadcrumb')
    {{ __('Machinery Details') }}
@endsection

@section('page-action')
    <a href="{{ route('machineries.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        <!-- Machinery Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Machinery Information') }}</h5>
                <span class="badge bg-secondary">{{ __('#') . $machinery->id }}</span>
            </div>
            <div class="card-body">

                <!-- First Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Machine ID') }}:</strong><br>
                        {{ $machinery->machine_id ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Name') }}:</strong><br>
                        {{ $machinery->name ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Model Number') }}:</strong><br>
                        {{ $machinery->model_number ?: __('Not Available') }}
                    </div>
                </div>

                <!-- Second Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Category') }}:</strong><br>
                        {{ optional($machinery->category)->name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Manufacturer') }}:</strong><br>
                        {{ $machinery->manufacturer ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Purchase Date') }}:</strong><br>
                        {{ $machinery->purchase_date ?: __('Not Available') }}
                    </div>
                </div>

                <!-- Third Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Capacity') }}:</strong><br>
                        {{ $machinery->capacity ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Operational Status') }}:</strong><br>
                        <span class="badge bg-{{ $machinery->operational_status === 'active' ? 'success' : 'secondary' }}">
                            {{ $machinery->operational_status ?: __('Not Available') }}
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Ownership') }}:</strong><br>
                        @switch($machinery->owned_by)
                            @case('owned')
                                {{ __('Owned') }}
                                @break
                            @case('rental')
                                {{ __('Rental') }}
                                @break
                            @default
                                {{ __('Not Available') }}
                        @endswitch
                    </div>
                </div>

                <!-- Fourth Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Machine Number') }}:</strong><br>
                        {{ $machinery->vehicle_number ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Supplier') }}:</strong><br>
                        {{ optional($machinery->supplier)->name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Site') }}:</strong><br>
                        {{ optional($machinery->site)->name ?? __('Not Available') }}
                    </div>
                </div>

                <!-- Description -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>{{ __('Description') }}:</strong><br>
                        {{ $machinery->description ?: __('Not Available') }}
                    </div>
                </div>

            </div>
        </div>

        <!-- Additional Details Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Additional Information') }}</h5>
            </div>
            <div class="card-body">

                <!-- First Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Maintenance Schedule') }}:</strong><br>
                        {{ $machinery->maintenance_schedule ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Supplier') }}:</strong><br>
                        {{ optional($machinery->supplier)->name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Site') }}:</strong><br>
                        {{ optional($machinery->site)->name ?? __('Not Available') }}
                    </div>
                </div>

                <!-- Second Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Workspace') }}:</strong><br>
                        {{ optional($machinery->workspace)->name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Created By') }}:</strong><br>
                        {{ $machinery->created_by ?: __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Last Updated') }}:</strong><br>
                        {{ $machinery->updated_at ? \Carbon\Carbon::parse($machinery->updated_at)->format('d M Y') : __('Not Available') }}
                    </div>
                </div>

            </div>
        </div>

        <!-- Machinery Balance Widget -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">💰 Financial Balance</h5>
            </div>
            <div class="card-body">
                @php
                    $totalCredit = \App\Domain\Machinery\Models\MachineryLedger::where('machinery_id', $machinery->id)
                        ->where('entry_direction', 'credit')
                        ->where('is_reversal', false)
                        ->sum('amount');
                    
                    $totalDebit = \App\Domain\Machinery\Models\MachineryLedger::where('machinery_id', $machinery->id)
                        ->where('entry_direction', 'debit')
                        ->where('is_reversal', false)
                        ->sum('amount');
                    
                    $currentBalance = $totalCredit - $totalDebit;
                    
                    $lastEntry = \App\Domain\Machinery\Models\MachineryLedger::where('machinery_id', $machinery->id)
                        ->where('is_reversal', false)
                        ->orderBy('date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    $runningBalance = $lastEntry ? $lastEntry->running_balance : 0;
                @endphp
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Current Balance</h6>
                                <h3 class="display-6 @if($currentBalance >= 0) text-success @else text-danger @endif">
                                    ₹{{ number_format($currentBalance, 2) }}
                                </h3>
                                <small class="text-muted">Running Balance: ₹{{ number_format($runningBalance, 2) }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Credit</h6>
                                <h3 class="display-6 text-success">
                                    ₹{{ number_format($totalCredit, 2) }}
                                </h3>
                                <small class="text-muted">Income/Work Credits</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Debit</h6>
                                <h3 class="display-6 text-danger">
                                    ₹{{ number_format($totalDebit, 2) }}
                                </h3>
                                <small class="text-muted">Expenses/Costs</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="{{ route('ledger.index', ['machinery_id' => $machinery->id]) }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-eye"></i> View Full Ledger
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
