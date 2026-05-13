@extends('layouts.main')

@section('page-title', __('Payment Details'))

@section('content')
<div class="card">
    <div class="card-header bg-primary">
        <h5 class="mb-0 text-white">{{ __('Payment Information') }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Payment Number') }}</label>
                    <p class="fw-bold fs-5">{{ $paymentsModule->payment_number }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Payment Date') }}</label>
                    <p class="fw-bold">{{ \Carbon\Carbon::parse($paymentsModule->payment_date)->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Supplier') }}</label>
                    <p class="fw-bold">{{ optional($paymentsModule->supplier)->name ?? '—' }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Site') }}</label>
                    <p class="fw-bold">{{ optional($paymentsModule->site)->name ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Paid Amount') }}</label>
                    <p class="fw-bold text-success fs-4">{{ currency_format_with_sym($paymentsModule->amount) }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Payment Method') }}</label>
                    <p class="fw-bold">
                        @if($paymentsModule->mode == 'cash')
                            <span class="badge bg-success">{{ __('Cash') }}</span>
                        @elseif($paymentsModule->mode == 'bank_transfer')
                            <span class="badge bg-primary">{{ __('Bank Transfer') }}</span>
                        @elseif($paymentsModule->mode == 'cheque')
                            <span class="badge bg-warning">{{ __('Cheque') }}</span>
                        @elseif($paymentsModule->mode == 'upi')
                            <span class="badge bg-info">{{ __('UPI') }}</span>
                        @else
                            {{ $paymentsModule->mode ?? '—' }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Payment Type') }}</label>
                    <p class="fw-bold">
                        @if($paymentsModule->payment_type == 'advance_against_po')
                            <span class="badge bg-info">{{ __('Advance Against PO') }}</span>
                        @elseif($paymentsModule->payment_type == 'against_po')
                            <span class="badge bg-primary">{{ __('Against PO') }}</span>
                        @elseif($paymentsModule->payment_type == 'against_invoice')
                            <span class="badge bg-primary">{{ __('Against Invoice') }}</span>
                        @elseif($paymentsModule->payment_type == 'mixed')
                            <span class="badge bg-warning">{{ __('Mixed') }}</span>
                        @elseif($paymentsModule->payment_type == 'on_account')
                            <span class="badge bg-secondary">{{ __('On Account') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($paymentsModule->payment_type) }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Reference Number') }}</label>
                    <p class="fw-bold">{{ $paymentsModule->reference_number ?? '—' }}</p>
                </div>
            </div>
        </div>

        @if($paymentsModule->purchase_order_id)
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Purchase Order') }}</label>
                    <p class="fw-bold">{{ optional($paymentsModule->purchaseOrder)->po_number ?? '—' }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('PO Amount') }}</label>
                    <p class="fw-bold">{{ currency_format_with_sym(optional($paymentsModule->purchaseOrder)->grand_total ?? 0) }}</p>
                </div>
            </div>
        </div>
        @endif

        @if($paymentsModule->purchase_invoice_id)
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Invoice Reference') }}</label>
                    <p class="fw-bold">{{ optional($paymentsModule->invoice)->invoice_number ?? '—' }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Invoice Amount') }}</label>
                    <p class="fw-bold">{{ currency_format_with_sym(optional($paymentsModule->invoice)->grand_total ?? 0) }}</p>
                </div>
            </div>
        </div>
        @endif

        @if($paymentsModule->notes)
        <div class="row">
            <div class="col-12">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Notes') }}</label>
                    <p class="fw-bold">{{ $paymentsModule->notes }}</p>
                </div>
            </div>
        </div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Payment Proof') }}</label>
                    @if($paymentsModule->payment_proff_file)
                        <a href="{{ asset('' . ltrim($paymentsModule->payment_proff_file, '/')) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-download"></i> {{ __('Download Proof') }}
                        </a>
                    @else
                        <span class="text-muted">{{ __('N/A') }}</span>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted">{{ __('Status') }}</label>
                    <p class="fw-bold">
                        @if($paymentsModule->status == 'completed')
                            <span class="badge bg-success">{{ __('Completed') }}</span>
                        @else
                            <span class="badge bg-warning">{{ ucfirst($paymentsModule->status ?? 'Pending') }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection