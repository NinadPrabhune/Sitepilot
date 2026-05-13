<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Voucher - {{ $payment->payment_number ?? '' }}</title>
    @php
    if (!isset($workspaceDetails) || !$workspaceDetails) {
        $workspaceDetails = $payment->workspace ?? null;
    }
    if (!$workspaceDetails) {
        $workspaceDetails = getActiveWorkSpace();
    }
    if (!$workspaceDetails) {
        $fallbackSettings = [];
        $keys = ['company_name', 'company_email', 'company_address', 'company_city', 'company_state', 'company_country', 'company_zipcode', 'company_telephone', 'company_logo', 'company_gst'];
        foreach ($keys as $key) {
            $fallbackSettings[$key] = company_setting($key);
        }
    }
    @endphp
    @if(isset($isPdf))
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; }
    </style>
    @endif
    <style>
        @page {
            size: A4;
            margin: 12mm 10mm 12mm 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        html, body {
            width: 100%;
            background: #fff;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 8px;
            line-height: 1.3;
            color: #333;
            padding: 0;
            margin: 0;
        }

        .main-wrapper {
            padding: 6mm;
        }

        img {
            max-width: 100px;
            max-height: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            word-break: break-word;
            overflow-wrap: break-word;
            font-size: 8px;
        }

        .header,
        .party-section,
        .details-section,
        .summary-section,
        .signature-section {
            page-break-inside: avoid;
        }

        .header {
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 10px;
        }

        .header-table td {
            vertical-align: top;
            padding: 2px;
        }

        .company-logo {
            width: 80px;
            max-width: 80px;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            color: #000;
            margin-bottom: 2px;
        }

        .company-details {
            font-size: 7px;
            line-height: 1.1;
        }

        .doc-title {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            background: #f5f5f5;
            padding: 5px;
            border: 1px solid #000;
            margin-bottom: 10px;
        }

        .party-section {
            margin-bottom: 10px;
        }

        .party-grid {
            border: 1px solid #000;
        }

        .party-grid td {
            width: 50%;
            vertical-align: top;
            border-right: 1px solid #000;
        }

        .party-grid td:last-child {
            border-right: none;
        }

        .party-header {
            background: #f5f5f5;
            padding: 3px 4px;
            font-weight: bold;
            font-size: 8px;
            border-bottom: 1px solid #000;
        }

        .party-content {
            padding: 3px 4px;
            font-size: 7px;
            line-height: 1.2;
        }

        .info-row td {
            padding: 1px 0;
        }

        .info-label {
            font-weight: 600;
            width: 55px;
            white-space: nowrap;
        }

        .info-value {
            word-break: break-word;
        }

        .details-section {
            margin-bottom: 10px;
        }

        .details-table {
            border: 1px solid #000;
        }

        .details-table th {
            background: #f5f5f5;
            border: 1px solid #000;
            padding: 2px;
            font-size: 6px;
            text-align: center;
            font-weight: 600;
        }

        .details-table td {
            border: 1px solid #000;
            padding: 2px;
            font-size: 7px;
        }

        .details-table .text-start {
            text-align: left;
        }

        .details-table .text-end {
            text-align: right;
        }

        .summary-section {
            margin-bottom: 10px;
        }

        .summary-grid {
            border: 1px solid #000;
        }

        .summary-grid td {
            vertical-align: top;
            padding: 3px 4px;
            font-size: 7px;
        }

        .signature-section {
            margin-top: 18px;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            padding: 5px 2px;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 2px;
            font-size: 7px;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            font-size: 7px;
            color: #666;
            padding: 8px 0;
            border-top: 1px solid #ddd;
            margin-top: 12px;
        }

        :last-child {
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 15%;">
                    @if(!empty($workspaceDetails->logo))
                        <img src="{{ get_file($workspaceDetails->logo) }}" alt="Logo" class="company-logo">
                    @else
                        <img src="{{ get_file(sidebar_logo()) }}" alt="Logo" class="company-logo">
                    @endif
                </td>
                <td style="width: 55%;">
                    @if($workspaceDetails)
                    <div class="company-name">{{ $workspaceDetails->name ?: 'Company Name' }}</div>
                    <div class="company-details">
                        @if(!empty($workspaceDetails->address)){{ $workspaceDetails->address }}@endif
                        @if(!empty($workspaceDetails->city)), {{ $workspaceDetails->city }}@endif
                        @if(!empty($workspaceDetails->state)) {{ $workspaceDetails->state }}@endif
                        @if(!empty($workspaceDetails->pincode)) - {{ $workspaceDetails->pincode }}@endif
                        @if(!empty($workspaceDetails->country))<br>{{ $workspaceDetails->country }}@endif
                        @if(!empty($workspaceDetails->gst_number))<br><strong>GSTIN:</strong> {{ $workspaceDetails->gst_number }}@endif
                    </div>
                    @else
                    <div class="company-name">{{ $fallbackSettings['company_name'] ?? 'Company Name' }}</div>
                    <div class="company-details">
                        @if(!empty($fallbackSettings['company_address'] ?? null)){{ $fallbackSettings['company_address'] }}@endif
                        @if(!empty($fallbackSettings['company_city'] ?? null)), {{ $fallbackSettings['company_city'] }}@endif
                        @if(!empty($fallbackSettings['company_state'] ?? null)) {{ $fallbackSettings['company_state'] }}@endif
                        @if(!empty($fallbackSettings['company_zipcode'] ?? null)) - {{ $fallbackSettings['company_zipcode'] }}@endif
                        @if(!empty($fallbackSettings['company_country'] ?? null))<br>{{ $fallbackSettings['company_country'] }}@endif
                        @if(!empty($fallbackSettings['company_gst'] ?? null))<br><strong>GSTIN:</strong> {{ $fallbackSettings['company_gst'] }}@endif
                    </div>
                    @endif
                </td>
                <td style="width: 30%;">
                    <div style="text-align: right; font-size: 7px; line-height: 1.3;">
                        <strong>Payment No:</strong> {{ $payment->payment_number }}<br>
                        <strong>Date:</strong> {{ \Carbon\Carbon::parse($payment->payment_date)->format('d-m-Y') }}<br>
                        <strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}<br>
                        <strong>Status:</strong> {{ ucfirst($payment->status) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">PAYMENT VOUCHER</div>

    <div class="party-section">
        <table class="party-grid">
            <tr>
                <td>
                    <div class="party-header">SUPPLIER</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Name:</td><td class="info-value"><strong>{{ $payment->supplier->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if(!empty($payment->supplier->address))
                        <table class="info-row">
                            <tr><td class="info-label">Addr:</td><td class="info-value">{{ $payment->supplier->address }}</td></tr>
                        </table>
                        @endif
                        @if(!empty($payment->supplier->gst_number))
                        <table class="info-row">
                            <tr><td class="info-label">GSTIN:</td><td class="info-value">{{ $payment->supplier->gst_number }}</td></tr>
                        </table>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="party-header">SITE / PROJECT</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Site:</td><td class="info-value"><strong>{{ $payment->site->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if($payment->payment_type === 'against_po' && !empty($payment->purchaseOrder))
                        <table class="info-row">
                            <tr><td class="info-label">PO No:</td><td class="info-value">{{ $payment->purchaseOrder->po_number }}</td></tr>
                        </table>
                        @endif
                        @if($payment->payment_type === 'against_invoice' && !empty($payment->invoice))
                        <table class="info-row">
                            <tr><td class="info-label">Inv No:</td><td class="info-value">{{ $payment->invoice->invoice_number }}</td></tr>
                        </table>
                        @endif
                        @if($payment->payment_type === 'advance_against_po' && !empty($payment->purchaseOrder))
                        <table class="info-row">
                            <tr><td class="info-label">PO No:</td><td class="info-value">{{ $payment->purchaseOrder->po_number }}</td></tr>
                        </table>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="details-section">
        <table class="details-table">
            <thead>
                <tr>
                    <th style="width:30%;">Field</th>
                    <th style="width:70%;">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-start"><strong>Paid Amount</strong></td>
                    <td class="text-end"><strong>₹{{ format_indian_currency($payment->amount) }}</strong></td>
                </tr>
                <tr>
                    <td class="text-start">Payment Method</td>
                    <td class="text-start">{{ $payment->mode ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-start">Payment Type</td>
                    <td class="text-start">{{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}</td>
                </tr>
                <tr>
                    <td class="text-start">Reference Number</td>
                    <td class="text-start">{{ $payment->reference_number ?? '-' }}</td>
                </tr>
                @if($payment->payment_type === 'against_po' && !empty($payment->purchaseOrder))
                <tr>
                    <td class="text-start">Purchase Order</td>
                    <td class="text-start">{{ $payment->purchaseOrder->po_number }} (₹{{ format_indian_currency($payment->purchaseOrder->grand_total) }})</td>
                </tr>
                @endif
                @if($payment->payment_type === 'against_invoice' && !empty($payment->invoice))
                <tr>
                    <td class="text-start">Invoice Reference</td>
                    <td class="text-start">{{ $payment->invoice->invoice_number }} (₹{{ format_indian_currency($payment->invoice->total_amount) }})</td>
                </tr>
                @endif
                @if($payment->payment_type === 'advance_against_po' && !empty($payment->purchaseOrder))
                <tr>
                    <td class="text-start">Advance Against PO</td>
                    <td class="text-start">{{ $payment->purchaseOrder->po_number }} (₹{{ format_indian_currency($payment->purchaseOrder->grand_total) }})</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="summary-section">
        <table class="summary-grid">
            <tr>
                <td style="width: 60%;">
                    <div class="party-header">Notes</div>
                    <div style="padding: 3px 4px; font-size: 7px;">
                        @if(!empty($payment->notes)){{ $payment->notes }}
                        @else-
                        @endif
                    </div>
                </td>
                <td style="width: 40%;">
                    <div class="party-header">Payment Proof</div>
                    <div style="padding: 3px 4px; font-size: 7px;">
                        @if(!empty($payment->payment_proff_file))
                            <a href="{{ asset($payment->payment_proff_file) }}" target="_blank">View Proof</a>
                        @else
                            N/A
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td><div class="signature-line">Prepared By</div></td>
                <td><div class="signature-line">Checked By</div></td>
                <td><div class="signature-line">Authorized Signatory</div></td>
            </tr>
        </table>
    </div>

    <div class="footer">Computer generated document. No signature required.</div>
    </div>
</body>
</html>
