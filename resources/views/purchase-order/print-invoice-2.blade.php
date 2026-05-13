<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $purchaseOrder->po_number }}</title>
    @php
        $projectDetails = $projectDetails ?? null;

        // Indian number format function (grouping as 3,2,2 from right)
        if (!function_exists('formatIndianNumber')) {
        function formatIndianNumber($number, $decimals = 2) {
        $number = floatval($number);
        $isNegative = $number < 0;
        $number = abs($number);

        // Split integer and decimal parts
        $parts = explode('.', number_format($number, $decimals, '.', ''));
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? $parts[1] : str_repeat('0', $decimals);

        // Format integer part with Indian grouping (3,2,2 from right)
        $lastThree = substr($integerPart, -3);
        $remaining = substr($integerPart, 0, -3);

        $formatted = '';
        if (!empty($remaining)) {
        // Split remaining into groups of 2 from right
        $groups = array();
        while (strlen($remaining) > 0) {
        if (strlen($remaining) >= 2) {
        $groups[] = substr($remaining, -2);
        $remaining = substr($remaining, 0, -2);
        } else {
        $groups[] = $remaining;
        $remaining = '';
        }
        }
        $groups = array_reverse($groups);
        $formatted = implode(',', $groups) . ',';
        }

        $formatted .= $lastThree;

        // Add decimal part
        if ($decimals > 0) {
        $formatted .= '.' . $decimalPart;
        }

        return $isNegative ? '-' . $formatted : $formatted;
        }
        }
    @endphp
    <style>
        @page {
            size: A4;
            margin: 6mm;
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

        img {
            max-width: 80px;
            max-height: 35px;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 8px;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            margin: 0 auto;
            padding: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        td, th {
            word-break: break-word;
            overflow-wrap: break-word;
            font-size: 7px;
        }

        .header,
        .items-table,
        .terms-table,
        .signature-table {
            page-break-inside: avoid;
        }

        .items-table tr {
            page-break-inside: avoid;
        }

        .footer {
            text-align: center;
            font-size: 6px;
            color: #666;
            padding: 5px 0;
            border-top: 1px solid #ddd;
            margin-top: 5px;
        }

        .header {
            border: 1px solid #000;
            padding: 5px;
            margin-bottom: 5px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            padding: 2px;
        }

        .company-logo {
            width: 80px;
            max-width: 80px;
        }

        .logo-placeholder {
            width: 80px;
            height: 35px;
            border: 1px solid #000;
            text-align: center;
            background: #f9f9f9;
            font-size: 10px;
            color: #666;
        }

        .po-title {
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            background: #f5f5f5;
            padding: 3px;
            border: 1px solid #000;
            margin-bottom: 5px;
        }

        .party-section {
            margin-bottom: 5px;
        }

        .party-grid {
            border: 1px solid #000;
            border-collapse: collapse;
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
            width: 50px;
            white-space: nowrap;
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
            margin-bottom: 5px;
        }

        .summary-grid {
            border: 1px solid #000;
            border-collapse: collapse;
        }

        .summary-grid td {
            padding: 0;
            vertical-align: top;
        }

        .summary-left {
            width: 62%;
            border-right: 1px solid #000;
        }

        .summary-right {
            width: 38%;
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

        .grand-total-row {
            background: #d9d9d9 !important;
            color: #333 !important;
            font-weight: bold;
        }

        .grand-total-row td {
            background: #d9d9d9 !important;
            color: #333 !important;
        }

        .terms-table {
            border: 1px solid #000;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .terms-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            font-size: 6px;
        }

        .terms-table td:first-child {
            font-weight: bold;
            background: #f5f5f5;
        }

        .signature-table td {
            height: 35px;
            text-align: center;
            font-size: 7px;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 2px;
            font-size: 7px;
            font-weight: 600;
        }

        .amount-in-words {
            background: #d9d9d9 !important;
            padding: 5px;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 5px;
        }

        :last-child {
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <table class="header">
            <tr>
                <td style="width: 18%;">
                    @if(!empty($workspaceDetails->logo))
                        <img src="{{ get_file($workspaceDetails->logo) }}" alt="Logo" class="company-logo">
                    @else
                        <div class="logo-placeholder">LOGO</div>
                    @endif
                </td>
                <td style="width: 52%;">
                    <div style="font-size: 10px; font-weight: bold; text-align: center; border-bottom: 1px solid #000; padding: 2px;">
                        {{ $settings['company_name'] ?? $workspaceDetails->name ?? 'Company Name' }}
                    </div>
                    <div style="font-size: 6px; text-align: center; padding: 2px;">
                        @if(!empty($settings['company_address'])){{ $settings['company_address'] }},@endif
                        @if(!empty($settings['company_city'])){{ $settings['company_city'] }},@endif
                        @if(!empty($settings['company_state'])){{ $settings['company_state'] }}@endif
                        @if(!empty($settings['company_zipcode'])) - {{ $settings['company_zipcode'] }}@endif
                        @if(!empty($workspaceDetails->gst_number))<br><strong>GSTIN:</strong> {{ $workspaceDetails->gst_number }}@endif
                    </div>
                </td>
                <td style="width: 30%;">
                    <div style="font-size: 7px; line-height: 1.3;">
                        <strong>PO No:</strong> {{ $purchaseOrder->po_number }}<br>
                        <strong>Date:</strong> @if(!empty($purchaseOrder->po_date)){{ \Carbon\Carbon::parse($purchaseOrder->po_date)->format('d-m-Y') }}@else-@endif<br>
                        <strong>Delivery:</strong> @if(!empty($purchaseOrder->delivery_date)){{ \Carbon\Carbon::parse($purchaseOrder->delivery_date)->format('d-m-Y') }}@else-@endif<br>
                        <strong>Status:</strong> {{ $purchaseOrder->status ?? 'Pending' }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="po-title">PURCHASE ORDER</div>

        <table class="party-grid" style="margin-bottom: 5px;">
            <tr>
                <td>
                    <div class="party-header">Vendor / Supplier</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Name:</td><td><strong>{{ $purchaseOrder->supplier->name ?? '-' }}</strong></td></tr>
                        </table>
                        @if(!empty($purchaseOrder->supplier->address))
                        <table class="info-row">
                            <tr><td class="info-label">Addr:</td><td>{{ $purchaseOrder->supplier->address }}</td></tr>
                        </table>
                        @endif
                        <table class="info-row">
                            <tr><td class="info-label">GSTIN:</td><td>{{ $purchaseOrder->supplier->gst_number ?? '-' }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="party-header">Order Reference</div>
                    <div class="party-content">
                        <table class="info-row">
                            <tr><td class="info-label">Indent:</td><td>{{ $purchaseOrder->indent->indent_number ?? '-' }}</td></tr>
                        </table>
                        <table class="info-row">
                            <tr><td class="info-label">Site:</td><td>{{ $purchaseOrder->site->name ?? $projectDetails->name ?? '-' }}</td></tr>
                        </table>
                        <table class="info-row">
                            <tr><td class="info-label">Tax:</td><td>{{ $purchaseOrder->tax_type == 'igst' ? 'IGST' : 'CGST/SGST' }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="party-grid" style="margin-bottom: 5px;">
            <tr>
                <td>
                    <div class="party-header">Billing Address</div>
                    <div class="party-content">
                        <strong>{{ $workspaceDetails->name ?? $settings['company_name'] ?? '-' }}</strong><br>
                        @if(!empty($workspaceDetails->address)){{ $workspaceDetails->address }}@endif
                        @if(!empty($workspaceDetails->city)), {{ $workspaceDetails->city }}@endif
                        @if(!empty($workspaceDetails->state)), {{ $workspaceDetails->state }}@endif
                        @if(!empty($workspaceDetails->pincode)) - {{ $workspaceDetails->pincode }}@endif
                    </div>
                </td>
                <td>
                    <div class="party-header">Delivery Address</div>
                    <div class="party-content">
                        @if(!empty($purchaseOrder->delivery_address)){{ $purchaseOrder->delivery_address }}
                        @else
                            <strong>{{ $workspaceDetails->name ?? $settings['company_name'] ?? '-' }}</strong><br>
                            @if(!empty($workspaceDetails->address)){{ $workspaceDetails->address }}@endif
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <table class="items-table" style="margin-bottom: 5px;">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th style="width:25%;">Description</th>
                    <th style="width:8%;">Qty</th>
                    <th style="width:5%;">UoM</th>
                    <th style="width:10%;">Rate</th>
                    <th style="width:9%;">Disc</th>
                    <th style="width:12%;">Taxable</th>
                    <th style="width:6%;">GST%</th>
                    <th style="width:10%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $srNo = 1; @endphp
                @forelse($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $srNo++ }}</td>
                    <td class="text-start">{{ $item->material->name ?? '-' }}</td>
                    <td>{{ formatIndianNumber($item->quantity ?? 0, 2) }}</td>
                    <td>{{ $item->unit ?? 'PCS' }}</td>
                    <td class="text-end">{{ formatIndianNumber($item->price ?? 0, 2) }}</td>
                    <td class="text-end">{{ formatIndianNumber($item->discount_amount ?? 0, 2) }}</td>
                    <td class="text-end">{{ formatIndianNumber($item->subtotal ?? 0, 2) }}</td>
                    <td>
                        @php
                        $gstPercent = 0;
                        if (!empty($item->gstMaster)) {
                            $gstPercent = $purchaseOrder->tax_type == 'igst' ? $item->gstMaster->igst : ($item->gstMaster->cgst + $item->gstMaster->sgst);
                        }
                        @endphp
                        {{ formatIndianNumber($gstPercent, 2) }}%
                    </td>
                    <td class="text-end">{{ formatIndianNumber(($item->subtotal ?? 0) + ($item->tax_amount ?? 0), 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center">No items</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="summary-grid">
            <tr>
                <td class="summary-left">
                    @if(!empty($totalInWords))
                    <div class="amount-in-words">Amount: {{ $totalInWords }} Only</div>
                    @endif
                    @if(!empty($purchaseOrder->remark))
                    <table class="terms-table">
                        <tr><td style="width:20%;">Remarks</td><td>{!! nl2br(e($purchaseOrder->remark)) !!}</td></tr>
                    </table>
                    @endif
                    <table class="terms-table">
                        <tr><td>Bank</td><td>{{ $workspaceDetails->bank_name ?? '-' }} | A/C: {{ $workspaceDetails->account_number ?? '-' }}</td></tr>
                    </table>
                </td>
                <td class="summary-right">
                    <table class="summary-table">
                        <tr><td>Taxable</td><td>{{ formatIndianNumber($purchaseOrder->total_taxable_value ?? 0, 2) }}</td></tr>
                        @if($purchaseOrder->tax_type == 'igst')
                        <tr><td>IGST</td><td>{{ formatIndianNumber($purchaseOrder->total_igst ?? 0, 2) }}</td></tr>
                        @else
                        <tr><td>CGST</td><td>{{ formatIndianNumber($purchaseOrder->total_cgst ?? 0, 2) }}</td></tr>
                        <tr><td>SGST</td><td>{{ formatIndianNumber($purchaseOrder->total_sgst ?? 0, 2) }}</td></tr>
                        @endif
                        <tr><td>Total Tax</td><td>{{ formatIndianNumber($purchaseOrder->total_tax ?? 0, 2) }}</td></tr>
                        @if(($purchaseOrder->additional_charge ?? 0) > 0)
                        <tr><td>Add. Chg</td><td>{{ formatIndianNumber($purchaseOrder->additional_charge, 2) }}</td></tr>
                        @endif
                        @if(($purchaseOrder->additional_deduction ?? 0) > 0)
                        <tr><td>Less Ded</td><td>{{ formatIndianNumber($purchaseOrder->additional_deduction, 2) }}</td></tr>
                        @endif
                        <tr class="grand-total-row"><td>Grand Total</td><td>{{ formatIndianNumber($purchaseOrder->grand_total ?? 0, 2) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        @if(!empty($purchaseOrder->payment_terms_conditions) || !empty($purchaseOrder->delivery_terms_conditions))
        <table class="terms-table" style="margin-top: 5px;">
            <tr>
                <td style="width:25%;">Payment</td>
                <td>{{ $purchaseOrder->payment_terms_conditions ?? '-' }}</td>
            </tr>
            <tr>
                <td>Delivery</td>
                <td>{{ $purchaseOrder->delivery_terms_conditions ?? '-' }}</td>
            </tr>
        </table>
        @endif

        <table class="signature-table" style="margin-top: 10px;">
            <tr>
                <td><div class="signature-line">Prepared By</div></td>
                <td><div class="signature-line">Checked By</div></td>
                <td><div class="signature-line">Authorized Signature</div></td>
            </tr>
        </table>

        <div class="footer">Computer generated. No signature required.</div>
    </div>
</body>
</html>