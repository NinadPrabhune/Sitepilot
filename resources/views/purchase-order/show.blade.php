@extends('layouts.main')

@section('page-title', __('Purchase Order Details'))
@section('page-breadcrumb', __('Purchase Orders'))

@section('page-action')
<a href="{{ route('purchase-order.index') }}" class="btn btn-sm btn-primary">
    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
</a>
@can('grn create')
<a data-size="xxl" data-url="{{ route('grn.create', ['po_id' => $purchaseOrder->id]) }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Create GRN')}}" data-title="{{__('Create Goods Receipt Note')}}" class="btn btn-sm btn-info ms-2">
    <i class="ti ti-package"></i> {{ __('Create GRN') }}
</a>
@endcan
@endsection

@section('content')

{{-- ================= BASIC INFORMATION ================= --}}
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Purchase Order') }}: {{ $purchaseOrder->po_number }}</h5>
                <span class="badge px-3 py-2 fs-6 
                    bg-{{ $purchaseOrder->display_status == 'Draft' ? 'secondary' : 
                        ($purchaseOrder->display_status == 'Approved' ? 'primary' : 
                        ($purchaseOrder->display_status == 'Partial Received' ? 'warning' : 
                        ($purchaseOrder->display_status == 'Completed' ? 'success' : 
                        ($purchaseOrder->display_status == 'Flagged - Corrected' ? 'info' : 
                        ($purchaseOrder->display_status == 'Short Closed' ? 'dark' : 'danger'))))) }}">
                    {{ __($purchaseOrder->display_status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="row gy-4">

                    {{-- PO Details --}}
                    <div class="col-md-4">
                        <small class="text-muted">{{ __('PO Number') }}</small>
                        <div class="fw-bold">{{ $purchaseOrder->po_number }}</div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('PO Date') }}</small>
                        <div class="fw-bold">
                            {{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('d M Y') : '-' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Supplier Invoice Number') }}</small>
                        <div class="fw-bold">{{ $purchaseOrder->supplier_invoice_number ?? '-' }}</div>
                    </div>

                    {{-- Relationships --}}
                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Supplier') }}</small>
                        <div class="fw-bold">{{ $purchaseOrder->supplier->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Site') }}</small>
                        <div class="fw-bold">{{ $purchaseOrder->site->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Indent') }}</small>
                        <div class="fw-bold">{{ $purchaseOrder->indent->indent_number ?? '-' }}</div>
                    </div>

                    {{-- Assigned To --}}
                    @if($assignedUsers->isNotEmpty())
                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Assigned To') }}</small>
                        <div class="mt-1">
                            @foreach($assignedUsers as $user)
                                <span class="badge bg-primary">{{ $user->name }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Tax & Delivery --}}
                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Tax Type') }}</small>
                        <div class="fw-bold">
                            {{ $purchaseOrder->tax_type == 'igst' ? __('IGST') : __('CGST + SGST') }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Delivery Date') }}</small>
                        <div class="fw-bold">
                            {{ $purchaseOrder->delivery_date ? \Carbon\Carbon::parse($purchaseOrder->delivery_date)->format('d M Y') : '-' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Reference File') }}</small>
                        @if($purchaseOrder->reference_file)
                            <div>
                                <a href="{{ asset($purchaseOrder->reference_file) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="ti ti-file"></i> {{ __('View File') }}
                                </a>
                            </div>
                        @else
                            <div class="fw-bold">-</div>
                        @endif
                    </div>

                    {{-- Description --}}
                    @if($purchaseOrder->description)
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Description') }}</small>
                        <div class="p-3 border rounded bg-light mt-1">
                            {{ $purchaseOrder->description }}
                        </div>
                    </div>
                    @endif

                    @if($purchaseOrder->delivery_terms_conditions)
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Delivery Terms & Conditions') }}</small>
                        <div class="p-3 border rounded bg-light mt-1">
                            {{ $purchaseOrder->delivery_terms_conditions }}
                        </div>
                    </div>
                    @endif

                    @if($purchaseOrder->remark)
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Remark') }}</small>
                        <div class="p-3 border rounded bg-light mt-1">
                            {{ $purchaseOrder->remark }}
                        </div>
                    </div>
                    @endif

                    @if($purchaseOrder->rejection_reason)
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Rejection Reason') }}</small>
                        <div class="p-3 border rounded bg-danger bg-opacity-10 mt-1 text-danger">
                            {{ $purchaseOrder->rejection_reason }}
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= ITEMS TABLE ================= --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Purchase Order Items') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Indent Qty') }}</th>
                                <th>{{ __('PO Qty') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('GST (%)') }}</th>
                                <th>{{ __('Tax Amount') }}</th>
                                <th>{{ __('Discount') }}</th>
                                <th>{{ __('Subtotal') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalTaxable = 0;
                                $totalTax = 0;
                                $totalDiscount = 0;
                            @endphp

                            @forelse($purchaseOrder->items as $item)
                                @php
                                    $indentItem = optional($purchaseOrder->indent)
                                        ->items
                                        ->where('material_id', $item->material_id)
                                        ->first();

                                    $indentQty = $indentItem->quantity ?? 0;
                                    $rowTaxable = ($item->quantity * $item->price);
                                    $totalTaxable += $rowTaxable;
                                    $totalTax += $item->tax_amount ?? 0;
                                    $totalDiscount += $item->discount_amount ?? 0;
                                @endphp

                                <tr>
                                    <td class="fw-semibold">{{ $item->material->name ?? '-' }}</td>
                                    <td>{{ number_format($indentQty, 2) }}</td>
                                    <td>{{ number_format($item->quantity, 2) }}</td>
                                    <td>{{ $item->unit ?? '-' }}</td>
                                    <td>{{ currency_format_with_sym_indian($item->price) }}</td>
                                    <td>
                                        @if($item->gstMaster)
                                            {{ $item->gstMaster->name }}
                                            <small class="text-muted">({{ $purchaseOrder->tax_type == 'igst' ? $item->gstMaster->igst : ($item->gstMaster->cgst + $item->gstMaster->sgst) }}%)</small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ currency_format_with_sym_indian($item->tax_amount ?? 0) }}</td>
                                    <td>{{ currency_format_with_sym_indian($item->discount_amount ?? 0) }}</td>
                                    <td class="fw-semibold text-primary">
                                        {{ currency_format_with_sym_indian($item->subtotal ?? 0) }}
                                    </td>
                                    <td>{{ $item->remarks ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">{{ __('No items found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= FINANCIAL SUMMARY (Moved after Items) ================= --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Financial Summary') }}</h5>
            </div>
            <div class="card-body">
                <div class="row gy-4">
                    
                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Total Taxable Value') }}</small>
                        <div class="fw-bold text-primary fs-5">
                            {{ currency_format_with_sym_indian($purchaseOrder->total_taxable_value ?? 0) }}
                        </div>
                    </div>

                    @if($purchaseOrder->tax_type == 'igst')
                        <div class="col-md-3">
                            <small class="text-muted">{{ __('Total IGST') }}</small>
                            <div class="fw-bold fs-5">
                                {{ currency_format_with_sym_indian($purchaseOrder->total_igst ?? 0) }}
                            </div>
                        </div>
                    @else
                        <div class="col-md-3">
                            <small class="text-muted">{{ __('Total CGST') }}</small>
                            <div class="fw-bold fs-5">
                                {{ currency_format_with_sym_indian($purchaseOrder->total_cgst ?? 0) }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">{{ __('Total SGST') }}</small>
                            <div class="fw-bold fs-5">
                                {{ currency_format_with_sym_indian($purchaseOrder->total_sgst ?? 0) }}
                            </div>
                        </div>
                    @endif

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Total Tax') }}</small>
                        <div class="fw-bold fs-5">
                            {{ currency_format_with_sym_indian($purchaseOrder->total_tax ?? 0) }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Total Discount') }}</small>
                        <div class="fw-bold text-danger fs-5">
                            {{ currency_format_with_sym_indian($purchaseOrder->total_discount ?? 0) }}
                        </div>
                    </div>

                    {{-- Additional Charges/Deductions --}}
                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Additional Charge') }}</small>
                        <div class="fw-bold">
                            + {{ currency_format_with_sym_indian($purchaseOrder->additional_charge ?? 0) }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Additional Deduction') }}</small>
                        <div class="fw-bold text-danger">
                            - {{ currency_format_with_sym_indian($purchaseOrder->additional_deduction ?? 0) }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Additional Discount') }}</small>
                        <div class="fw-bold text-danger">
                            - {{ currency_format_with_sym_indian($purchaseOrder->additional_discount ?? 0) }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Grand Total') }}</small>
                        <div class="fw-bold text-success fs-4">
                            {{ currency_format_with_sym_indian($purchaseOrder->grand_total ?? 0) }}
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= PAYMENT REQUESTS - PO ADVANCE ================= --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Payment Requests - PO Advance') }}</h5>
                <small class="text-muted">{{ $paymentRequests->where('type', 'po_advance')->count() }} {{ __('Requests') }}</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('PO') }}</th>
                                <th>{{ __('PO Date') }}</th>
                                <th>{{ __('PO Amount') }}</th>
                                <th>{{ __('Requested Amount') }}</th>
                                <th>{{ __('Requested Date') }}</th>
                                <th>{{ __('Approved Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Requested By') }}</th>
                                <th>{{ __('Approved By') }}</th>
                                <th>{{ __('Approved At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentRequests->where('type', 'po_advance') as $paymentRequest)
                                <tr>
                                    <td class="fw-semibold">{{ $paymentRequest->po?->po_number ?? '-' }}</td>
                                    <td>{{ $paymentRequest->po?->po_date ? \Carbon\Carbon::parse($paymentRequest->po->po_date)->format('d M Y') : '-' }}</td>
                                    <td class="fw-semibold">{{ $paymentRequest->po ? currency_format_with_sym_indian($paymentRequest->po->grand_total) : '-' }}</td>
                                    <td class="fw-semibold">{{ currency_format_with_sym_indian($paymentRequest->requested_amount) }}</td>
                                    <td>{{ $paymentRequest->created_at ? \Carbon\Carbon::parse($paymentRequest->created_at)->format('d M Y, h:i A') : '-' }}</td>
                                    <td class="fw-semibold {{ $paymentRequest->approved_amount ? 'text-success' : '' }}">
                                        {{ $paymentRequest->approved_amount ? currency_format_with_sym_indian($paymentRequest->approved_amount) : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($paymentRequest->status == 'pending') bg-warning
                                            @elseif($paymentRequest->status == 'approved') bg-success
                                            @elseif($paymentRequest->status == 'partially_approved') bg-info
                                            @elseif($paymentRequest->status == 'rejected') bg-danger
                                            @elseif($paymentRequest->status == 'partially_paid') bg-primary
                                            @elseif($paymentRequest->status == 'paid') bg-success
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $paymentRequest->status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $paymentRequest->requestedBy->name ?? '-' }}</td>
                                    <td>
                                        @if($paymentRequest->approvedBy)
                                            {{ $paymentRequest->approvedBy->name }}
                                        @elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->creator)
                                            {{ $paymentRequest->payments->first()->creator->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($paymentRequest->approved_at)
                                            {{ \Carbon\Carbon::parse($paymentRequest->approved_at)->format('d M Y, h:i A') }}
                                        @elseif($paymentRequest->paid_at)
                                            {{ \Carbon\Carbon::parse($paymentRequest->paid_at)->format('d M Y, h:i A') }}
                                        @elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->created_at)
                                            {{ \Carbon\Carbon::parse($paymentRequest->payments->first()->created_at)->format('d M Y, h:i A') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">{{ __('No PO Advance payment requests found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= PAYMENT REQUESTS - INVOICE PAYMENT ================= --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
             <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Payment Requests - Invoice Payment') }}</h5>
                <small class="text-muted">{{ $paymentRequests->where('type', 'invoice_payment')->count() }} {{ __('Requests') }}</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Invoice Date') }}</th>
                                <th>{{ __('Invoice Amount') }}</th>
                                <th>{{ __('Requested Amount') }}</th>
                                <th>{{ __('Requested Date') }}</th>
                                <th>{{ __('Approved Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Requested By') }}</th>
                                <th>{{ __('Approved By') }}</th>
                                <th>{{ __('Approved At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentRequests->where('type', 'invoice_payment') as $paymentRequest)
                                <tr>
                                    <td>{{ $paymentRequest->invoice?->invoice_number ?? '-' }}</td>
                                    <td>{{ $paymentRequest->invoice?->invoice_date ? \Carbon\Carbon::parse($paymentRequest->invoice->invoice_date)->format('d M Y') : '-' }}</td>
                                    <td class="fw-semibold">{{ $paymentRequest->invoice ? currency_format_with_sym_indian($paymentRequest->invoice->grand_total) : '-' }}</td>
                                    <td class="fw-semibold">{{ currency_format_with_sym_indian($paymentRequest->requested_amount) }}</td>
                                    <td>{{ $paymentRequest->created_at ? \Carbon\Carbon::parse($paymentRequest->created_at)->format('d M Y, h:i A') : '-' }}</td>
                                    <td class="fw-semibold {{ $paymentRequest->approved_amount ? 'text-success' : '' }}">
                                        {{ $paymentRequest->approved_amount ? currency_format_with_sym_indian($paymentRequest->approved_amount) : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($paymentRequest->status == 'pending') bg-warning
                                            @elseif($paymentRequest->status == 'approved') bg-success
                                            @elseif($paymentRequest->status == 'partially_approved') bg-info
                                            @elseif($paymentRequest->status == 'rejected') bg-danger
                                            @elseif($paymentRequest->status == 'partially_paid') bg-primary
                                            @elseif($paymentRequest->status == 'paid') bg-success
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $paymentRequest->status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $paymentRequest->requestedBy->name ?? '-' }}</td>
                                    <td>
                                        @if($paymentRequest->approvedBy)
                                            {{ $paymentRequest->approvedBy->name }}
                                        @elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->creator)
                                            {{ $paymentRequest->payments->first()->creator->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($paymentRequest->approved_at)
                                            {{ \Carbon\Carbon::parse($paymentRequest->approved_at)->format('d M Y, h:i A') }}
                                        @elseif($paymentRequest->paid_at)
                                            {{ \Carbon\Carbon::parse($paymentRequest->paid_at)->format('d M Y, h:i A') }}
                                        @elseif($paymentRequest->payments->count() > 0 && $paymentRequest->payments->first()->created_at)
                                            {{ \Carbon\Carbon::parse($paymentRequest->payments->first()->created_at)->format('d M Y, h:i A') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">{{ __('No Invoice Payment requests found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= SUPPLIER LEDGER ================= --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Supplier Ledger') }} - {{ $purchaseOrder->supplier->name ?? '-' }}</h5>
                <small class="text-muted">{{ $supplierTransactions->count() }} {{ __('Transactions') }}</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th class="text-end">{{ __('Debit') }}</th>
                                <th class="text-end">{{ __('Credit') }}</th>
                                <th class="text-end">{{ __('Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supplierTransactions as $transaction)
                                <tr>
                                    <td>
                                        {{ $transaction->created_at ? \Carbon\Carbon::parse($transaction->created_at)->format('d M Y, h:i A') : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($transaction->reference_type == 'invoice') bg-danger
                                            @elseif($transaction->reference_type == 'payment') bg-success
                                            @elseif($transaction->reference_type == 'advance') bg-info
                                            @elseif($transaction->reference_type == 'po') bg-primary
                                            @elseif($transaction->reference_type == 'grn') bg-warning
                                            @else bg-secondary
                                            @endif">
                                            {{ $transaction->reference_type_label }}
                                        </span>
                                    </td>
                                    <td class="fw-semibold">{{ $transaction->reference_number }}</td>
                                    <td>{{ $transaction->description ?? '-' }}</td>
                                    <td class="text-end {{ $transaction->debit > 0 ? 'text-danger' : '' }}">
                                        {{ $transaction->debit > 0 ? currency_format_with_sym_indian($transaction->debit) : '-' }}
                                    </td>
                                    <td class="text-end {{ $transaction->credit > 0 ? 'text-success' : '' }}">
                                        {{ $transaction->credit > 0 ? currency_format_with_sym_indian($transaction->credit) : '-' }}
                                    </td>
                                    <td class="text-end fw-bold {{ $transaction->balance > 0 ? 'text-danger' : ($transaction->balance < 0 ? 'text-success' : '') }}">
                                        {{ currency_format_with_sym_indian($transaction->balance) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">{{ __('No ledger transactions found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= AUDIT INFORMATION ================= --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Audit Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row gy-4">
                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Created By') }}</small>
                        <div class="fw-bold">{{ $purchaseOrder->creator->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Created At') }}</small>
                        <div>{{ $purchaseOrder->created_at ? $purchaseOrder->created_at->format('d M Y, h:i A') : '-' }}</div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Updated At') }}</small>
                        <div>{{ $purchaseOrder->updated_at ? $purchaseOrder->updated_at->format('d M Y, h:i A') : '-' }}</div>
                    </div>

                    @if($purchaseOrder->deleted_at)
                    <div class="col-md-3">
                        <small class="text-muted">{{ __('Deleted At') }}</small>
                        <div class="text-danger">{{ $purchaseOrder->deleted_at->format('d M Y, h:i A') }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
