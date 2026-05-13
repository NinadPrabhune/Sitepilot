<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Invoice - {{ $purchaseOrder->po_number }}</title>
    @php
        $projectDetails = $projectDetails ?? null;
    @endphp
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }

        /* A4 Page Setup */
        @page {
            size: A4;
            margin: 15mm;
        }

        /* Print Container */
        .invoice-container {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }

        /* Invoice Header */
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .company-address {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }

        .company-gst {
            font-size: 11px;
            color: #666;
        }

        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Document Details */
        .doc-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .doc-info, .party-info {
            width: 48%;
        }

        .doc-info h4, .party-info h4 {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
        }

        .info-row {
            display: flex;
            margin-bottom: 4px;
        }

        .info-label {
            font-weight: bold;
            width: 110px;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 10px;
        }

        .items-table th {
            background: #f5f5f5;
            border: 1px solid #333;
            padding: 8px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }

        .items-table td {
            border: 1px solid #333;
            padding: 6px 4px;
            text-align: center;
            vertical-align: middle;
        }

        .items-table th:first-child,
        .items-table td:first-child {
            width: 40px;
        }

        .items-table .text-start {
            text-align: left;
        }

        .items-table .text-end {
            text-align: right;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        /* Summary Table */
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .summary-table {
            width: 280px;
            border-collapse: collapse;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #333;
            padding: 6px 10px;
            font-size: 10px;
        }

        .summary-table th {
            background: #f5f5f5;
            text-align: right;
            font-weight: bold;
        }

        .summary-table td {
            text-align: right;
        }

        .summary-table .total-row {
            background: #333;
            color: #fff;
            font-weight: bold;
            font-size: 12px;
        }

        .summary-table .total-row th,
        .summary-table .total-row td {
            border-color: #333;
        }

        /* Footer */
        .invoice-footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .footer-section {
            margin-bottom: 20px;
        }

        .footer-section h5 {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }

        .footer-section p {
            font-size: 10px;
            color: #666;
        }

        .signature-box {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .signature-field {
            width: 200px;
            text-align: center;
        }

        .signature-box .border-top {
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 10px;
            color: #666;
        }

        /* Print Button */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .btn-print {
            background: #333;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-print:hover {
            background: #555;
        }

        /* Print Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .invoice-container {
                padding: 0;
                margin: 0;
            }

            .items-table {
                page-break-inside: avoid;
            }

            .items-table tr {
                page-break-inside: avoid;
            }

            @page {
                margin: 10mm;
            }
        }

        /* Preview Styles (when not printing) */
        @media screen {
            body {
                background: #f0f0f0;
            }

            .invoice-container {
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button (hidden when printing) -->
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="ti ti-printer"></i> Print Invoice
        </button>
    </div>

    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-name">{{ $settings['company_name'] ?? '' }}</div>
            <div class="company-address">
                {{ $settings['company_address'] ?? '' }}
                @if(!empty($settings['company_city']))<br>{{ $settings['company_city'] }}, @endif
                {{ $settings['company_state'] ?? '' }}
                @if(!empty($settings['company_country']))<br>{{ $settings['company_country'] }}@endif
                @if(!empty($settings['company_zipcode'])) - {{ $settings['company_zipcode'] }}@endif
            </div>
            <div class="company-gst">GSTIN: {{ $settings['company_gst'] ?? '' }}</div>
            @if($projectDetails)
            <div class="company-gst" style="margin-top: 5px;">
                <strong>Project:</strong> {{ $projectDetails->name ?? '-' }}
                @if(!empty($projectDetails->project_code))
                    ({{ $projectDetails->project_code }})
                @endif
            </div>
            @endif
            <div class="invoice-title">Purchase Order</div>
        </div>

        <!-- Document & Party Details -->
        <div class="doc-details">
            <!-- Document Info -->
            <div class="doc-info">
                <h4>PO Details</h4>
                <div class="info-row">
                    <span class="info-label">PO Number:</span>
                    <span class="info-value">{{ $purchaseOrder->po_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">PO Date:</span>
                    <span class="info-value">{{ \Carbon\Carbon::parse($purchaseOrder->po_date)->format('d-m-Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Indent No:</span>
                    <span class="info-value">{{ $purchaseOrder->indent->indent_number ?? '-' }}</span>
                </div>
                @if($purchaseOrder->delivery_date)
                <div class="info-row">
                    <span class="info-label">Delivery Date:</span>
                    <span class="info-value">{{ \Carbon\Carbon::parse($purchaseOrder->delivery_date)->format('d-m-Y') }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">{{ $purchaseOrder->status }}</span>
                </div>
                @if($projectDetails)
                <div class="info-row">
                    <span class="info-label">Project:</span>
                    <span class="info-value">{{ $projectDetails->name ?? '-' }}</span>
                </div>
                @endif
            </div>

            <!-- Supplier Info -->
            <div class="party-info">
                <h4>Supplier Details</h4>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value">{{ $purchaseOrder->supplier->name ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value">{{ $purchaseOrder->supplier->address ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">GST Number:</span>
                    <span class="info-value">{{ $purchaseOrder->supplier->gst_number ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Site:</span>
                    <span class="info-value">{{ $purchaseOrder->site->name ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Sr No</th>
                    <th>Material Name</th>
                    <th>HSN/SAC</th>
                    <th>Indent Qty</th>
                    <th>Order Qty</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Disc.</th>
                    <th>Tax %</th>
                    <th>Tax Amt</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $srNo = 1; @endphp
                @forelse($purchaseOrder->items as $item)
                    @php
                        $indentItem = optional($purchaseOrder->indent)
                            ->items
                            ->where('material_id', $item->material_id)
                            ->first();
                        $indentQty = $indentItem->quantity ?? 0;
                        
                        $gstPercent = 0;
                        if ($item->gstMaster) {
                            $gstPercent = $purchaseOrder->tax_type == 'igst' 
                                ? $item->gstMaster->igst 
                                : ($item->gstMaster->cgst + $item->gstMaster->sgst);
                        }
                    @endphp
                    <tr>
                        <td>{{ $srNo++ }}</td>
                        <td class="text-start">{{ $item->material->name ?? '-' }}</td>
                        <td>{{ $item->material->hsn_code ?? '-' }}</td>
                        <td>{{ number_format($indentQty, 2) }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>{{ $item->unit ?? '-' }}</td>
                        <td class="text-end">{{ number_format($item->price, 2) }}</td>
                        <td class="text-end">{{ number_format($item->discount_amount ?? 0, 2) }}</td>
                        <td class="text-end">{{ number_format($gstPercent, 2) }}%</td>
                        <td class="text-end">{{ number_format($item->tax_amount ?? 0, 2) }}</td>
                        <td class="text-end">{{ number_format($item->subtotal ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center">No items found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Summary Section -->
        <div class="summary-section">
            <table class="summary-table">
                <tr>
                    <th>Total Taxable Value</th>
                    <td>{{ number_format($purchaseOrder->total_taxable_value ?? 0, 2) }}</td>
                </tr>
                @if($purchaseOrder->tax_type == 'igst')
                <tr>
                    <th>IGST</th>
                    <td>{{ number_format($purchaseOrder->total_igst ?? 0, 2) }}</td>
                </tr>
                @else
                <tr>
                    <th>CGST</th>
                    <td>{{ number_format($purchaseOrder->total_cgst ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <th>SGST</th>
                    <td>{{ number_format($purchaseOrder->total_sgst ?? 0, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <th>Total Tax</th>
                    <td>{{ number_format($purchaseOrder->total_tax ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <th>Total Discount</th>
                    <td>{{ number_format($purchaseOrder->total_discount ?? 0, 2) }}</td>
                </tr>
                @if(($purchaseOrder->additional_charge ?? 0) > 0)
                <tr>
                    <th>Additional Charge (+)</th>
                    <td>{{ number_format($purchaseOrder->additional_charge, 2) }}</td>
                </tr>
                @endif
                @if(($purchaseOrder->additional_deduction ?? 0) > 0)
                <tr>
                    <th>Additional Deduction (-)</th>
                    <td>{{ number_format($purchaseOrder->additional_deduction, 2) }}</td>
                </tr>
                @endif
                @if(($purchaseOrder->additional_discount ?? 0) > 0)
                <tr>
                    <th>Additional Discount (-)</th>
                    <td>{{ number_format($purchaseOrder->additional_discount, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <th>Grand Total</th>
                    <td>{{ number_format($purchaseOrder->grand_total ?? 0, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            @if($purchaseOrder->remark)
            <div class="footer-section">
                <h5>Remarks</h5>
                <p>{{ $purchaseOrder->remark }}</p>
            </div>
            @endif

            @if($purchaseOrder->delivery_terms_conditions)
            <div class="footer-section">
                <h5>Delivery Terms & Conditions</h5>
                <p>{{ $purchaseOrder->delivery_terms_conditions }}</p>
            </div>
            @endif

            <div class="signature-box">
                <div class="signature-field">
                    <div class="border-top">Prepared By</div>
                </div>
                <div class="signature-field">
                    <div class="border-top">Checked By</div>
                </div>
                <div class="signature-field">
                    <div class="border-top">Authorized Signature</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger print dialog when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
