@extends('layouts.main')
@section('page-title')
    {{ __('Supplier Ledger Report') }}
@endsection
@section('page-breadcrumb')
    {{ __('Report') }},
    {{ __('Supplier Ledger Report') }}
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
    <script src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>
    <script>
        var filename = $('#filename').val();

        function saveAsPDF() {
            var element = document.getElementById('printableArea');
            var opt = {
                margin: 0.3,
                filename: filename,
                image: {type: 'jpeg', quality: 1},
                html2canvas: {
                    scale: 4, 
                    dpi: 72, 
                    letterRendering: true,
                    onclone: function(clonedDoc) {
                        var printButtons = clonedDoc.querySelectorAll('#printableArea .print-btn, #printableArea .filter-section');
                        printButtons.forEach(function(el) {
                            el.style.display = 'none !important';
                        });
                    }
                },
                jsPDF: {unit: 'in', format: 'A2'}
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
@endpush

@section('content')
    <!-- [ Main Content ] start -->
    <div class="row">
        <!-- [ sample-page ] start -->
        <div class="col-sm-12">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header card-body table-border-style">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5>{{ __('Supplier Ledger Report') }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section (for screen - not included in PDF) -->
            <div class="row filter-section print-btn">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form action="{{ route('reports.supplier-ledger') }}" method="GET" class="mb-0">
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="supplier_id" class="form-label">{{ __('Supplier') }}</label>
                                            <select name="supplier_id" id="supplier_id" class="form-control">
                                               
                                                @foreach($suppliers as $key => $supplier)
                                                    <option value="{{ $key }}" {{ isset($filters['supplier_id']) && $filters['supplier_id'] == $key ? 'selected' : '' }}>
                                                        {{ $supplier }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="site_id" class="form-label">{{ __('Site') }}</label>
                                            <select name="site_id" id="site_id" class="form-control">
                                               
                                                @foreach($sites as $key => $site)
                                                    <option value="{{ $key }}" {{ isset($filters['site_id']) && $filters['site_id'] == $key ? 'selected' : '' }}>
                                                        {{ $site }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="from_date" class="form-label">{{ __('From Date') }}</label>
                                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $filters['from_date'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="to_date" class="form-label">{{ __('To Date') }}</label>
                                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $filters['to_date'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                         <div class="form-group">
                                        <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                                        <a href="{{ route('reports.supplier-ledger') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Printable Area (included in PDF) -->
            <div id="printableArea">
                <!-- Report Title -->
                <div class="row mb-3">
                    <div class="col-12 text-center">
                        <h4>{{ __('Supplier Ledger Report') }}</h4>
                    </div>
                </div>

                <!-- Filter Info Row (for PDF) -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="border p-2">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>{{ __('Supplier') }}:</strong>
                                    {{ isset($filters['supplier_id']) && isset($suppliers[$filters['supplier_id']]) ? $suppliers[$filters['supplier_id']] : __('All') }}
                                </div>
                                <div class="col-md-3">
                                    <strong>{{ __('Site') }}:</strong>
                                    {{ isset($filters['site_id']) && isset($sites[$filters['site_id']]) ? $sites[$filters['site_id']] : __('All') }}
                                </div>
                                <div class="col-md-3">
                                    <strong>{{ __('From Date') }}:</strong>
                                    {{ $filters['from_date'] ?? __('All') }}
                                </div>
                                <div class="col-md-3">
                                    <strong>{{ __('To Date') }}:</strong>
                                    {{ $filters['to_date'] ?? __('All') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-primary">
                                    <i class="ti ti-shopping-cart"></i>
                                </div>
                                <p class="text-muted text-sm mb-2">{{ __('Total PO Amount') }}</p>
                                <h4 class="mb-0">{{ currency_format_with_sym_indian($summary['total_po'] ?? 0) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-info">
                                    <i class="ti ti-file-invoice"></i>
                                </div>
                                <p class="text-muted text-sm mb-2">{{ __('Total Invoice') }}</p>
                                <h4 class="mb-0">{{ currency_format_with_sym_indian($summary['total_invoice'] ?? 0) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-success">
                                    <i class="ti ti-credit-card"></i>
                                </div>
                                <p class="text-muted text-sm mb-2">{{ __('Total Payments') }}</p>
                                <h4 class="mb-0">{{ currency_format_with_sym_indian($summary['total_payments']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-fluid">
                            <div class="card-body">
                                <div class="theme-avtar bg-danger">
                                    <i class="ti ti-file-dollar"></i>
                                </div>
                                <p class="text-muted text-sm mb-2">{{ __('Current Balance') }}</p>
                                <h4 class="mb-0">{{ currency_format_with_sym_indian($summary['current_balance']) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header card-body table-border-style">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>{{ __('Ledger Transactions') }}</h5>
                                    <div class="d-flex gap-2 print-btn">
                                        <button onclick="saveAsPDF()" class="btn btn-sm btn-primary">
                                            <i class="ti ti-file"></i> Print
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table" id="pc-dt-simple">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Date & Time') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Reference') }}</th>
                                                <th>{{ __('Supplier') }}</th>
                                                <th class="text-right" style="text-align: right;">{{ __('Ref. Amount') }}</th>
                                                <th>{{ __('Site') }}</th>
                                                <th class="text-right" style="text-align: right;">{{ __('Debit') }}</th>
                                                <th class="text-right" style="text-align: right;">{{ __('Credit') }}</th>
                                                <th class="text-right" style="text-align: right;">{{ __('Balance') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                            <tr class="table-primary">
                                                <th colspan="6" class="text-end">{{ __('Total') }}</th>
                                                <th class="text-right" style="text-align: right;">{{ currency_format_with_sym_indian($transactions->sum('debit')) }}</th>
                                                <th class="text-right" style="text-align: right;">{{ currency_format_with_sym_indian($transactions->sum('credit')) }}</th>
                                                <th class="text-right" style="text-align: right;">{{ currency_format_with_sym_indian($summary['current_balance'] ?? 0) }}</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($transactions as $transaction)
                                                @php
                                                    // Get meta as array
                                                    $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
                                                    
                                                    // Check if non-accounting (PO, GRN should be ignored in balance)
                                                    $isNonAccounting = !empty($meta['non_accounting']);
                                                @endphp
                                                <tr>
                                                    <td>
                                                        @php
                                                            // Priority: created_at (has time) > transaction_datetime > transaction_date
                                                            $sortDate = $transaction->created_at;
                                                            if (!empty($transaction->transaction_datetime)) {
                                                                $sortDate = $transaction->transaction_datetime;
                                                            }
                                                        @endphp
                                                        {{ \Carbon\Carbon::parse($sortDate)->format('d M Y') }}
                                                        <br><small class="text-muted">{{ \Carbon\Carbon::parse($sortDate)->format('h:i A') }}</small>
                                                    </td>
                                                    <td>
                                                        @if($transaction->reference_type == 'po')
                                                            <span class="badge bg-primary">{{ __('PO') }}</span>
                                                        @elseif($transaction->reference_type == 'grn')
                                                            <span class="badge bg-secondary">{{ __('GRN') }}</span>
                                                        @elseif($transaction->reference_type == 'invoice')
                                                            <span class="badge bg-info">{{ __('Invoice') }}</span>
                                                        @elseif($transaction->reference_type == 'payment')
                                                            <span class="badge bg-success">{{ __('Payment') }}</span>
                                                        @elseif($transaction->reference_type == 'advance')
                                                            <span class="badge bg-warning">{{ __('Advance') }}</span>
                                                        @elseif($transaction->reference_type == 'adjustment')
                                                            <span class="badge bg-dark">{{ __('Adjustment') }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($transaction->reference_type == 'po')
                                                            <a href="{{ route('purchase-order.show', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
                                                        @elseif($transaction->reference_type == 'grn')
                                                            <a href="{{ route('grn.show', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
                                                        @elseif($transaction->reference_type == 'invoice')
                                                            <a href="{{ route('purchase-invoice.show', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
                                                        @elseif($transaction->reference_type == 'payment' || $transaction->reference_type == 'advance')
                                                            <a href="{{ route('payments-module.edit', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
                                                        @else
                                                            {{ $transaction->reference_number ?? '-' }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $transaction->supplier->name ?? '-' }}</td>
                                                    <td class="text-right" style="text-align: right;">{{ currency_format_with_sym_indian($transaction->reference_amount ?? 0) }}</td>
                                                    <td>{{ $transaction->site->name ?? '-' }}</td>
                                                    <td class="text-right" style="text-align: right;">
                                                        @if($isNonAccounting)
                                                            -
                                                        @else
                                                            @if((float)($transaction->debit ?? 0) > 0)
                                                                {{ currency_format_with_sym_indian($transaction->debit) }}
                                                            @else
                                                                -
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="text-right" style="text-align: right;">
                                                        @if($isNonAccounting)
                                                            -
                                                        @else
                                                            @if((float)($transaction->credit ?? 0) > 0)
                                                                {{ currency_format_with_sym_indian($transaction->credit) }}
                                                            @else
                                                                -
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="text-right {{ $transaction->balance < 0 ? 'text-danger' : '' }}" style="text-align: right;">
                                                        @if($isNonAccounting)
                                                            -
                                                        @else
                                                            {{ currency_format_with_sym_indian($transaction->balance) }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $transaction->description ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center">{{ __('No transactions found') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ sample-page ] end -->
    </div>
    <!-- [ Main Content ] end -->
    
    <input type="hidden" id="filename" value="Supplier-Ledger-Report-{{ date('Y-m-d') }}">
@endsection
