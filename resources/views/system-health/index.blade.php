@extends('layouts.main')

@section('page-title')
    System Health
@endsection

@section('page-breadcrumb')
    System Health
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        {{-- Health Summary --}}
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card @if($orphanLedgerEntries->count() > 0) border-danger @else border-success @endif">
                    <div class="card-body">
                        <h5 class="card-title">🔴 Critical Issues</h5>
                        <h2 class="display-4 @if($orphanLedgerEntries->count() > 0) text-danger @else text-success @endif">
                            {{ $orphanLedgerEntries->count() }}
                        </h2>
                        <p class="card-text">
                            @if($orphanLedgerEntries->count() > 0)
                                <span class="text-danger">⚠️ Orphan entries detected</span>
                            @else
                                <span class="text-success">✅ No orphans</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card @if($driftEntries->where('severity', 'critical')->count() > 0) border-danger @elseif($driftEntries->where('severity', 'warning')->count() > 0) border-warning @else border-success @endif">
                    <div class="card-body">
                        <h5 class="card-title">🟠 Warnings</h5>
                        <h2 class="display-4 @if($driftEntries->where('severity', 'critical')->count() > 0) text-danger @elseif($driftEntries->where('severity', 'warning')->count() > 0) text-warning @else text-success @endif">
                            {{ $driftEntries->where('severity', 'warning')->count() }}
                        </h2>
                        <p class="card-text">
                            @if($driftEntries->where('severity', 'warning')->count() > 0)
                                <span class="text-warning">⚠️ Minor drift detected</span>
                            @else
                                <span class="text-success">✅ No warnings</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card @if($orphanLedgerEntries->count() == 0 && $driftEntries->count() == 0) border-success @else border-warning @endif">
                    <div class="card-body">
                        <h5 class="card-title">🟢 System Status</h5>
                        <h2 class="display-4 @if($orphanLedgerEntries->count() == 0 && $driftEntries->count() == 0) text-success @else text-warning @endif">
                            @if($orphanLedgerEntries->count() == 0 && $driftEntries->count() == 0)
                                Healthy
                            @else
                                Issues
                            @endif
                        </h2>
                        <p class="card-text">
                            @if($orphanLedgerEntries->count() > 0)
                                <span class="text-danger">⛔ Operations blocked</span>
                            @else
                                <span class="text-success">✅ All systems operational</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Orphan Ledger Entries --}}
        <div class="card mb-4">
            <div class="card-header @if($orphanLedgerEntries->count() > 0) bg-danger text-white @else bg-success text-white @endif">
                <strong>Orphan Ledger Entries</strong>
            </div>
            <div class="card-body">
                @if($orphanLedgerEntries->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Reference ID</th>
                                    <th>Ledger ID</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orphanLedgerEntries as $orphan)
                                <tr>
                                    <td>{{ $orphan['type'] }}</td>
                                    <td>{{ $orphan['reference_id'] }}</td>
                                    <td><code>#{{ $orphan['ledger_id'] }}</code></td>
                                    <td>{{ $orphan['message'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-success mb-0">✅ No orphan ledger entries detected.</p>
                @endif
            </div>
        </div>

        {{-- Drift Entries --}}
        <div class="card mb-4">
            <div class="card-header @if($driftEntries->count() > 0) bg-warning text-dark @else bg-success text-white @endif">
                <strong>Drift Detection</strong>
            </div>
            <div class="card-body">
                @if($driftEntries->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Reference ID</th>
                                    <th>Ledger ID</th>
                                    <th>Message</th>
                                    <th>Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($driftEntries as $drift)
                                <tr>
                                    <td>{{ $drift['type'] }}</td>
                                    <td>{{ $drift['reference_id'] }}</td>
                                    <td><code>#{{ $drift['ledger_id'] }}</code></td>
                                    <td>{{ $drift['message'] }}</td>
                                    <td>
                                        @if($drift['severity'] === 'warning')
                                            <span class="badge badge-warning">Warning</span>
                                        @else
                                            <span class="badge badge-danger">Critical</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-success mb-0">✅ No drift detected.</p>
                @endif
            </div>
        </div>

        {{-- Hash Verification --}}
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <strong>Payment Request Hash Verification</strong>
            </div>
            <div class="card-body">
                <p class="mb-3">Verifies payment request integrity by recalculating ledger entry hashes.</p>
                <button class="btn btn-primary" onclick="verifyHashes()">Verify All Payment Requests</button>
                <div id="hashResults" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
function verifyHashes() {
    const resultsDiv = document.getElementById('hashResults');
    resultsDiv.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>';
    
    fetch('/system-health/verify-hashes', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.verified_count === data.total_count) {
            resultsDiv.innerHTML = '<div class="alert alert-success">✅ All ' + data.verified_count + ' payment requests verified successfully.</div>';
        } else {
            resultsDiv.innerHTML = '<div class="alert alert-danger">❌ ' + data.mismatch_count + ' payment requests have hash mismatches.</div>';
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
    });
}
</script>
@endsection
