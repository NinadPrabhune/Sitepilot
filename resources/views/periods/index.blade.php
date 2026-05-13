@extends('layouts.main')

@section('page-title')
    Payment Periods
@endsection

@section('page-breadcrumb')
    Payment Periods
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <strong>Payment Periods</strong>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Machinery</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Payment Request</th>
                                <th>Locked By</th>
                                <th>Locked At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($periods as $period)
                            <tr>
                                <td>{{ $period->machinery->name ?? 'N/A' }}</td>
                                <td>{{ $period->start_date->format('d-M-Y') }} to {{ $period->end_date->format('d-M-Y') }}</td>
                                <td>
                                    @if($period->is_locked)
                                        <span class="badge badge-danger">Locked</span>
                                    @else
                                        <span class="badge badge-success">Open</span>
                                    @endif
                                </td>
                                <td>
                                    @if($period->paymentRequest)
                                        <a href="{{ route('machinery-payment.show', $period->paymentRequest->id) }}" class="btn btn-sm btn-info">
                                            #{{ $period->paymentRequest->id }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ optional($period->lockedBy)->name ?? '-' }}</td>
                                <td>{{ $period->locked_at ? $period->locked_at->format('d-M-Y H:i') : '-' }}</td>
                                <td>
                                    @if($period->is_locked)
                                        <button class="btn btn-sm btn-warning" onclick="showUnlockModal({{ $period->id }})">
                                            <i class="ti ti-lock-open"></i> Unlock
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-danger" onclick="showLockModal({{ $period->id }})">
                                            <i class="ti ti-lock"></i> Lock
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No payment periods found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $periods->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Lock Modal -->
<div class="modal fade" id="lockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lock Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="lockForm">
                    <input type="hidden" id="lockPeriodId" name="period_id">
                    <div class="mb-3">
                        <label for="lockNotes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="lockNotes" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitLock()">Lock Period</button>
            </div>
        </div>
    </div>
</div>

<!-- Unlock Modal -->
<div class="modal fade" id="unlockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Unlock Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>⚠️ Warning</strong>
                    <br>Unlocking a period will allow modifications to ledger entries. This action should only be done for emergency corrections.
                </div>
                <form id="unlockForm">
                    <input type="hidden" id="unlockPeriodId" name="period_id">
                    <div class="mb-3">
                        <label for="unlockReason" class="form-label">Override Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="unlockReason" name="override_reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitUnlock()">Unlock Period</button>
            </div>
        </div>
    </div>
</div>

<script>
function showLockModal(id) {
    document.getElementById('lockPeriodId').value = id;
    new bootstrap.Modal(document.getElementById('lockModal')).show();
}

function showUnlockModal(id) {
    document.getElementById('unlockPeriodId').value = id;
    new bootstrap.Modal(document.getElementById('unlockModal')).show();
}

function submitLock() {
    const id = document.getElementById('lockPeriodId').value;
    const notes = document.getElementById('lockNotes').value;
    
    fetch(`/periods/${id}/lock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ notes: notes })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Period locked successfully');
            location.reload();
        } else {
            alert('Error: ' + JSON.stringify(data));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function submitUnlock() {
    const id = document.getElementById('unlockPeriodId').value;
    const reason = document.getElementById('unlockReason').value;
    
    if (!reason) {
        alert('Please provide an override reason.');
        return;
    }
    
    fetch(`/periods/${id}/unlock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ override_reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Period unlocked successfully');
            location.reload();
        } else {
            alert('Error: ' + JSON.stringify(data));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>
@endsection
