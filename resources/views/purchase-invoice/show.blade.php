@extends('layouts.main')

@section('page-title')
{{ __('Purchase Invoice Details') }}
@endsection

@section('page-breadcrumb')
{{ __('Purchase Invoice Details') }}
@endsection

@section('page-action')
<a href="{{ url()->previous() }}" class="btn btn-sm btn-light border">
    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
</a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        <!-- Invoice Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Invoice Information') }}</h5>
                <span class="badge bg-secondary">{{ __('#') . $purchaseInvoice->invoice_number }}</span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>{{ __('Invoice Date') }}:</strong><br>
                        {{ \Carbon\Carbon::parse($purchaseInvoice->invoice_date)->format('d M Y') }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Supplier Invoice No') }}:</strong><br>
                        {{ $purchaseInvoice->supplier_invoice_number ?? 'N/A' }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Supplier') }}:</strong><br>
                        {{ optional($purchaseInvoice->supplier)->name }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Site') }}:</strong><br>
                        {{ optional($purchaseInvoice->site)->name }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>{{ __('PO Number') }}:</strong><br>
                        {{ optional($purchaseInvoice->purchaseOrder)->po_number ?? 'N/A' }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('GRN Number') }}:</strong><br>
                        {{ optional($purchaseInvoice->grn)->grn_number ?? 'N/A' }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Tax Type') }}:</strong><br>
                        {{ strtoupper($purchaseInvoice->tax_type ?? 'CGST') }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Invoice Type') }}:</strong><br>
                        @if($purchaseInvoice->invoice_type === 'minor_misc_service')
                        <span class="badge bg-warning text-dark">Minor/Misc Service</span>
                        @else
                        <span class="badge bg-primary">General PO</span>
                        @endif
                    </div>
                </div>

                {{-- Assigned To --}}
                @if($assignedUsers->isNotEmpty())
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>{{ __('Assigned To') }}:</strong><br>
                        <div class="mt-1">
                            @foreach($assignedUsers as $user)
                                <span class="badge bg-primary">{{ $user->name }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>{{ __('Payment Status') }}:</strong><br>
                        @php $status = strtolower($purchaseInvoice->payment_status); @endphp
                        @switch($status)
                        @case('unpaid') <span class="badge bg-danger">Unpaid</span> @break
                        @case('paid') <span class="badge bg-success">Paid</span> @break
                        @case('overpaid') <span class="badge bg-info text-dark">Overpaid</span> @break
                        @case('partially paid') <span class="badge bg-warning text-dark">Partially Paid</span> @break
                        @default <span class="badge bg-secondary">{{ ucfirst($status) }}</span>
                        @endswitch
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Invoice File') }}:</strong><br>
                        @if($purchaseInvoice->invoice_file)
                        <a href="{{ asset('/' . ltrim($purchaseInvoice->invoice_file, '/')) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-download"></i> {{ __('Download File') }}
                        </a>
                        @else
                        <span class="text-muted">{{ __('N/A') }}</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Created By') }}:</strong><br>
                        {{ optional($purchaseInvoice->creator)->name ?? 'N/A' }}
                    </div>
                    <div class="col-md-3">
                        <strong>{{ __('Status') }}:</strong><br>
                        <span class="badge bg-success">{{ $purchaseInvoice->status ?? 'Approved' }}</span>
                    </div>
                </div>

                <!-- Tax & Totals -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2 mb-3">{{ __('Tax Summary') }}</h6>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-2">
                        <strong>{{ __('Taxable Value') }}:</strong><br>
                        <span class="text-primary">{{ currency_format_with_sym_indian($purchaseInvoice->total_taxable_value ?? 0) }}</span>
                    </div>
                    @if($purchaseInvoice->tax_type === 'igst')
                    <div class="col-md-2">
                        <strong>{{ __('IGST') }}:</strong><br>
                        <span class="text-info">{{ currency_format_with_sym_indian($purchaseInvoice->total_igst ?? 0) }}</span>
                    </div>
                    @else
                    <div class="col-md-2">
                        <strong>{{ __('CGST') }}:</strong><br>
                        <span class="text-info">{{ currency_format_with_sym_indian($purchaseInvoice->total_cgst ?? 0) }}</span>
                    </div>
                    <div class="col-md-2">
                        <strong>{{ __('SGST') }}:</strong><br>
                        <span class="text-info">{{ currency_format_with_sym_indian($purchaseInvoice->total_sgst ?? 0) }}</span>
                    </div>
                    @endif
                    <div class="col-md-2">
                        <strong>{{ __('Total Tax') }}:</strong><br>
                        <span class="text-warning">{{ currency_format_with_sym_indian($purchaseInvoice->total_tax ?? 0) }}</span>
                    </div>
                    <div class="col-md-2">
                        <strong>{{ __('Discount') }}:</strong><br>
                        <span class="text-danger">{{ currency_format_with_sym_indian($purchaseInvoice->total_discount ?? 0) }}</span>
                    </div>
                    <div class="col-md-2">
                        <strong>{{ __('Grand Total') }}:</strong><br>
                        <span class="h6 text-success">{{ currency_format_with_sym_indian($purchaseInvoice->grand_total ?? $purchaseInvoice->total_amount) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        @if($purchaseInvoice->invoice_type === 'general_po')
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Invoice Items') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Discount') }}</th>
                                <th>{{ __('Tax Amount') }}</th>
                                <th>{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseInvoice->items as $item)
                            <tr>
                                <td>{{ optional($item->material)->name }}</td>
                                <td>{{ number_format($item->quantity, 2) }}</td>
                                <td>{{ $item->unit ?? 'PCS' }}</td>
                                <td>{{ currency_format_with_sym_indian($item->price) }}</td>
                                <td>{{ currency_format_with_sym_indian($item->discount_amount ?? 0) }}</td>
                                <td>{{ currency_format_with_sym_indian($item->tax_amount ?? 0) }}</td>
                                <td>{{ currency_format_with_sym_indian($item->subtotal) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Payments Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Payments Made') }}</h5>
            </div>
            <div class="card-body">
                @if($purchaseInvoice->payments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __(' Payment No') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Mode') }}</th>
                                <th>{{ __('Reference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $totalPaid = $purchaseInvoice->payments->sum('amount');
                            $balance = ($purchaseInvoice->grand_total ?? $purchaseInvoice->total_amount) - $totalPaid;
                            @endphp
                            @foreach ($purchaseInvoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                <td><span class="text-success fw-bold">{{ currency_format_with_sym_indian($payment->amount) }}</span></td>
                                <td><span class="badge bg-secondary">{{ ucfirst($payment->payment_type) }}</span></td>
                                <td>{{ $payment->mode ?? '—' }}</td>
                                <td>{{ $payment->reference_number ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">{{ __('Total Paid') }}</th>
                                <td colspan="4"><span class="fw-bold text-success">{{ currency_format_with_sym_indian($totalPaid) }}</span></td>
                            </tr>
                            <tr>
                                <th colspan="2">{{ __('Balance Remaining') }}</th>
                                <td colspan="4">
                                    @if($balance > 0)
                                    <span class="fw-bold text-danger">{{ currency_format_with_sym_indian($balance) }}</span>
                                    @else
                                    <span class="fw-bold text-success">{{ __('Fully Paid') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <p class="text-muted">{{ __('No payments recorded for this invoice.') }}</p>
                @endif
            </div>
        </div>


    </div>
</div>
@endsection
