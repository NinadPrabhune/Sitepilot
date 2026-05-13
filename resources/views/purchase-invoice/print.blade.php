<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Invoice - {{ $purchaseInvoice->invoice_number }}</title>
    @php
    if (!isset($workspaceDetails) || !$workspaceDetails) {
        $workspaceDetails = $purchaseInvoice->workspace ?? null;
    }
    if (!$workspaceDetails) {
        $fallbackSettings = [];
        $keys = ['company_name', 'company_email', 'company_address', 'company_city', 'company_state', 'company_country', 'company_zipcode', 'company_telephone', 'company_logo', 'company_gst', 'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code'];
        foreach ($keys as $key) {
            $fallbackSettings[$key] = company_setting($key);
        }
    }
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

        /* Main content wrapper with inner padding */
        .main-wrapper {
            padding: 6mm;
        }

        img {
            max-width: 100px;
            max-height: 40px;
        }

        /* STRICT TABLE CONTROL - no expansion */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            word-break: break-word;
            overflow-wrap: break-word;
            font-size: 8px;
        }

        /* Page break control - prevent orphan rows */
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

        /* Header Section */
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

        /* Party Section */
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

        /* Items Table - EXACT column widths summing to 100% */
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

        /* Summary Section */
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

        .grand-total {
            background: #333 !important;
            color: #fff !important;
            font-weight: bold;
        }

        .grand-total th,
        .grand-total td {
            background: #333 !important;
            color: #fff !important;
        }

        .bank-header {
            background: #f5f5f5;
            padding: 3px 4px;
            font-weight: bold;
            font-size: 8px;
            border-bottom: 1px solid #000;
        }

        .bank-content {
            padding: 3px 4px;
            font-size: 7px;
            line-height: 1.3;
        }

        /* Signature */
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

        /* Footer */
        .footer {
            text-align: center;
            font-size: 7px;
            color: #666;
            padding: 8px 0;
            border-top: 1px solid #ddd;
            margin-top: 12px;
        }

        /* Safety - prevent last element from pushing */
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
                        <strong>Inv No:</strong> {{ $purchaseInvoice->invoice_number }}<br>
                        <strong>Date:</strong> {{ \Carbon\Carbon::parse($purchaseInvoice->invoice_date)->format('d-m-Y') }}<br>
                        @if($purchaseInvoice->supplier_invoice_number)
                        <strong>Sup Inv:</strong> {{ $purchaseInvoice->supplier_invoice_number }}<br>
                        @endif
                        <strong>Status:</strong> {{ $purchaseInvoice->status ?? '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">PURCHASE INVOICE</div>

    <div class="party-section">
        <table class="party-grid">
            <tr>
                <td>
                    <div class="party-header">SUPPLIER</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Name:</td><td class="info-value"><strong>{{ $purchaseInvoice->supplier->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if(!empty($purchaseInvoice->supplier->address))
                        <table class="info-row">
                            <tr><td class="info-label">Addr:</td><td class="info-value">{{ $purchaseInvoice->supplier->address }}</td></tr>
                        </table>
                        @endif
                        @if(!empty($purchaseInvoice->supplier->gst_number))
                        <table class="info-row">
                            <tr><td class="info-label">GSTIN:</td><td class="info-value">{{ $purchaseInvoice->supplier->gst_number }}</td></tr>
                        </table>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="party-header">SITE / PROJECT</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Site:</td><td class="info-value"><strong>{{ $purchaseInvoice->site->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if(!empty($purchaseInvoice->purchaseOrder))
                        <table class="info-row">
                            <tr><td class="info-label">PO No:</td><td class="info-value">{{ $purchaseInvoice->purchaseOrder->po_number }}</td></tr>
                        </table>
                        @endif
                        @if(!empty($purchaseInvoice->grn))
                        <table class="info-row">
                            <tr><td class="info-label">GRN:</td><td class="info-value">{{ $purchaseInvoice->grn->grn_number }}</td></tr>
                        </table>
                        @endif
                        <table class="info-row">
                            <tr><td class="info-label">Tax:</td><td class="info-value">{{ strtoupper($purchaseInvoice->tax_type ?? 'CGST') }}</td></tr>
                        </table>
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
                    <th style="width:23%;">Material</th>
                    <th style="width:8%;">Qty</th>
                    <th style="width:5%;">Unit</th>
                    <th style="width:9%;">Rate</th>
                    <th style="width:9%;">Disc</th>
                    <th style="width:11%;">Taxable</th>
                    <th style="width:6%;">GST%</th>
                    <th style="width:9%;">Tax</th>
                    <th style="width:12%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $srNo = 1; @endphp
                @forelse($purchaseInvoice->items as $item)
                <tr>
                    <td>{{ $srNo++ }}</td>
                    <td class="text-start">{{ $item->material->name ?? 'N/A' }}</td>
                    <td>{{ \App\Helpers\NumberHelper::formatIndian($item->quantity, 2) }}</td>
                    <td>{{ $item->unit ?? 'PCS' }}</td>
                    <td class="text-end">{{ \App\Helpers\NumberHelper::formatIndian($item->price, 2) }}</td>
                    <td class="text-end">{{ \App\Helpers\NumberHelper::formatIndian($item->discount_amount ?? 0, 2) }}</td>
                    <td class="text-end">{{ \App\Helpers\NumberHelper::formatIndian(($item->quantity * $item->price) - ($item->discount_amount ?? 0), 2) }}</td>
                    <td class="text-end">
                        @if($purchaseInvoice->tax_type === 'igst')
                            {{ \App\Helpers\NumberHelper::formatIndian($item->gstMaster?->igst ?? 0, 2) }}%
                        @else
                            {{ \App\Helpers\NumberHelper::formatIndian(($item->gstMaster?->cgst ?? 0) + ($item->gstMaster?->sgst ?? 0), 2) }}%
                        @endif
                    </td>
                    <td class="text-end">{{ \App\Helpers\NumberHelper::formatIndian($item->tax_amount ?? 0, 2) }}</td>
                    <td class="text-end">{{ \App\Helpers\NumberHelper::formatIndian($item->subtotal, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center">No items</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="summary-section">
        <table class="summary-grid">
            <tr>
                <td class="summary-left">
                    <div class="bank-header">Bank Details</div>
                    <div class="bank-content">
                        @if($workspaceDetails)
                            @if(!empty($workspaceDetails->bank_name))
                            <table class="info-row"><tr><td class="info-label">Bank:</td><td class="info-value">{{ $workspaceDetails->bank_name }}</td></tr></table>
                            @endif
                            @if(!empty($workspaceDetails->account_number))
                            <table class="info-row"><tr><td class="info-label">A/C:</td><td class="info-value">{{ $workspaceDetails->account_number }}</td></tr></table>
                            @endif
                            @if(!empty($workspaceDetails->ifsc_code))
                            <table class="info-row"><tr><td class="info-label">IFSC:</td><td class="info-value">{{ $workspaceDetails->ifsc_code }}</td></tr></table>
                            @endif
                            @if(empty($workspaceDetails->bank_name) && empty($workspaceDetails->account_number))-@endif
                        @else
                            @if(!empty($fallbackSettings['bank_name'] ?? null))
                            <table class="info-row"><tr><td class="info-label">Bank:</td><td class="info-value">{{ $fallbackSettings['bank_name'] }}</td></tr></table>
                            @endif
                            @if(!empty($fallbackSettings['bank_account_no'] ?? null))
                            <table class="info-row"><tr><td class="info-label">A/C:</td><td class="info-value">{{ $fallbackSettings['bank_account_no'] }}</td></tr></table>
                            @endif
                            @if(!empty($fallbackSettings['bank_ifsc_code'] ?? null))
                            <table class="info-row"><tr><td class="info-label">IFSC:</td><td class="info-value">{{ $fallbackSettings['bank_ifsc_code'] }}</td></tr></table>
                            @endif
                            @if(empty($fallbackSettings['bank_name'] ?? null) && empty($fallbackSettings['bank_account_no'] ?? null))-@endif
                        @endif
                    </div>
                </td>
                <td class="summary-right">
                    <table class="summary-table">
                        <tr><th>Taxable:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->total_taxable_value ?? 0, 2) }}</td></tr>
                        @if($purchaseInvoice->tax_type === 'igst')
                        <tr><th>IGST:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->total_igst ?? 0, 2) }}</td></tr>
                        @else
                        <tr><th>CGST:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->total_cgst ?? 0, 2) }}</td></tr>
                        <tr><th>SGST:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->total_sgst ?? 0, 2) }}</td></tr>
                        @endif
                        <tr><th>Disc:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->total_discount ?? 0, 2) }}</td></tr>
                        <tr><th>Tax:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->total_tax ?? 0, 2) }}</td></tr>
                        <tr class="grand-total"><th>Total:</th><td>{{ \App\Helpers\NumberHelper::formatIndian($purchaseInvoice->grand_total ?? $purchaseInvoice->total_amount, 2) }}</td></tr>
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