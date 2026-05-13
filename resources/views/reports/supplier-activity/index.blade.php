@extends('layouts.main')
@section('page-title')
    {{ __('Supplier Activity Report') }}
@endsection
@section('page-breadcrumb')
    {{ __('Report') }},
    {{ __('Supplier Activity Report') }}
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
                                <h5>{{ __('Supplier Activity Report') }}</h5>
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
                            <form action="{{ route('reports.supplier-activity') }}" method="GET" class="mb-0">
                                @csrf
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="supplier_id" class="form-label">{{ __('Supplier') }} <span class="text-danger">*</span></label>
                                            <select name="supplier_id" id="supplier_id" class="form-control" required>
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
                                            <label for="from_date" class="form-label">{{ __('From Date') }} <span class="text-danger">*</span></label>
                                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $filters['from_date'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="to_date" class="form-label">{{ __('To Date') }} <span class="text-danger">*</span></label>
                                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $filters['to_date'] }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                         <div class="form-group">
                                        <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                                        <a href="{{ route('reports.supplier-activity') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
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
                        <h4>{{ __('Supplier Activity Report') }}</h4>
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
                                <div class="theme-avtar {{ $summary['final_balance'] < 0 ? 'bg-danger' : 'bg-warning' }}">
                                    <i class="ti ti-file-dollar"></i>
                                </div>
                                <p class="text-muted text-sm mb-2">{{ __('Final Balance') }}</p>
                                <h4 class="mb-0">{{ currency_format_with_sym_indian($summary['final_balance']) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header card-body table-border-style">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>{{ __('Activity Timeline') }}</h5>
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
                                        </thead>
                                        <tbody>
                                            @forelse($activities as $activity)
                                                <tr>
                                                    <td>
                                                        @php
                                                            $dateTime = \Carbon\Carbon::parse($activity->date_time);
                                                        @endphp
                                                        {{ $dateTime->format('d M Y') }}
                                                        <br><small class="text-muted">{{ $dateTime->format('h:i A') }}</small>
                                                    </td>
                                                    <td>
                                                        @if($activity->type == 'PO')
                                                            <span class="badge bg-primary">{{ __('PO') }}</span>
                                                        @elseif($activity->type == 'GRN')
                                                            <span class="badge bg-secondary">{{ __('GRN') }}</span>
                                                        @elseif($activity->type == 'Invoice')
                                                            <span class="badge bg-info">{{ __('Invoice') }}</span>
                                                        @elseif($activity->type == 'Payment')
                                                            <span class="badge bg-success">{{ __('Payment') }}</span>
                                                        @elseif($activity->type == 'Advance')
                                                            <span class="badge bg-warning">{{ __('Advance') }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($activity->type == 'PO')
                                                            <a href="{{ route('purchase-order.show', $activity->reference_id) }}" target="_blank">{{ $activity->reference }}</a>
                                                        @elseif($activity->type == 'GRN')
                                                            <a href="{{ route('grn.show', $activity->reference_id) }}" target="_blank">{{ $activity->reference }}</a>
                                                        @elseif($activity->type == 'Invoice')
                                                            <a href="{{ route('purchase-invoice.show', $activity->reference_id) }}" target="_blank">{{ $activity->reference }}</a>
                                                        @elseif($activity->type == 'Payment' || $activity->type == 'Advance')
                                                            <a href="{{ route('payments-module.edit', $activity->reference_id) }}" target="_blank">{{ $activity->reference }}</a>
                                                        @else
                                                            {{ $activity->reference ?? '-' }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $activity->supplier_name ?? '-' }}</td>
                                                    <td class="text-right" style="text-align: right;">{{ currency_format_with_sym_indian($activity->reference_amount ?? 0) }}</td>
                                                    <td>{{ $activity->site_name ?? '-' }}</td>
                                                    <td class="text-right" style="text-align: right;">
                                                        @if($activity->type == 'Invoice' && (float)($activity->debit ?? 0) > 0)
                                                            {{ currency_format_with_sym_indian($activity->debit) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-right" style="text-align: right;">
                                                        @if(($activity->type == 'Payment' || $activity->type == 'Advance') && (float)($activity->credit ?? 0) > 0)
                                                            {{ currency_format_with_sym_indian($activity->credit) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-right {{ $activity->balance < 0 ? 'text-danger' : '' }}" style="text-align: right;">
                                                        @if(!is_null($activity->balance))
                                                            {{ currency_format_with_sym_indian($activity->balance) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>{{ $activity->description ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center">{{ __('No activities found') }}</td>
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
    
    <input type="hidden" id="filename" value="Supplier-Activity-Report-{{ date('Y-m-d') }}">
@endsection
