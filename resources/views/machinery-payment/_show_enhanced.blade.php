@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Status Badge and Actions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar"></i> 
                        Payment Request #{{ $paymentRequest->id }}
                    </h3>
                    <div class="d-flex align-items-center">
                        <!-- Status Badge -->
                        <span class="badge {{ getStatusBadgeClass($paymentRequest->status) }} badge-lg mr-3">
                            {{ strtoupper($paymentRequest->status) }}
                        </span>
                        
                        <!-- Action Buttons -->
                        @if($paymentRequest->status === 'draft')
                            <button type="button" class="btn btn-primary" onclick="submitPaymentRequest()">
                                <i class="fas fa-paper-plane"></i> Submit
                            </button>
                        @endif
                        
                        @if($paymentRequest->status === 'submitted')
                            <button type="button" class="btn btn-success" onclick="approvePaymentRequest()">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger" onclick="showRejectionModal()">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        @endif
                        
                        @if($paymentRequest->status === 'approved')
                            <button type="button" class="btn btn-info" onclick="markAsPaid()">
                                <i class="fas fa-money-check-alt"></i> Mark as Paid
                            </button>
                        @endif
                        
                        <a href="{{ route('machinery-payment.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Locked Snapshot Indicator -->
                    @if(in_array($paymentRequest->status, ['approved', 'paid']))
                        <div class="alert alert-warning mb-4">
                            <h5><i class="fas fa-lock"></i> 🔒 Financial Snapshot Locked</h5>
                            <p class="mb-0">This payment request has been approved and the financial snapshot is now immutable. No further recalculation is allowed.</p>
                            <small class="text-muted">Locked on: {{ $paymentRequest->approved_at ? $paymentRequest->approved_at->format('d M Y H:i') : '-' }}</small>
                        </div>
                    @endif
                    
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Machinery Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Machine:</strong></td>
                                    <td>{{ $paymentRequest->machinery->name }} ({{ $paymentRequest->machinery->machine_id }})</td>
                                </tr>
                                <tr>
                                    <td><strong>Supplier:</strong></td>
                                    <td>{{ $paymentRequest->supplier->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Rate Type:</strong></td>
                                    <td>{{ ucfirst($paymentRequest->machinery->rate_type) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Rate:</strong></td>
                                    <td>₹{{ number_format($paymentRequest->machinery->rate, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Period Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Period:</strong></td>
                                    <td>{{ $paymentRequest->period_start }} to {{ $paymentRequest->period_end }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $paymentRequest->created_at->format('d M Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Submitted:</strong></td>
                                    <td>{{ $paymentRequest->submitted_at ? $paymentRequest->submitted_at->format('d M Y H:i') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Approved:</strong></td>
                                    <td>{{ $paymentRequest->approved_at ? $paymentRequest->approved_at->format('d M Y H:i') : '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Billing Breakdown Panel -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calculator"></i> Billing Breakdown
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Main Breakdown -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Description</th>
                                                    <th class="text-right">Amount (₹)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong>Machine Work Charges</strong></td>
                                                    <td class="text-right font-weight-bold text-success">
                                                        +{{ number_format($paymentRequest->gross_amount ?? $paymentRequest->net_payable, 2) }}
                                                    </td>
                                                </tr>
                                                @if($paymentRequest->diesel_deduction > 0)
                                                <tr>
                                                    <td><strong>Diesel Recovery</strong></td>
                                                    <td class="text-right font-weight-bold text-danger">
                                                        -{{ number_format($paymentRequest->diesel_deduction, 2) }}
                                                    </td>
                                                </tr>
                                                @endif
                                                @if(isset($paymentRequest->adjustments) && $paymentRequest->adjustments != 0)
                                                <tr>
                                                    <td><strong>Adjustments</strong></td>
                                                    <td class="text-right font-weight-bold text-info">
                                                        {{ $paymentRequest->adjustments > 0 ? '+' : '' }}{{ number_format($paymentRequest->adjustments, 2) }}
                                                    </td>
                                                </tr>
                                                @endif
                                                <tr class="table-active">
                                                    <td><strong>Net Payable</strong></td>
                                                    <td class="text-right font-weight-bold">
                                                        ₹{{ number_format($paymentRequest->net_payable, 2) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <!-- Calculation Summary -->
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Calculation Summary</h6>
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td><strong>Method:</strong></td>
                                                <td>{{ $paymentRequest->calculation_method ?? 'Legacy' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Credits:</strong></td>
                                                <td>₹{{ number_format($paymentRequest->credits, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Debits:</strong></td>
                                                <td>₹{{ number_format($paymentRequest->debits, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Net:</strong></td>
                                                <td>₹{{ number_format($paymentRequest->net_payable, 2) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Expandable Daily Breakdown -->
                            @if($paymentRequest->billing_breakdown)
                            <div class="mt-4">
                                <button class="btn btn-sm btn-outline-primary" onclick="toggleDailyBreakdown()">
                                    <i class="fas fa-chevron-down"></i> Show Daily Breakdown
                                </button>
                                
                                <div id="dailyBreakdown" style="display: none;" class="mt-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Hours</th>
                                                    <th>Rate</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if($paymentRequest->billing_breakdown['hourly_breakdown'] ?? false)
                                                    @foreach($paymentRequest->billing_breakdown['hourly_breakdown'] as $day)
                                                    <tr>
                                                        <td>{{ $day['date'] }}</td>
                                                        <td>{{ $day['billable_hours'] }}</td>
                                                        <td>₹{{ number_format($day['rate_applied'], 2) }}</td>
                                                        <td>₹{{ number_format($day['amount'], 2) }}</td>
                                                    </tr>
                                                    @endforeach
                                                @elseif($paymentRequest->billing_breakdown['daily_breakdown'] ?? false)
                                                    @foreach($paymentRequest->billing_breakdown['daily_breakdown'] as $day)
                                                    <tr>
                                                        <td>{{ $day['date'] }}</td>
                                                        <td>{{ $day['billable_hours'] }}</td>
                                                        <td>Daily Rate</td>
                                                        <td>₹{{ number_format($day['charged'], 2) }}</td>
                                                    </tr>
                                                    @endforeach
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Diesel Breakdown -->
                    @if($paymentRequest->diesel_breakdown && $paymentRequest->diesel_deduction > 0)
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-gas-pump"></i> Diesel Recovery Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Total Liters:</strong></td>
                                            <td>{{ $paymentRequest->diesel_breakdown['total_liters'] ?? 0 }} L</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Average Rate:</strong></td>
                                            <td>₹{{ number_format($paymentRequest->diesel_breakdown['average_rate'] ?? 90, 2) }}/L</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total Cost:</strong></td>
                                            <td>₹{{ number_format($paymentRequest->diesel_breakdown['total_cost'] ?? 0, 2) }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Diesel Recovery Notice</strong><br>
                                        This amount of ₹{{ number_format($paymentRequest->diesel_deduction, 2) }} will be deducted from the supplier's payment.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Ledger Entries -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-book"></i> Ledger Entries
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Direction</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($ledgerEntries as $entry)
                                        <tr>
                                            <td>{{ $entry->date }}</td>
                                            <td>{{ ucfirst($entry->entry_type) }}</td>
                                            <td>{{ $entry->description }}</td>
                                            <td>
                                                <span class="badge {{ $entry->entry_direction === 'credit' ? 'badge-success' : 'badge-danger' }}">
                                                    {{ strtoupper($entry->entry_direction) }}
                                                </span>
                                            </td>
                                            <td class="font-weight-bold">
                                                {{ $entry->entry_direction === 'credit' ? '+' : '-' }}₹{{ number_format($entry->amount, 2) }}
                                            </td>
                                            <td>
                                                @if($entry->is_billed)
                                                    <span class="badge badge-info">Billed</span>
                                                @else
                                                    <span class="badge badge-secondary">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Workflow History -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history"></i> Workflow History
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <!-- Created -->
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6>Created</h6>
                                        <p class="mb-0">
                                            By: {{ $paymentRequest->requester->name ?? 'System' }}<br>
                                            {{ $paymentRequest->created_at->format('d M Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Submitted -->
                                @if($paymentRequest->submitted_at)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6>Submitted</h6>
                                        <p class="mb-0">
                                            By: {{ $paymentRequest->submitter->name ?? 'System' }}<br>
                                            {{ $paymentRequest->submitted_at->format('d M Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                @endif
                                
                                <!-- Approved -->
                                @if($paymentRequest->approved_at)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6>Approved</h6>
                                        <p class="mb-0">
                                            By: {{ $paymentRequest->approver->name ?? 'System' }}<br>
                                            {{ $paymentRequest->approved_at->format('d M Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                @endif
                                
                                <!-- Paid -->
                                @if($paymentRequest->paid_at)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-warning"></div>
                                    <div class="timeline-content">
                                        <h6>Paid</h6>
                                        <p class="mb-0">
                                            By: {{ $paymentRequest->payer->name ?? 'System' }}<br>
                                            {{ $paymentRequest->paid_at->format('d M Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle text-danger"></i> Reject Payment Request
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rejection_reason">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejection_reason" rows="4" 
                              placeholder="Please provide a reason for rejection..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="rejectPaymentRequest()">
                    <i class="fas fa-times"></i> Reject Payment Request
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}
</style>

<script>
// Get status badge class
function getStatusBadgeClass(status) {
    switch(status) {
        case 'draft': return 'badge-secondary';
        case 'submitted': return 'badge-info';
        case 'approved': return 'badge-success';
        case 'rejected': return 'badge-danger';
        case 'paid': return 'badge-warning';
        default: return 'badge-secondary';
    }
}

// Toggle daily breakdown
function toggleDailyBreakdown() {
    const breakdown = document.getElementById('dailyBreakdown');
    const button = event.target;
    
    if (breakdown.style.display === 'none') {
        breakdown.style.display = 'block';
        button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Daily Breakdown';
    } else {
        breakdown.style.display = 'none';
        button.innerHTML = '<i class="fas fa-chevron-down"></i> Show Daily Breakdown';
    }
}

// Submit payment request
function submitPaymentRequest() {
    if (confirm('Are you sure you want to submit this payment request?')) {
        fetch(`/machinery-payment/{{ $paymentRequest->id }}/submit`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting the payment request.');
        });
    }
}

// Approve payment request
function approvePaymentRequest() {
    if (confirm('Are you sure you want to approve this payment request? This will lock the financial snapshot.')) {
        fetch(`/machinery-payment/{{ $paymentRequest->id }}/approve`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while approving the payment request.');
        });
    }
}

// Show rejection modal
function showRejectionModal() {
    $('#rejectionModal').modal('show');
}

// Reject payment request
function rejectPaymentRequest() {
    const reason = document.getElementById('rejection_reason').value.trim();
    
    if (!reason) {
        alert('Please provide a rejection reason.');
        return;
    }
    
    fetch(`/machinery-payment/{{ $paymentRequest->id }}/reject`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while rejecting the payment request.');
    });
}

// Mark as paid
function markAsPaid() {
    if (confirm('Are you sure you want to mark this payment request as paid?')) {
        fetch(`/machinery-payment/{{ $paymentRequest->id }}/paid`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while marking the payment request as paid.');
        });
    }
}
</script>
@endsection
