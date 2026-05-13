@extends('layouts.main')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Pending Approvals</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Machinery</th>
                            <th>Supplier</th>
                            <th>Period</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th>Current Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingRequests as $request)
                        <tr>
                            <td>{{ $request->id }}</td>
                            <td>{{ $request->machinery->name ?? 'N/A' }}</td>
                            <td>{{ $request->supplier->name ?? 'N/A' }}</td>
                            <td>{{ $request->period_start->format('d-M-Y') }} to {{ $request->period_end->format('d-M-Y') }}</td>
                            <td>₹{{ number_format($request->net_payable, 2) }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-secondary',
                                        'site_approved' => 'bg-info',
                                        'pm_approved' => 'bg-primary',
                                        'admin_approved' => 'bg-warning',
                                        'accounts_approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Pending',
                                        'site_approved' => 'Site Approved',
                                        'pm_approved' => 'PM Approved',
                                        'admin_approved' => 'Admin Approved',
                                        'accounts_approved' => 'Accounts Approved',
                                        'rejected' => 'Rejected',
                                    ];
                                @endphp
                                <span class="badge {{ $statusColors[$request->status] ?? 'bg-secondary' }}">
                                    {{ $statusLabels[$request->status] ?? $request->status }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $levelLabels = [
                                        'pending' => 'Site Engineer',
                                        'site_approved' => 'Project Manager',
                                        'pm_approved' => 'Admin',
                                        'admin_approved' => 'Accounts',
                                        'accounts_approved' => 'Completed',
                                    ];
                                @endphp
                                {{ $levelLabels[$request->status] ?? 'Unknown' }}
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-success" onclick="approveRequest({{ $request->id }})">
                                        Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="rejectRequest({{ $request->id }})">
                                        Reject
                                    </button>
                                    <a href="{{ route('machinery-payment.show', $request->id) }}" class="btn btn-sm btn-info">
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No pending approvals found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $pendingRequests->links() }}
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Payment Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approveForm">
                    <input type="hidden" id="approveRequestId" name="request_id">
                    <div class="mb-3">
                        <label for="approveRemarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="approveRemarks" name="remarks" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitApprove()">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Payment Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectForm">
                    <input type="hidden" id="rejectRequestId" name="request_id">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectReason" name="reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitReject()">Reject</button>
            </div>
        </div>
    </div>
</div>

<script>
function approveRequest(id) {
    document.getElementById('approveRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectRequest(id) {
    document.getElementById('rejectRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function submitApprove() {
    const id = document.getElementById('approveRequestId').value;
    const remarks = document.getElementById('approveRemarks').value;
    
    fetch(`/approvals/${id}/approve`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ remarks: remarks })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function submitReject() {
    const id = document.getElementById('rejectRequestId').value;
    const reason = document.getElementById('rejectReason').value;
    
    if (!reason) {
        alert('Please provide a rejection reason.');
        return;
    }
    
    fetch(`/approvals/${id}/reject`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>
@endsection
