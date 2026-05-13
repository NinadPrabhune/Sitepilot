@extends('layouts.main')

@section('page-title')
    {{ __('Daily Progress Report Details') }}
@endsection

@section('page-breadcrumb')
    {{ __('Daily Progress Report Details') }}
@endsection

@section('page-action')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('daily-progress-reports.index') }}" class="btn btn-sm btn-secondary">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
        </a>
      
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        {{-- General Info --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <i class="ti ti-info-circle"></i> <strong>{{ __('General Information') }}</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>{{ __('Date') }}:</strong> {{ $report->date->format('d M, Y') }}</div>
                    <div class="col-md-6"><strong>{{ __('Created By') }}:</strong> {{ optional($report->creator)->name ?? '-' }}</div>
                    <div class="col-md-6"><strong>{{ __('Workspace') }}:</strong> {{ optional($report->workspace)->name ?? '-' }}</div>
                    <div class="col-md-6"><strong>{{ __('Site') }}:</strong> {{ optional($report->site)->name ?? '-' }}</div>
                </div>
            </div>
        </div>

        {{-- Machine Readings --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <i class="ti ti-gauge"></i> <strong>{{ __('Machine Readings') }}</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>{{ __('Start Reading') }}:</strong> {{ $report->machine_start_reading ?? '-' }}</div>
                    <div class="col-md-4"><strong>{{ __('End Reading') }}:</strong> {{ $report->machine_end_reading ?? '-' }}</div>
                    <div class="col-md-4"><strong>{{ __('Operators') }}:</strong> {{ $report->number_of_operators ?? '-' }}</div>
                </div>
            </div>
        </div>

        {{-- Work Details --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <i class="ti ti-briefcase"></i> <strong>{{ __('Work Details') }}</strong>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $report->work_details ?? __('No work details provided.') }}</p>
            </div>
        </div>

        {{-- Diesel & Maintenance --}}
        <div class="card mb-4 shadow-sm d-none">
            <div class="card-header bg-light">
                <i class="ti ti-tools"></i> <strong>{{ __('Diesel Consumption & Maintenance') }}</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>{{ __('Diesel Consumption') }}:</strong> {{ $report->diesel_consumption ?? '-' }}</div>
                    <div class="col-md-6"><strong>{{ __('Maintenance Notes') }}:</strong> {{ $report->maintenance_notes ?? __('No notes.') }}</div>
                </div>
            </div>
        </div>
        
        {{-- Diesel & Maintenance --}}
        <div class="card mb-4 shadow-sm ">
            <div class="card-header bg-light">
                <i class="ti ti-tools"></i> <strong>{{ __('Maintenance') }}</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    
                    <div class="col-md-6"><strong>{{ __('Maintenance Notes') }}:</strong> {{ $report->maintenance_notes ?? __('No notes.') }}</div>
                </div>
            </div>
        </div>

        {{-- Machinery Advances --}}
        <div class="card mb-4 shadow-sm d-none">
            <div class="card-header bg-light">
                <i class="ti ti-truck"></i> <strong>{{ __('Machinery Advances') }}</strong>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $report->machinery_advances ?? __('No advances recorded.') }}</p>
            </div>
        </div>

        {{-- Ledger Traceability Panel --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="ti ti-receipt"></i> <strong>📋 Ledger Traceability</strong>
            </div>
            <div class="card-body">
                @if($report->ledger_entry_id)
                    @php
                        $ledgerEntry = \App\Domain\Machinery\Models\MachineryLedger::find($report->ledger_entry_id);
                    @endphp
                    @if($ledgerEntry)
                        @if($ledgerEntry->reversed_entry_id)
                            <div class="alert alert-danger">
                                <strong>❌ Reversed</strong>
                                <br>This ledger entry has been reversed. The financial impact has been neutralized.
                                @if($ledgerEntry->reversed_entry_id)
                                    <br><a href="{{ route('ledger.index') }}#entry-{{ $ledgerEntry->reversed_entry_id }}" class="alert-link">View Reversal Entry #{{ $ledgerEntry->reversed_entry_id }}</a>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-success">
                                <strong>✅ System Trust: Ledger entry linked</strong>
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>Ledger Entry ID:</strong> <code>#LED-{{ $ledgerEntry->id }}</code>
                            </div>
                            <div class="col-md-6">
                                <strong>Entry Type:</strong> Credit (Work)
                            </div>
                            <div class="col-md-6">
                                <strong>Credit Amount:</strong> <span class="text-success fw-bold">₹{{ number_format($ledgerEntry->amount, 2) }}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong>
                                @if($ledgerEntry->reversed_entry_id)
                                    <span class="badge badge-danger">Reversed</span>
                                @else
                                    <span class="badge badge-success">Posted</span>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <strong>Running Balance:</strong> <span class="fw-bold">₹{{ number_format($ledgerEntry->running_balance, 2) }}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Posted At:</strong> {{ $ledgerEntry->created_at->format('d-M-Y H:i') }}
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('ledger.index', ['machinery_id' => $report->machinery_id]) }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-eye"></i> View in Ledger
                            </a>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <strong>⚠️ Ledger entry not found</strong>
                            <br>Linked ledger entry ID {{ $report->ledger_entry_id }} does not exist in the ledger.
                        </div>
                    @endif
                @else
                    <div class="alert alert-warning">
                        <strong>⚠️ No ledger entry linked</strong>
                        <br>This Daily Progress Report has no associated ledger entry. The financial impact is not visible in the ledger.
                    </div>
                @endif
            </div>
        </div>

    </div>
    
    
    
    {{-- Consumption Master --}}
@if($report->consumptionMaster)
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">
            <i class="ti ti-gas-station"></i> <strong>{{ __('Fuel Consumption Details') }}</strong>
        </div>
        <div class="card-body">
            {{-- Consumption File --}}
            @if($report->consumptionMaster->consumption_file)
                <p>
                    <strong>{{ __('Attached File') }}:</strong>
                    <a href="{{ asset('storage/'.$report->consumptionMaster->consumption_file) }}" target="_blank">
                        {{ __('Download') }}
                    </a>
                </p>
            @endif

            {{-- Consumption Details Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Material') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Unit') }}</th>
                            <th>{{ __('Remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report->consumptionMaster->details as $detail)
                            <tr>
                                <td>{{ $detail->material->name ?? '-' }}</td>
                                <td>{{ $detail->quantity }}</td>
                                <td>{{ $detail->unit }}</td>
                                <td>{{ $detail->remarks ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    {{ __('No consumption details recorded.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

    
    
</div>
@endsection
