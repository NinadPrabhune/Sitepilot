<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Purchase Order - {{ $purchaseOrder->po_number ?? '' }}</title>
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

        // Number to words conversion function (standalone) - check if already defined
        if (!function_exists('convertNumberToWords')) {
        function convertNumberToWords($number) {
        $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
        $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');

        $words = '';
        $number = round($number);

        if ($number == 0) {
        return 'Zero';
        }

        // Indian numbering system: groups from right as 3,2,2,2...
        // Pattern: XX,XX,XX,XXX (Crore, Lakh, Thousand, Hundreds)
        $numStr = (string)$number;
        $groups = array();

        // Take last 3 digits (hundreds)
        if (strlen($numStr) > 2) {
        $groups[] = substr($numStr, -3);
        $numStr = substr($numStr, 0, -3);
        } else {
        $groups[] = $numStr;
        $numStr = '';
        }

        // Take remaining in groups of 2
        while (strlen($numStr) > 0) {
        if (strlen($numStr) >= 2) {
        $groups[] = substr($numStr, -2);
        $numStr = substr($numStr, 0, -2);
        } else {
        $groups[] = $numStr;
        $numStr = '';
        }
        }

        $groups = array_reverse($groups);
        $powers = array('', 'Thousand', 'Lakh', 'Crore', 'Arab');

        for ($i = 0; $i < count($groups); $i++) {
        $part = intval($groups[$i]);

        if ($part > 0) {
        // Convert the 2 or 3 digit number to words
        if ($part < 20) {
        $words .= $ones[$part] . ' ';
        } else {
        if ($part >= 100) {
        $words .= $ones[intval($part / 100)] . ' Hundred ';
        $part = $part % 100;
        }
        if ($part > 0) {
        if ($part < 20) {
        $words .= $ones[$part] . ' ';
        } else {
        $words .= $tens[intval($part / 10)] . ' ';
        if ($part % 10 > 0) {
        $words .= $ones[$part % 10] . ' ';
        }
        }
        }
        }

        // Add power suffix (skip for hundreds group which is last in reversed array)
        $powerIndex = count($groups) - 1 - $i;
        if ($powerIndex > 0 && $powerIndex < count($powers)) {
        $words .= $powers[$powerIndex] . ' ';
        }
        }
        }

        return trim($words);
        }
        } // End if !function_exists

        $totalInWords = '';
        if (!empty($purchaseOrder->grand_total)) {
        $totalInWords = convertNumberToWords($purchaseOrder->grand_total);
        }
        @endphp
        <style>
