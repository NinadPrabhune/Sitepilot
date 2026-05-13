<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Goods Receipt Note - {{ $grn->grn_number ?? '' }}</title>
    @php
    if (!isset($workspaceDetails) || !$workspaceDetails) {
        $workspaceDetails = $grn->workspace ?? null;
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
    $isDirectGrn = $grn->isDirectGrn();
    @endphp
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
        .items-section,
        .summary-section,
        .signature-section {
            page-break-inside: avoid;
        }

        .items-table tr {
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

        .items-section {
            margin-bottom: 10px;
        }

        .items-table {
            border: 1px solid #000;
        }

        .items-table th {
            background: #f5f5f5;
            border: 1px solid #000;
            padding: 2px;
            font-size: 6px;
            text-align: center;
            font-weight: 600;
        }

        .items-table td {
            border: 1px solid #000;
            padding: 2px;
            font-size: 6px;
            text-align: center;
        }

        .items-table .text-start {
            text-align: left;
        }

        .items-table .text-end {
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
        }

        .summary-left {
            width: 60%;
            border-right: 1px solid #000;
        }

        .summary-right {
            width: 40%;
        }

        .summary-table th,
        .summary-table td {
            padding: 2px 3px;
            font-size: 7px;
            border-bottom: 1px solid #ddd;
            text-align: right;
        }

        .summary-table th {
            font-weight: 600;
            background: #f9f9f9;
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
                        <strong>GRN No:</strong> {{ $grn->grn_number }}<br>
                        <strong>Date:</strong> {{ \Carbon\Carbon::parse($grn->grn_date)->format('d-m-Y') }}<br>
                        <strong>Type:</strong> {{ $isDirectGrn ? 'Direct' : 'Against PO' }}<br>
                        <strong>Status:</strong> {{ $grn->status ?? '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">GOODS RECEIPT NOTE</div>

    <div class="party-section">
        <table class="party-grid">
            <tr>
                <td>
                    <div class="party-header">SUPPLIER</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Name:</td><td class="info-value"><strong>{{ $grn->supplier->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if(!empty($grn->supplier->address))
                        <table class="info-row">
                            <tr><td class="info-label">Addr:</td><td class="info-value">{{ $grn->supplier->address }}</td></tr>
                        </table>
                        @endif
                        @if(!empty($grn->supplier->gst_number))
                        <table class="info-row">
                            <tr><td class="info-label">GSTIN:</td><td class="info-value">{{ $grn->supplier->gst_number }}</td></tr>
                        </table>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="party-header">SITE / PROJECT</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Site:</td><td class="info-value"><strong>{{ $grn->site->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if(!$isDirectGrn && !empty($grn->purchaseOrder))
                        <table class="info-row">
                            <tr><td class="info-label">PO No:</td><td class="info-value">{{ $grn->purchaseOrder->po_number }}</td></tr>
                        </table>
                        @endif
                        @if($isDirectGrn && !empty($grn->supplier_invoice_number))
                        <table class="info-row">
                            <tr><td class="info-label">Inv No:</td><td class="info-value">{{ $grn->supplier_invoice_number }}</td></tr>
                        </table>
                        @endif
                        @if(!empty($grn->delivery_challan_number))
                        <table class="info-row">
                            <tr><td class="info-label">Challan:</td><td class="info-value">{{ $grn->delivery_challan_number }}</td></tr>
                        </table>
                        @endif
                        @if(!empty($grn->vehicle_number))
                        <table class="info-row">
                            <tr><td class="info-label">Vehicle:</td><td class="info-value">{{ $grn->vehicle_number }}</td></tr>
                        </table>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="items-section">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:3%;">#</th>
                    <th style="width:28%;">Material</th>
                    <th style="width:10%;">PO Qty</th>
                    <th style="width:10%;">Recd</th>
                    <th style="width:10%;">Accpt</th>
                    <th style="width:10%;">Rej</th>
                    <th style="width:6%;">Unit</th>
                    @if($isDirectGrn)
                    <th style="width:10%;">Price</th>
                    <th style="width:8%;">Tax</th>
                    <th style="width:10%;">Subtotal</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php $srNo = 1; @endphp
                @forelse($grn->items as $item)
                <tr>
                    <td>{{ $srNo++ }}</td>
                    <td class="text-start">{{ $item->material->name ?? 'N/A' }}</td>
                    <td>{{ number_format($item->ordered_qty, 2) }}</td>
                    <td>{{ number_format($item->received_qty, 2) }}</td>
                    <td>{{ number_format($item->accepted_qty, 2) }}</td>
                    <td>{{ number_format($item->rejected_qty, 2) }}</td>
                    <td>{{ $item->material->unit->name ?? 'PCS' }}</td>
                    @if($isDirectGrn)
                    <td class="text-end">{{ number_format($item->price, 2) }}</td>
                    <td class="text-end">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($item->subtotal, 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $isDirectGrn ? 10 : 7 }}" class="text-center">No items</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="summary-section">
        <table class="summary-grid">
            <tr>
                <td class="summary-left">
                    <div class="party-header">Remarks</div>
                    <div style="padding: 3px 4px; font-size: 7px;">
                        @if(!empty($grn->remarks)){{ $grn->remarks }}
                        @elseif(!empty($grn->description)){{ $grn->description }}
                        @else-
                        @endif
                    </div>
                </td>
                <td class="summary-right">
                    <table class="summary-table">
                        <tr><th>Recd:</th><td>{{ number_format($grn->items->sum('received_qty'), 2) }}</td></tr>
                        <tr><th>Accpt:</th><td>{{ number_format($grn->items->sum('accepted_qty'), 2) }}</td></tr>
                        <tr><th>Rej:</th><td>{{ number_format($grn->items->sum('rejected_qty'), 2) }}</td></tr>
                        @if($isDirectGrn)
                        <tr><th>Taxable:</th><td>{{ number_format($grn->total_taxable_value, 2) }}</td></tr>
                        @if($grn->tax_type === 'igst')
                        <tr><th>IGST:</th><td>{{ number_format($grn->total_igst, 2) }}</td></tr>
                        @else
                        <tr><th>CGST:</th><td>{{ number_format($grn->total_cgst, 2) }}</td></tr>
                        <tr><th>SGST:</th><td>{{ number_format($grn->total_sgst, 2) }}</td></tr>
                        @endif
                        <tr><th>Tax:</th><td>{{ number_format($grn->total_tax, 2) }}</td></tr>
                        <tr style="font-weight:bold;background:#f0f0f0"><th>Total:</th><td>{{ number_format($grn->total_amount, 2) }}</td></tr>
                        @endif
                    </table>
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