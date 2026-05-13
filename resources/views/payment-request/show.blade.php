@extends('layouts.main')

@section('page-title')
{{ __('Payment Request Details') }}
@endsection

@section('page-breadcrumb')
{{ __('Payment Request') }}, {{ __('Details') }}
@endsection

@section('page-action')
<a href="{{ route('payment-request.index') }}" class="btn btn-sm btn-light border">
    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
</a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        <!-- Payment Request Summary -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Payment Request #') }}{{ $paymentRequest->id }}</h5>
                @if($paymentRequest->status === 'pending')
                    <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                @elseif($paymentRequest->status === 'approved')
                    <span class="badge bg-success">{{ __('Approved') }}</span>
                @elseif($paymentRequest->status === 'partially_approved')
                    <span class="badge bg-info text-dark">{{ __('Partial') }}</span>
                @elseif($paymentRequest->status === 'rejected')
                    <span class="badge bg-danger">{{ __('Rejected') }}</span>
                @else
                    <span class="badge bg-secondary">{{ ucfirst($paymentRequest->status) }}</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>{{ __('Request Type') }}:</strong><br>
                        @if($paymentRequest->isPoAdvance())
                            <span class="badge bg-primary">PO Advance</span>
                        @else
                            <span class="badge bg-info">Invoice Payment</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Invoice/PO') }}:</strong><br>
                        @if($paymentRequest->purchase_invoice_id)
                            <a href="{{ route('purchase-invoice.show', $paymentRequest->purchase_invoice_id) }}" target="_blank">
                                {{ $paymentRequest->invoice?->invoice_number ?? '-' }}
                            </a>
                        @else
                            <a href="{{ route('purchase-order.show', $paymentRequest->po_id) }}" target="_blank">
                                {{ $paymentRequest->po?->po_number ?? '-' }}
                            </a>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Supplier') }}:</strong><br>
                        @if($paymentRequest->isPoAdvance())
                            {{ $paymentRequest->po?->supplier?->name ?? '-' }}
                        @else
                            {{ $paymentRequest->invoice?->supplier?->name ?? '-' }}
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Site') }}:</strong><br>
                        @if($paymentRequest->isPoAdvance())
                            {{ $paymentRequest->po?->site?->name ?? '-' }}
                        @else
                            {{ $paymentRequest->invoice?->site?->name ?? '-' }}
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>{{ __('Payment Terms and Conditions (PO)') }}:</strong><br>
                        @if($paymentRequest->isPoAdvance())
                            {{ $paymentRequest->po?->payment_terms_conditions ?? '-' }}
                        @else
                            {{ $paymentRequest->invoice?->purchaseOrder?->payment_terms_conditions ?? '-' }}
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Payment Date') }}:</strong><br>
                        {{ $paymentRequest->payment_date ? \Carbon\Carbon::parse($paymentRequest->payment_date)->format('d M Y') : '-' }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Status') }}:</strong><br>
                        @if($paymentRequest->status === 'pending')
                            <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                        @elseif($paymentRequest->status === 'approved')
                            <span class="badge bg-success">{{ __('Approved') }}</span>
                        @elseif($paymentRequest->status === 'partially_approved')
                            <span class="badge bg-info text-dark">{{ __('Partial') }}</span>
                        @elseif($paymentRequest->status === 'rejected')
                            <span class="badge bg-danger">{{ __('Rejected') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($paymentRequest->status) }}</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>{{ __('Requested Amount') }}:</strong><br>
                        <span class="h6">₹{{ format_indian_currency($paymentRequest->requested_amount) }}</span>
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Approved Amount') }}:</strong><br>
                        @if($paymentRequest->isPending())
                            <span class="text-muted">-</span>
                        @else
                            <span class="h6 text-success">₹{{ format_indian_currency($paymentRequest->approved_amount ?? 0) }}</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Requested By') }}:</strong><br>
                        {{ $paymentRequest->requestedBy?->name ?? '-' }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Created At') }}:</strong><br>
                        {{ $paymentRequest->created_at->format('d M Y, h:i A') }}
                    </div>
                </div>

                @if($paymentRequest->remarks)
                <div class="row">
                    <div class="col-md-12">
                        <strong>{{ __('Remarks') }}:</strong><br>
                        {{ $paymentRequest->remarks }}
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Financial Snapshot (Only if approved) -->
        @if($paymentRequest->hasFinancialSnapshot() && !$paymentRequest->isPending())
        <div class="card shadow-sm mb-4 border-info">
            <div class="card-header bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-info">
                    <i class="ti ti-camera"></i> {{ __('Financial Snapshot (Captured at Approval)') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ __('Net Payable (At Approval)') }}</small>
                        <strong class="h5">₹{{ format_indian_currency($paymentRequest->net_payable_snapshot) }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ __('Advance Used (At Approval)') }}</small>
                        <strong class="h5">₹{{ format_indian_currency($paymentRequest->advance_used_snapshot) }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">{{ __('Already Paid (At Approval)') }}</small>
                        <strong class="h5">₹{{ format_indian_currency($paymentRequest->paid_amount_snapshot) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Rejection Reason -->
        @if($paymentRequest->isRejected() && $paymentRequest->rejection_reason)
        <div class="card shadow-sm mb-4 border-danger">
            <div class="card-header bg-danger bg-opacity-10">
                <h5 class="mb-0 text-danger">
                    <i class="ti ti-alert-circle"></i> {{ __('Rejection Reason') }}
                </h5>
            </div>
            <div class="card-body">
                {{ $paymentRequest->rejection_reason }}
                @if($paymentRequest->approvedBy)
                <div class="mt-2 text-muted small">
                    {{ __('Rejected by') }}: {{ $paymentRequest->approvedBy->name }}
                    {{ $paymentRequest->approved_at ? \Carbon\Carbon::parse($paymentRequest->approved_at)->format(' d M Y, h:i A') : '' }}
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Approval Audit Trail -->
        @if($paymentRequest->approval_history && is_array($paymentRequest->approval_history) && count($paymentRequest->approval_history) > 0)
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0 text-primary">
                    <i class="ti ti-history"></i> {{ __('Approval Audit Trail') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    @foreach($paymentRequest->approval_history as $index => $history)
                    <div class="timeline-item @if($index === count($paymentRequest->approval_history) - 1) timeline-item-last @endif">
                        <div class="timeline-marker @if($history['action'] === 'approved') bg-success @elseif($history['action'] === 'rejected') bg-danger @else bg-warning @endif"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong class="text-{{ $history['action'] === 'approved' ? 'success' : ($history['action'] === 'rejected' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($history['action']) }}
                                    </strong>
                                    @if(isset($history['role']))
                                    <span class="badge bg-light text-dark ms-2">{{ ucfirst($history['role']) }}</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    @if(isset($history['timestamp']))
                                    {{ \Carbon\Carbon::parse($history['timestamp'])->format('d M Y, h:i A') }}
                                    @endif
                                </small>
                            </div>
                            @if(isset($history['from_status']) && isset($history['to_status']))
                            <div class="small text-muted mt-1">
                                {{ ucfirst($history['from_status']) }} → {{ ucfirst($history['to_status']) }}
                            </div>
                            @endif
                            @if(isset($history['remarks']) && $history['remarks'])
                            <div class="small mt-1">
                                <em>"{{ $history['remarks'] }}"</em>
                            </div>
                            @endif
                            @if(isset($history['reason']) && $history['reason'])
                            <div class="small mt-1">
                                <em>{{ $history['reason'] }}</em>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Payments Made -->
        @if($paymentRequest->payments && $paymentRequest->payments->count() > 0)
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Payments Made') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Payment No') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Mode') }}</th>
                                <th>{{ __('Reference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalPaid = 0; @endphp
                            @foreach ($paymentRequest->payments as $payment)
                            @php $totalPaid += $payment->amount; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('payments-module.edit', $payment->id) }}" target="_blank">
                                        {{ $payment->payment_number }}
                                    </a>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                <td><span class="text-success fw-bold">₹{{ format_indian_currency($payment->amount) }}</span></td>
                                <td>{{ $payment->mode ?? '-' }}</td>
                                <td>{{ $payment->reference_number ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">{{ __('Total Paid') }}</th>
                                <td colspan="3"><span class="fw-bold text-success">₹{{ format_indian_currency($totalPaid) }}</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection