@extends('layouts.main')

@section('page-title', __('Consumption #') . ($daily_consumption->consumption_number ?? ''))
@section('page-breadcrumb', __('Consumption Log,Details'))

@section('page-action')
<div class="d-flex gap-2">
    <a href="{{ route('daily-consumption.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back to List') }}
    </a>
    @if($daily_consumption->machinery_id)
    <a href="{{ route('ledger.index', ['machinery_id' => $daily_consumption->machinery_id]) }}" class="btn btn-sm btn-secondary">
        <i class="ti ti-book"></i> {{ __('View Ledger') }}
    </a>
    @endif
</div>
@endsection

@push('css')
@include('layouts.includes.datatable-css')
<!-- <style>
    .info-card {
        border-left: 4px solid #0d6efd;
    }
    .info-card.warning {
        border-left-color: #ffc107;
    }
    .info-card.success {
        border-left-color: #198754;
    }
    .info-card.danger {
        border-left-color: #dc3545;
    }
</style> -->
@endpush

@section('content')
<div class="row">
    {{-- Header Info Card --}}
    <div class="col-sm-12 col-lg-8">
        <div class="card info-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="ti ti-info-circle me-2"></i>{{ __('General Information') }}</h5>
                <span class="badge bg-primary">{{ ucfirst($daily_consumption->consumption_type) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Consumption Number') }}</label>
                        <div class="fw-bold">{{ $daily_consumption->consumption_number }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Date') }}</label>
                        <div class="fw-bold">{{ \Carbon\Carbon::parse($daily_consumption->consumption_date)->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Site') }}</label>
                        <div class="fw-bold">{{ optional($daily_consumption->site)->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Workspace') }}</label>
                        <div class="fw-bold">{{ optional($daily_consumption->workspace)->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Machinery Type') }}</label>
                        <div class="fw-bold">{{ $daily_consumption->machinery_type ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Machinery') }}</label>
                        <div class="fw-bold">{{ optional($daily_consumption->machinery)->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">{{ __('Created By') }}</label>
                        <div class="fw-bold">{{ optional($daily_consumption->creator)->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        @if($daily_consumption->consumption_file)
                        <label class="form-label text-muted mb-1">{{ __('Attached File') }}</label>
                        <div>
                            <a href="{{ Storage::url($daily_consumption->consumption_file) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="ti ti-file-download me-1"></i> {{ __('View File') }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Consumption Items Table --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-list-details me-2"></i>{{ __('Consumption Items') }}</h5>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th class="text-end">{{ __('Quantity') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daily_consumption->details as $detail)
                                <tr>
                                    <td>{{ optional($detail->material)->name ?? 'N/A' }}</td>
                                    <td class="text-end fw-bold">{{ $detail->quantity }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $detail->unit }}</span></td>
                                    <td>{{ $detail->remarks ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="ti ti-inbox fs-3 mb-2 d-block"></i>
                                        {{ __('No items recorded.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Sidebar: Ledger Traceability --}}
    <div class="col-sm-12 col-lg-4">
        <div class="card info-card {{ $daily_consumption->ledger_entry_id ? 'success' : 'warning' }} mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-receipt me-2"></i>{{ __('Ledger Traceability') }}</h5>
            </div>
            <div class="card-body">
                @if($daily_consumption->ledger_entry_id)
                    @php
                        $ledgerEntry = \App\Domain\Machinery\Models\MachineryLedger::find($daily_consumption->ledger_entry_id);
                    @endphp
                    @if($ledgerEntry)
                        @if($ledgerEntry->reversed_entry_id)
                            <div class="alert alert-danger">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong>{{ __('Reversed') }}</strong>
                                <p class="mb-0 small mt-1">{{ __('This ledger entry has been reversed. The financial impact has been neutralized.') }}</p>
                                @if($ledgerEntry->reversed_entry_id)
                                    <a href="{{ route('ledger.index') }}#entry-{{ $ledgerEntry->reversed_entry_id }}" class="alert-link small">{{ __('View Reversal Entry') }} #{{ $ledgerEntry->reversed_entry_id }}</a>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-success">
                                <i class="ti ti-check-circle me-2"></i>
                                <strong>{{ __('System Trust: Linked') }}</strong>
                                <p class="mb-0 small mt-1">{{ __('Ledger entry is active and posted.') }}</p>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">{{ __('Ledger Entry ID') }}</label>
                            <div><code>#LED-{{ $ledgerEntry->id }}</code></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">{{ __('Entry Type') }}</label>
                            <div class="fw-bold">{{ __('Debit') }} ({{ $daily_consumption->consumption_type }})</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">{{ __('Debit Amount') }}</label>
                            <div class="text-danger fw-bold fs-5">₹{{ number_format($ledgerEntry->amount, 2) }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">{{ __('Running Balance') }}</label>
                            <div class="fw-bold">₹{{ number_format($ledgerEntry->running_balance, 2) }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">{{ __('Status') }}</label>
                            <div>
                                @if($ledgerEntry->reversed_entry_id)
                                    <span class="badge bg-danger">{{ __('Reversed') }}</span>
                                @else
                                    <span class="badge bg-success">{{ __('Posted') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">{{ __('Posted At') }}</label>
                            <div class="small">{{ $ledgerEntry->created_at->format('d M Y, h:i A') }}</div>
                        </div>

                        <a href="{{ route('ledger.index', ['machinery_id' => $daily_consumption->machinery_id]) }}" class="btn btn-primary w-100">
                            <i class="ti ti-eye me-1"></i> {{ __('View in Ledger') }}
                        </a>
                    @else
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-2"></i>
                            <strong>{{ __('Ledger Entry Not Found') }}</strong>
                            <p class="mb-0 small mt-1">{{ __('Linked ledger entry ID') }} {{ $daily_consumption->ledger_entry_id }} {{ __('does not exist.') }}</p>
                        </div>
                    @endif
                @else
                    <div class="alert alert-warning">
                        <i class="ti ti-unlink me-2"></i>
                        <strong>{{ __('No Ledger Entry Linked') }}</strong>
                        <p class="mb-0 small mt-1">{{ __('This consumption has no associated ledger entry. The financial impact is not visible in the ledger.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('layouts.includes.datatable-js')
@endpush