@page {
                size: A4;
                margin: 12mm 10mm 12mm 10mm;
            }

            @page :first {
                margin-top: 12mm;
            }

            * {
                box-sizing: border-box;
                word-break: break-word;
                overflow-wrap: break-word;
            }

            html, body {
                width: 100%;
                background: #fff;
            }

            img {
                max-width: 100px;
                max-height: 40px;
            }

            body {
                font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
                font-size: 8px;
                line-height: 1.3;
                width: 100%;
            }

            .main-wrapper {
                padding: 6mm;
            }

            @page :first {
                margin-top: 6mm;
            }

            * {
                box-sizing: border-box;
                word-break: break-word;
                overflow-wrap: break-word;
            }

            html, body {
                width: 100%;
                background: #fff;
            }

            img {
                max-width: 100px;
                max-height: 40px;
            }

            body {
                font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
                font-size: 8px;
                margin: 0;
                padding: 0;
                color: #000;
                line-height: 1.2;
                width: 100%;
            }

            .container {
                width: 100%;
                margin: 0 auto;
                padding: 6mm;
            }

            h1, h2, h3, h4 {
                margin: 0;
                padding: 0;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: avoid;
            }

            table td, table th {
                border: 1px solid #000;
                padding: 2px 3px;
                vertical-align: top;
                font-size: 7px;
                word-break: break-word;
            }

            table th {
                background: #e5e5e5;
                font-weight: bold;
                font-size: 6px;
                text-transform: uppercase;
            }

            .header, .items-table, .summary-grid, .signature-section {
                page-break-inside: avoid;
            }

            .no-border td {
                border: none !important;
            }

            .no-border-table td {
                border: none !important;
            }

            .header-bar {
                background: #DAE4C1 !important;
                font-weight: bold;
                text-align: center;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;

                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .text-right {
                text-align: right;
            }

            .text-center {
                text-align: center;
            }

            .bold {
                font-weight: bold;
            }

            .small-text {
                font-size: 9px;
            }

            .company-logo {
                max-width: 80px;
                max-height: 35px;
            }

            .logo-placeholder {
                width: 80px;
                height: 35px;
                border: 1px solid #000;
                text-align: center;
                margin: 0 auto;
                background: #f9f9f9;
                font-size: 10px;
                color: #666;
                padding: 5px;
            }

            .section-title {
                font-weight: bold;
                text-align: center;
                margin-top: 12px;
                margin-bottom: 8px;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .terms-short td:first-child {
                width: 18%;
                font-weight: bold;
                background: #d9d9d9 !important;
                font-size: 9px;
            }

            .terms-table td:first-child {
                width: 22%;
                font-weight: bold;
                font-size: 9px;
            }

            .terms-table td:last-child {
                font-size: 9px;
                line-height: 1.4;
            }

            .signature-table td {
                height: 40px;
                text-align: center;
                vertical-align: bottom;
                font-weight: bold;
                font-size: 8px;
            }

            .signature-line {
                border-top: 1px solid #000;
                height: 30px;
                vertical-align: bottom;
            }

            .footer-note {
                text-align: center;
                font-size: 8px;
                border-top: 1px solid #000;
                padding: 5px;
                margin-top: 8px;
            }

            .tax-table td {
                text-align: right;
                font-size: 10px;
            }

            .tax-table td:first-child {
                text-align: left;
            }

            .items-table th {
                font-size: 8px;
                padding: 4px 6px;
                text-transform: uppercase;
            }

            .items-table td {
                font-size: 9px;
                padding: 4px 6px;
            }

            .po-title-bar {
                background-color: #d9d9d9 !important;
                color: #333;
                font-weight: bold;
                text-align: center;
                padding: 10px;
                font-size: 18px;
                border: 1px solid #000;
                text-transform: uppercase;
                letter-spacing: 2px;

                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }


            .address-box {
                min-height: 70px;
            }

            .company-info {
                font-size: 9px;
                line-height: 1.4;
            }

            .page-break {
                page-break-before: always;
            }

            .amount-in-words {
                background: #d9d9d9 !important;
                padding: 8px;
                border: 1px solid #000;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
                margin-top: 8px;
            }

            .grand-total-row {
                background: #d9d9d9 !important;
                color: #333 !important;
                font-weight: bold;
                font-size: 11px !important;
            }

            .grand-total-row td {
                background: #d9d9d9 !important;
                color: #333 !important;
            }

            /* Page number styling */
/*            .page-number {
                font-size: 10px;
                color: #333;
                font-weight: bold;
            }*/

            @media print {
                .no-print {
                    display: none !important;
                }

/*                .page-number {
                    display: block !important;
                    position: fixed;
                    bottom: 8mm;
                    right: 12mm;
                }*/

                body {
                    font-size: 10px;
                }

                .container {
                    padding: 0;
                }

                .page-break {
                    page-break-before: always;
                }

                table {
                    page-break-inside: avoid;
                }
                .header-bar {
                    background: #DAE4C1 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .po-title-bar {
                    background-color: #d9d9d9 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                @page {
                    margin: 12mm 15mm;
                    margin-bottom: 25mm;
                }
            }
            
            @media print {

                body {
                    counter-reset: page;
                }

                .page-number {
                    text-align: right;
                    font-size: 10px;
                    font-weight: bold;
                    margin-top: 10px;
                }

                .page-number::after {
                    counter-increment: page;
                    content: "Page " counter(page);
                }

            }
            
            
           
        
        </style>
    </head>
    <body>
        @if(!isset($isPdf))
        <!-- Print Button -->
        <button onclick="window.print()" class="print-btn no-print">
            🖨️ Print PO
        </button>
        @endif
        

        <div class="container">

            <!-- HEADER WITH COMPANY LOGO, NAME, ADDRESS, AND PO TITLE -->
            <table class="no-border-table" style="margin-bottom: 8px;border: 1px solid #000;">
                <tr>
                    <!-- LEFT SIDE - Company Logo and Name -->
                    <td width="20%" style="border: 1px solid #000; vertical-align: middle; text-align: center;">
                        @if(!empty($workspaceDetails->logo))
                        <img src="{{ get_file($workspaceDetails->logo) }}" alt="Logo" class="company-logo">
                        @else
                        <div class="logo-placeholder">
                            <span>LOGO</span>
                        </div>
                        @endif
                    </td>

                    <!-- CENTER - Company Name and Address -->
                    <td width="52%" style="border: 1px solid #000; vertical-align: top;">
                        <table class="no-border-table">
                            <tr>
                                <td class="bold" style="font-size: 16px; border: 1px solid #000; text-align: center; padding: 6px 0;">
                                    {{ $settings['company_name'] ?? $workspaceDetails->name ?? 'Company Name' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="company-info" style="border: none; text-align: center;">
                                    @if(!empty($settings['company_address']))
                                    {{ $settings['company_address'] }},
                                    @endif
                                    @if(!empty($settings['company_city']))
                                    {{ $settings['company_city'] }},
                                    @endif
                                    @if(!empty($settings['company_state']))
                                    {{ $settings['company_state'] }}
                                    @endif
                                    @if(!empty($settings['company_zipcode']))
                                    - {{ $settings['company_zipcode'] }}
                                    @endif
                                    @if(!empty($settings['company_country']))
                                    , {{ $settings['company_country'] }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="company-info" style="border: none; text-align: center;">
                                    @if(!empty($settings['company_gst']))
                                    <strong>GSTIN:</strong> {{ $settings['company_gst'] }} &nbsp;|&nbsp;
                                    @endif
                                    @if(!empty($workspaceDetails->pan_number))
                                    <strong>PAN:</strong> {{ $workspaceDetails->pan_number }} &nbsp;|&nbsp;
                                    @endif
                                    @if(!empty($workspaceDetails->cin_no))
                                    <strong>CIN:</strong> {{ $workspaceDetails->cin_no }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="company-info" style="border: none; text-align: center;">
                                    @if(!empty($workspaceDetails->phone))
                                    <strong>Tel:</strong> {{ $workspaceDetails->phone }} &nbsp;|&nbsp;
                                    @endif
                                    @if(!empty($workspaceDetails->email))
                                    <strong>Email:</strong> {{ $workspaceDetails->email }} &nbsp;|&nbsp;
                                    @endif
                                    @if(!empty($workspaceDetails->website))
                                    <strong>Website:</strong> {{ $workspaceDetails->website }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="company-info" style="border: none; text-align: center;">
                            @if(!empty($workspaceDetails->address))
                                    <strong>Address:</strong>
                                    {{ $workspaceDetails->address }}
                                    @if(!empty($workspaceDetails->city)), {{ $workspaceDetails->city }}@endif
                                    @if(!empty($workspaceDetails->state)), {{ $workspaceDetails->state }}@endif
                                    @if(!empty($workspaceDetails->pincode)) - {{ $workspaceDetails->pincode }}@endif
                                    <br>
                                    @endif
                                       </td>
                            </tr>
                        </table>
                    </td>

                    <!-- RIGHT SIDE - PO Details -->
                    <td width="28%" style="border: 1px solid #000; vertical-align: top;">
                        <table class="no-border-table">
                            <tr>
                                <td class="bold header-bar" style="border: 1px solid #000; font-size: 11px;">PO DETAILS</td>
                            </tr>
                            <tr>
                                <td style="border: none; font-size: 10px; line-height: 1.8;">
                                    <strong>PO No:</strong> {{ $purchaseOrder->po_number ?? '-' }}<br>
                                    <strong>PO Date:</strong> 
                                    @if(!empty($purchaseOrder->po_date))
                                    {{ \Carbon\Carbon::parse($purchaseOrder->po_date)->format('d-m-Y') }}
                                    @else
                                    -
                                    @endif
                                    <br>
                                    <strong>Delivery Date:</strong> 
                                    @if(!empty($purchaseOrder->delivery_date))
                                    {{ \Carbon\Carbon::parse($purchaseOrder->delivery_date)->format('d-m-Y') }}
                                    @else
                                    -
                                    @endif
                                    <br>
                                    <strong>Status:</strong> {{ $purchaseOrder->status ?? 'Pending' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- SECTION 2: PURCHASE ORDER TITLE -->
            <div class="po-title-bar">
                Purchase Order
            </div>

           

            <!-- SECTION 3: VENDOR AND PO DETAILS SECTION -->
            <table style="margin-top: 8px;">
                <tr>
                    <!-- LEFT COLUMN - Vendor Address Block -->
                    <td width="50%" style="vertical-align: top;">
                        <table class="no-border-table">
                            <tr>
                                <td class="bold header-bar" style="border: 1px solid #000;">Vendor / Supplier</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; border-top: none; font-size: 10px; line-height: 1.5;">
                                    <strong>{{ $purchaseOrder->supplier->name ?? '-' }}</strong><br>
                                    @if(!empty($purchaseOrder->supplier->address))
                                    {{ $purchaseOrder->supplier->address }}
                                    @if(!empty($purchaseOrder->supplier->city)), {{ $purchaseOrder->supplier->city }}@endif
                                    @if(!empty($purchaseOrder->supplier->state)), {{ $purchaseOrder->supplier->state }}@endif
                                    @if(!empty($purchaseOrder->supplier->zipcode)) - {{ $purchaseOrder->supplier->zipcode }}@endif
                                    @endif
                                    <br>
                                    @if(!empty($purchaseOrder->supplier->contact_person))
                                    <strong>Contact:</strong> {{ $purchaseOrder->supplier->contact_person }}<br>
                                    @endif
                                    @if(!empty($purchaseOrder->supplier->phone))
                                    <strong>Phone:</strong> {{ $purchaseOrder->supplier->phone }}<br>
                                    @endif
                                    @if(!empty($purchaseOrder->supplier->email))
                                    <strong>Email:</strong> {{ $purchaseOrder->supplier->email }}<br>
                                    @endif
                                    <strong>GSTIN:</strong> {{ $purchaseOrder->supplier->gst_number ?? '-' }}<br>
                                    @if(!empty($purchaseOrder->supplier->state))
                                    <strong>State:</strong> {{ $purchaseOrder->supplier->state }}
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>

                    <!-- RIGHT COLUMN - PO Reference Details -->
                    <td width="50%" style="vertical-align: top;">
                        <table class="no-border-table">
                            <tr>
                                <td class="bold header-bar" style="border: 1px solid #000;">Order Reference</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; border-top: none; font-size: 10px; line-height: 1.8;">
                                    <strong>Indent No:</strong> {{ $purchaseOrder->indent->indent_number ?? '-' }}<br>
                                    <strong>Site/Project:</strong> {{ $purchaseOrder->site->name ?? $projectDetails->name ?? '-' }}<br>
                                    <strong>PO Coordinator:</strong> {{ $workspaceDetails->contact_person ?? '-' }}<br>
                                    <strong>Tax Type:</strong> {{ $purchaseOrder->tax_type == 'igst' ? 'IGST' : 'CGST/SGST' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- SECTION 4: BILLING AND DELIVERY ADDRESS SECTION -->
            <table style="margin-top: 8px;">
                <tr>

                    <!-- LEFT BOX - Billing Address -->
                    <td width="50%" style="vertical-align: top; padding-right: 4px;">
                        <table class="no-border-table">

                            <tr>
                                <td class="bold header-bar" style="border:1px solid #000;">
                                    Billing Address
                                </td>
                            </tr>

                            <tr>
                                <td style="border:1px solid #000; border-top:none; min-height:80px; font-size:10px; line-height:1.5;">

                                    <strong>{{ $workspaceDetails->name ?? $settings['company_name'] ?? '-' }}</strong><br>

                                    @if(!empty($workspaceDetails->contact_person))
                                    <strong>Contact:</strong> {{ $workspaceDetails->contact_person }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->phone))
                                    <strong>Phone:</strong> {{ $workspaceDetails->phone }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->email))
                                    <strong>Email:</strong> {{ $workspaceDetails->email }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->address))
                                    <strong>Address:</strong>
                                    {{ $workspaceDetails->address }}
                                    @if(!empty($workspaceDetails->city)), {{ $workspaceDetails->city }}@endif
                                    @if(!empty($workspaceDetails->state)), {{ $workspaceDetails->state }}@endif
                                    @if(!empty($workspaceDetails->pincode)) - {{ $workspaceDetails->pincode }}@endif
                                    <br>
                                    @endif

                                    @if(!empty($workspaceDetails->country))
                                    <strong>Country:</strong> {{ $workspaceDetails->country }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->gst_number))
                                    <strong>GSTIN:</strong> {{ $workspaceDetails->gst_number }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->pan_number))
                                    <strong>PAN:</strong> {{ $workspaceDetails->pan_number }}
                                    @endif

                                </td>
                            </tr>

                        </table>
                    </td>


                    <!-- RIGHT BOX - Delivery Address -->
                    <td width="50%" style="vertical-align: top; padding-left: 4px;">
                        <table class="no-border-table">

                            <tr>
                                <td class="bold header-bar" style="border:1px solid #000;">
                                    Delivery Address
                                </td>
                            </tr>

                            <tr>
                                <td style="border:1px solid #000; border-top:none; min-height:80px; font-size:10px; line-height:1.5;">

                                    @if(!empty($purchaseOrder->delivery_address))

                                    {{ $purchaseOrder->delivery_address }}

                                    @else

                                    <strong>{{ $workspaceDetails->name ?? $settings['company_name'] ?? '-' }}</strong><br>

                                    @if(!empty($workspaceDetails->contact_person))
                                    <strong>Contact:</strong> {{ $workspaceDetails->contact_person }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->phone))
                                    <strong>Phone:</strong> {{ $workspaceDetails->phone }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->email))
                                    <strong>Email:</strong> {{ $workspaceDetails->email }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->address))
                                    <strong>Address:</strong>
                                    {{ $workspaceDetails->address }}
                                    @if(!empty($workspaceDetails->city)), {{ $workspaceDetails->city }}@endif
                                    @if(!empty($workspaceDetails->state)), {{ $workspaceDetails->state }}@endif
                                    @if(!empty($workspaceDetails->pincode)) - {{ $workspaceDetails->pincode }}@endif
                                    <br>
                                    @endif

                                    @if(!empty($workspaceDetails->country))
                                    <strong>Country:</strong> {{ $workspaceDetails->country }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->gst_number))
                                    <strong>GSTIN:</strong> {{ $workspaceDetails->gst_number }}<br>
                                    @endif

                                    @if(!empty($workspaceDetails->pan_number))
                                    <strong>PAN:</strong> {{ $workspaceDetails->pan_number }}
                                    @endif

                                    @endif

                                </td>
                            </tr>

                        </table>
                    </td>

                </tr>
            </table>

            <!-- SECTION 5: ITEM TABLE -->
            <table class="items-table" style="margin-top: 8px;">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">#</th>
                        <th width="10%">HSN Code</th>
                        <th width="27%">Description</th>
                        <th width="7%" class="text-center">Qty</th>
                        <th width="6%" class="text-center">UoM</th>
                        <th width="11%" class="text-right">Rate (₹)</th>
                        <th width="10%" class="text-right">Disc (₹)</th>
                        <th width="11%" class="text-right">Taxable (₹)</th>
                        <th width="7%" class="text-center">GST %</th>
                        <th width="11%" class="text-right">Total (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @php $srNo = 1; @endphp
                    @forelse($purchaseOrder->items as $item)
                    @php
                    $gstPercent = 0;
                    if (!empty($item->gstMaster)) {
                    $gstPercent = $purchaseOrder->tax_type == 'igst' 
                    ? $item->gstMaster->igst 
                    : ($item->gstMaster->cgst + $item->gstMaster->sgst);
                    }
                    @endphp
                    <tr>
                        <td class="text-center">{{ $srNo++ }}</td>
                        <td>{{ $item->material->hsn_code ?? '-' }}</td>
                        <td>{{ $item->material->name ?? '-' }}</td>
                        <td class="text-center">{{ formatIndianNumber($item->quantity ?? 0, 2) }}</td>
                        <td class="text-center">{{ $item->unit ?? 'PCS' }}</td>
                        <td class="text-right">{{ formatIndianNumber($item->price ?? 0, 2) }}</td>
                        <td class="text-right">{{ formatIndianNumber($item->discount_amount ?? 0, 2) }}</td>
                        <td class="text-right">{{ formatIndianNumber($item->subtotal ?? 0, 2) }}</td>
                        <td class="text-center">{{ formatIndianNumber($gstPercent, 2) }}%</td>
                        <td class="text-right">{{ formatIndianNumber(($item->subtotal ?? 0) + ($item->tax_amount ?? 0), 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center">No items found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- SECTION 6: TAX SUMMARY SECTION -->
            <table style="margin-top: 8px;">
                <tr>
                    <td width="62%" style="border: none; vertical-align: top;padding-left: 0px;">
                        <!-- Amount in Words -->
                        @if(!empty($totalInWords))
                        <div class="amount-in-words" style="margin-top: 0px;">
                            Amount in Words: Rupees {{ $totalInWords }} Only
                        </div>
                        @endif

                        <!-- Remarks / Notes -->
                        @if(!empty($purchaseOrder->remark))
                        <table class="" style="margin-top: 8px;">
                            <tr>
                                <td class="bold header-bar" style="border: 1px solid #000; text-align: left;">Remarks</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; border-top: none; min-height: 50px; font-size: 10px;">
                                    {!! nl2br(e($purchaseOrder->remark)) !!}
                                </td>
                            </tr>
                        </table>
                        @endif

                        <!-- Bank Details -->
                        <table class="" style="margin-top: 8px;">
                            <tr>
                                <td class="bold header-bar" style="border: 1px solid #000; text-align: left;">Bank Details</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; border-top: none; font-size: 10px; line-height: 1.5;">
                                    @if(!empty($workspaceDetails->bank_name) || !empty($settings['workspace_bank_name']))
                                    <strong>Bank Name:</strong> {{ $workspaceDetails->bank_name ?? $settings['workspace_bank_name'] ?? '-' }}<br>
                                    @endif
                                    @if(!empty($workspaceDetails->account_number) || !empty($settings['workspace_account_number']))
                                    <strong>A/C No:</strong> {{ $workspaceDetails->account_number ?? $settings['workspace_account_number'] ?? '-' }}<br>
                                    @endif
                                    @if(!empty($workspaceDetails->ifsc_code) || !empty($settings['workspace_ifsc_code']))
                                    <strong>IFSC Code:</strong> {{ $workspaceDetails->ifsc_code ?? $settings['workspace_ifsc_code'] ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="38%" style="border: none; vertical-align: top;padding-right: 0px;">
                        <table class="tax-table">
                            <tr>
                                <td class="bold header-bar" colspan="4" style="text-align: center;">Tax Summary</td>
                            </tr>
                            <tr>
                                <td style="width: 25%;"><strong>Tax Type</strong></td>
                                <td style="width: 15%;"><strong>Rate</strong></td>
                                <td style="width: 30%;"><strong>Taxable (₹)</strong></td>
                                <td style="width: 30%;"><strong>Tax (₹)</strong></td>
                            </tr>
                            @if($purchaseOrder->tax_type == 'igst')
                            <tr>
                                <td>IGST</td>
                                <td class="text-right">18.00%</td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_taxable_value ?? 0, 2) }}</td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_igst ?? 0, 2) }}</td>
                            </tr>
                            @else
                            <tr>
                                <td>CGST</td>
                                <td class="text-right">9.00%</td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_taxable_value ?? 0, 2) }}</td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_cgst ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td>SGST</td>
                                <td class="text-right">9.00%</td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_taxable_value ?? 0, 2) }}</td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_sgst ?? 0, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="2"><strong>SubTotal</strong></td>
                                <td></td>
                                <td class="text-right bold">{{ formatIndianNumber($purchaseOrder->total_taxable_value ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="2">Total Tax</td>
                                <td></td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->total_tax ?? 0, 2) }}</td>
                            </tr>
                            @if(($purchaseOrder->additional_charge ?? 0) > 0)
                            <tr>
                                <td colspan="2">Add. Charges</td>
                                <td></td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->additional_charge, 2) }}</td>
                            </tr>
                            @endif
                            @if(($purchaseOrder->additional_deduction ?? 0) > 0)
                            <tr>
                                <td colspan="2">Less: Deductions</td>
                                <td></td>
                                <td class="text-right">{{ formatIndianNumber($purchaseOrder->additional_deduction, 2) }}</td>
                            </tr>
                            @endif
                            <tr class="grand-total-row">
                                <td colspan="2"><strong>Grand Total</strong></td>
                                <td></td>
                                <td class="text-right bold">{{ formatIndianNumber($purchaseOrder->grand_total ?? 0, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- SECTION 7: TERMS AND CONDITIONS - SHORT SECTION -->
            <table class="terms-short" style="margin-top: 12px;">
                <tr>
                    <td class="bold header-bar" colspan="2">Terms and Conditions</td>
                </tr>
                
                <tr>
                    <td>Payment Terms</td>
                    <td>{{ $purchaseOrder->payment_terms_conditions ?? '' }}</td>
                </tr>
                <tr>
                    <td>Delivery Terms</td>
                    <td>{{ $purchaseOrder->delivery_terms_conditions ?? '' }}</td>
                </tr>
                
            </table>

            <!--     SECTION 7: TERMS AND CONDITIONS - SHORT SECTION 
                <table class="terms-short" style="margin-top: 12px;">
                    <tr>
                        <td class="bold header-bar" colspan="2">Terms and Conditions</td>
                    </tr>
                    <tr>
                        <td>Prices Basis</td>
                        <td>{{ $purchaseOrder->delivery_terms_conditions ?? 'As per discussion' }}</td>
                    </tr>
                    <tr>
                        <td>Taxes</td>
                        <td>{{ $purchaseOrder->tax_type == 'igst' ? 'IGST Applicable' : 'CGST/SGST Applicable' }}</td>
                    </tr>
                    <tr>
                        <td>Payment Terms</td>
                        <td>Within 30 days from invoice date</td>
                    </tr>
                    <tr>
                        <td>Delivery</td>
                        <td>As per agreed schedule</td>
                    </tr>
                    <tr>
                        <td>Inspection</td>
                        <td>Before dispatch from vendor premises</td>
                    </tr>
                    <tr>
                        <td>Guarantee</td>
                        <td>12 months from date of delivery</td>
                    </tr>
                    <tr>
                        <td>Test Report</td>
                        <td>To be supplied with dispatch</td>
                    </tr>
                    <tr>
                        <td>Documents</td>
                        <td>Invoice, Challan, LR Copy, Test Reports</td>
                    </tr>
                </table>-->
            <div class="page-number"></div>
            <!-- SECTION 8: PAGE BREAK -->
            <div class="page-break"></div>

            <!-- PAGE 2: GENERAL TERMS AND CONDITIONS -->

            <!-- SECTION 9: GENERAL TERMS PAGE -->

            <table style="width:100%; border-collapse:collapse; border:1px solid #000; margin-top:20px; font-size:10px; line-height:1.4;">

                <tr>
                    <td colspan="2" style="background:#d9d9d9 !important; text-align:center; font-weight:bold; padding:6px; border-bottom:1px solid #000;">
                        General Terms and Conditions
                    </td>
                </tr>


                <tr>
                    <td style="width:20%; border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Fixed-Price Clause:
                    </td>
                    <td style="padding:6px;">
                        The Price Governing This Order Shall for All Purposes, Remain Firm Through the Duration of Supply / Work Till Completion, Unless Otherwise Agreed to Specifically in Writing by the Purchaser.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Statutory Variations:
                    </td>
                    <td style="padding:6px;">
                        Any variation in any form whether change in rate or addition of new tax shall be borne by us extra as per Govt. notifications.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        LD Clause:
                    </td>
                    <td style="padding:6px;">
                        If you fail in the compliance of the contract to deliver the complete material on or before delivery period, you will be liable to pay 0.5% per week maximum up to 5% of work value.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Requirements:
                    </td>
                    <td style="padding:6px;">
                        The PO number, item code, item description & vendor code must be mentioned on all documents sent to us.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Claim:
                    </td>
                    <td style="padding:6px;">
                        Notice for damage in transit or loss of goods shall be notified in writing to you within 10 days of receipt of goods.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Packing
                    </td>
                    <td style="padding:6px;">
                        Supplier has to pack the Goods so that they may be transported and unloaded safely. Goods has to describe accurately, classified, marked, and labelled, in accordance with the PO.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Invoice Copy
                    </td>
                    <td style="padding:6px;">
                        Original Copy of invoice has to courier to our HO, i.e, M/s. {{ $workspaceDetails->name ?? $settings['company_name'] ?? '-' }} @if(!empty($workspaceDetails->address)), {{ $workspaceDetails->address }}@endif @if(!empty($workspaceDetails->city)), {{ $workspaceDetails->city }}@endif @if(!empty($workspaceDetails->state)), {{ $workspaceDetails->state }}@endif @if(!empty($workspaceDetails->pincode)) - {{ $workspaceDetails->pincode }}@endif, apart from the invoice sent with the material. Invoices sent to any other address will not be tracked & processed.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Acceptance of order
                    </td>
                    <td style="padding:6px;">
                        Seller/Service provider will be deemed to have accepted the order unless seller acknowledges exceptions in writing within three business days after this mail of the order. Any shipment/partial shipment/Part Work of the goods/Services by Seller/Service provider shall be deemed to be an acceptance of the order. In the event of any inconsistency between the terms of these terms and any purported acceptance or acknowledgment form or invoice by seller, the terms of these terms shall prevail.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        GST Payment
                    </td>
                    <td style="padding:6px;">
                        GST is applicable as per tax rate defined in the PO.
                    </td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Confidentiality Clause
                    </td>
                    <td style="padding:6px;">
                        The Supplier agrees and acknowledges that for the purpose of this transaction or in the course of performance of the services etc.,(if applicable) under this contract, it may be provided with or shall have access to certain non-public, proprietary and confidential information belonging to the company. The Supplier undertakes to secure and hold all such information, in strict confidence. The Supplier shall limit its disclosure only to such of its employees, on a 'need to know' basis for the fulfillment of the purpose under this contract and shall be responsible for breach of the same by it or its employees.</td>
                </tr>


                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Child Labour and Forced Labour
                    </td>
                    <td style="padding:6px;">
                        Supplier warrants that it does not employ children, prison labor, indentured labor or bonded labor. Moreover,   Supplier agrees that it 
                        will not conduct business with vendors employing children, prison labor, indentured labor or bonded. In the absence of any national or local law, Purchaser and Supplier agree to define "child" as less than 15 years of age. If local minimum age law is set below 15 years of age, but is in accordance with exceptions under International Labor Organization (ILO) Convention 138, the lower age will apply. 
                        Buyer has the right to audit Supplier's premises to ensure compliance with this warranty.
                        Supplier warrants that no goods, materials or other items or services which are to be used or supplied to the buyer have been sourced or obtained from any Person or entity which is a citizen of, resident or located in, operating from,  or incorporated or organised in, the Xinjiang Uyghur Autonomous Region in the People’s Republic of China. Buyer has the right to audit Supplier's premises to ensure compliance with this warranty.

                    </td>
                </tr>

<!--                <tr>
                    <td colspan="2" style="height:80px; border-top:1px solid #000;"></td>
                </tr>-->
                <tr>
                    <td style="border-right:1px solid #000; padding:6px; font-weight:bold;">
                        Representations & Warranties
                    </td>
                    <td style="padding:6px;">
                        A) The Supplier represents and warrants that it has all necessary permits and licences to allow it to sell the Goods and/or Services to	the Purchaser, and that it has complied with all relevant laws, rules and regulations affecting its obligations and the performance of the Contract.<br>
                        B) Breach of any of the representations and warranties, without prejudice to any other rights of the Purchaser, entitle the Purchaser to terminate the Contract and claim damages, loss, costs and expenses from the Supplier (including, without limitation, legal costs on an indemnity basis).<br>
                        C) The Supplier shall fully indemnify and hold harmless the Purchaser and all its assigns, subcontractors and customers from and against all claims, liabilities, actions, demands, damages, costs and expenses ( including, without limitation, legal costs on an indemnity basis) of any kind or nature arising from, in connection with or related in any way to any breach or alleged breach of any of the representations and warranties made by the Supplier under the Contract.<br>

                    </td>
                </tr>




            </table>

            <!-- SECTION 10: SIGNATURE SECTION -->
            <table class="signature-table" style="margin-top: 25px;">
                <tr>
                    <td width="33%">
                        <div class="signature-line"></div>
                        <br><strong>Prepared By</strong>
                    </td>
                    <td width="34%">
                        <div class="signature-line"></div>
                        <br><strong>Checked By</strong>
                    </td>
                    <td width="33%">
                        <div class="signature-line"></div>
                        <br><strong>Authorized Signature</strong>
                    </td>
                </tr>
            </table>

            <div class="page-number"></div>
            <!-- FOOTER NOTE -->
            <div class="footer-note">
                This is a computer-generated document and does not require a signature. This purchase order is subject to the terms and conditions mentioned herein.
            </div>
            
            

        </div>

      
    </body>
</html>
