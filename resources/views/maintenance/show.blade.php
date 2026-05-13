@extends('layouts.main')

@section('page-title')
    Maintenance Log Details
@endsection

@section('page-breadcrumb')
    Maintenance Log Details
@endsection

@section('page-action')
    <a href="{{ route('maintenance.index') }}" class="btn btn-sm btn-secondary">
        <i class="ti ti-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        {{-- General Information --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>General Information</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Date:</strong> {{ $maintenanceLog->maintenance_date->format('d M, Y') }}</div>
                    <div class="col-md-6"><strong>Machinery:</strong> {{ $maintenanceLog->machinery->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Vendor:</strong> {{ $maintenanceLog->vendor->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Cost:</strong> <span class="text-danger fw-bold">₹{{ number_format($maintenanceLog->cost, 2) }}</span></div>
                    <div class="col-md-6"><strong>Paid By:</strong> {{ ucfirst($maintenanceLog->paid_by) }}</div>
                    <div class="col-md-6"><strong>Site:</strong> {{ $maintenanceLog->site->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Created By:</strong> {{ optional($maintenanceLog->creator)->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Status:</strong>
                        @if($maintenanceLog->status == 0)
                            <span class="badge badge-secondary">Pending</span>
                        @else
                            <span class="badge badge-success">Completed</span>
                        @endif
                    </div>
                </div>
                @if($maintenanceLog->description)
                    <div class="row mt-3">
                        <div class="col-12">
                            <strong>Description:</strong>
                            <p>{{ $maintenanceLog->description }}</p>
                        </div>
                    </div>
                @endif
                @if($maintenanceLog->attachment)
                    <div class="row mt-3">
                        <div class="col-12">
                            <strong>Attachment:</strong>
                            <a href="{{ asset('storage/' . $maintenanceLog->attachment) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="ti ti-file"></i> View Attachment
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Ledger Traceability Panel --}}
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <i class="ti ti-receipt"></i> <strong>📋 Ledger Traceability</strong>
            </div>
            <div class="card-body">
                @if($maintenanceLog->ledger_entry_id)
                    @php
                        $ledgerEntry = \App\Domain\Machinery\Models\MachineryLedger::find($maintenanceLog->ledger_entry_id);
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
                                <strong>Entry Type:</strong> Debit (Maintenance)
                            </div>
                            <div class="col-md-6">
                                <strong>Debit Amount:</strong> <span class="text-danger fw-bold">₹{{ number_format($ledgerEntry->amount, 2) }}</span>
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
                            <a href="{{ route('ledger.index', ['machinery_id' => $maintenanceLog->machinery_id]) }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-eye"></i> View in Ledger
                            </a>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <strong>⚠️ Ledger entry not found</strong>
                            <br>Linked ledger entry ID {{ $maintenanceLog->ledger_entry_id }} does not exist in the ledger.
                        </div>
                    @endif
                @else
                    <div class="alert alert-warning">
                        <strong>⚠️ No ledger entry linked</strong>
                        <br>This Maintenance Log has no associated ledger entry. The financial impact is not visible in the ledger.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
