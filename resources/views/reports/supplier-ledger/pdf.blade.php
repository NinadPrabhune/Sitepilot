<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Supplier Ledger Report') }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            text-transform: uppercase;
            color: #000;
        }
        .filters-info {
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f9f9f9;
        }
        .filters-info table {
            width: 100%;
        }
        .filters-info td {
            padding: 2px 5px;
        }
        .summary-boxes {
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-box {
            width: 24%;
            display: inline-block;
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            background: #fff;
        }
        .summary-box h4 {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #000;
        }
        .summary-box p {
            margin: 0;
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        table.ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.ledger-table th {
            background: #eee;
            border: 1px solid #ddd;
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
        }
        table.ledger-table td {
            border: 1px solid #ddd;
            padding: 8px 5px;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-danger {
            color: #dc3545;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #999;
        }
        .badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            color: #fff;
        }
        .bg-primary { background: #0d6efd; }
        .bg-secondary { background: #6c757d; }
        .bg-info { background: #0dcaf0; color: #000; }
        .bg-success { background: #198754; }
        .bg-warning { background: #ffc107; color: #000; }
        .bg-dark { background: #212529; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ __('Supplier Ledger Report') }}</h2>
        <p>{{ date('d M Y H:i A') }}</p>
    </div>

    <div class="filters-info">
        <table>
            <tr>
                <td><strong>{{ __('Supplier') }}:</strong> {{ $filters['supplier_id'] === 'all' ? __('All Suppliers') : ($transactions->first()->supplier->name ?? __('N/A')) }}</td>
                <td><strong>{{ __('Site') }}:</strong> {{ $filters['site_id'] === 'all' ? __('All Sites') : ($transactions->first()->site->name ?? __('N/A')) }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('From Date') }}:</strong> {{ \Carbon\Carbon::parse($filters['from_date'])->format('d M Y') }}</td>
                <td><strong>{{ __('To Date') }}:</strong> {{ \Carbon\Carbon::parse($filters['to_date'])->format('d M Y') }}</td>
            </tr>
        </table>
    </div>

    <div class="summary-boxes">
        <div class="summary-box">
            <p>{{ __('Total PO') }}</p>
            <h4>{{ currency_format_with_sym_indian($summary['total_po']) }}</h4>
        </div>
        <div class="summary-box">
            <p>{{ __('Total Invoice') }}</p>
            <h4>{{ currency_format_with_sym_indian($summary['total_invoice']) }}</h4>
        </div>
        <div class="summary-box">
            <p>{{ __('Total Payments') }}</p>
            <h4>{{ currency_format_with_sym_indian($summary['total_payments']) }}</h4>
        </div>
        <div class="summary-box" style="border-left: 2px solid #000;">
            <p>{{ __('Current Balance') }}</p>
            <h4>{{ currency_format_with_sym_indian($summary['current_balance']) }}</h4>
        </div>
    </div>

    <table class="ledger-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Supplier') }}</th>
                <th class="text-right">{{ __('Ref. Amount') }}</th>
                <th>{{ __('Site') }}</th>
                <th class="text-right">{{ __('Debit') }}</th>
                <th class="text-right">{{ __('Credit') }}</th>
                <th class="text-right">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                @php
                    $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
                    $isNonAccounting = !empty($meta['non_accounting']);
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y') }}</td>
                    <td>{{ ucfirst($transaction->reference_type) }}</td>
                    <td>{{ $transaction->reference_number }}</td>
                    <td>{{ $transaction->supplier->name ?? '-' }}</td>
                    <td class="text-right">{{ currency_format_with_sym_indian($transaction->reference_amount ?? 0) }}</td>
                    <td>{{ $transaction->site->name ?? '-' }}</td>
                    <td class="text-right">
                        @if(!$isNonAccounting && (float)($transaction->debit ?? 0) > 0)
                            {{ currency_format_with_sym_indian($transaction->debit) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if(!$isNonAccounting && (float)($transaction->credit ?? 0) > 0)
                            {{ currency_format_with_sym_indian($transaction->credit) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right {{ $transaction->balance < 0 ? 'text-danger' : '' }}">
                        @if(!$isNonAccounting)
                            {{ currency_format_with_sym_indian($transaction->balance) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">{{ __('No transactions found') }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #eee; font-weight: bold;">
                <td colspan="6" class="text-right">{{ __('Total') }}</td>
                <td class="text-right">{{ currency_format_with_sym_indian($transactions->sum('debit')) }}</td>
                <td class="text-right">{{ currency_format_with_sym_indian($transactions->sum('credit')) }}</td>
                <td class="text-right">{{ currency_format_with_sym_indian($summary['current_balance']) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>{{ __('Generated by SitePilot') }}</p>
    </div>
</body>
</html>
