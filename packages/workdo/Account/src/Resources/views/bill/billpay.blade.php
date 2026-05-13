@extends('account::layouts.master')
@php
    $admin_settings = getAdminAllSetting();

    $company_settings = getCompanyAllSetting($bill->created_by);

@endphp
@section('page-title')
    {{ __('Bill Detail') }}
@endsection
@push('script-page')
    <script>
        $(document).on('click', '#shipping', function() {
            var url = $(this).data('url');
            var is_display = $("#shipping").is(":checked");
            $.ajax({
                url: url,
                type: 'get',
                data: {
                    'is_display': is_display,
                },
                success: function(data) {}
            });
        })
    </script>
@endpush
@push('css')
<style>
    .bill_status{
        min-width: 94px;
    }
</style>
@endpush
@section('action-btn')
    @if (Auth::check() && Auth::user()->isAbleTo('bill manage'))
        @if ($bill->status != 0)
            <div class="row justify-content-between align-items-center ">
                <div class="col-12 d-flex align-items-center justify-content-between justify-content-md-end">
                    @if (!empty($billPayment))
                        <div class="mx-2 all-button-box">
                            <a href="#" data-url="{{ route('bill.debit.note', $bill->id) }}" data-ajax-popup="true"
                                data-title="{{ __('Add Debit Note') }}" class="btn btn-sm btn-primary">
                                {{ __('Add Debit Note') }}
                            </a>
                        </div>
                    @endif
                    <div class="mr-3 all-button-box">
                        <a href="{{ route('bill.resent', $bill->id) }}" class="btn btn-sm btn-primary">
                            {{ __('Resend Bill') }}
                        </a>
                        <a href="{{ route('bill.pdf', Crypt::encrypt($bill->id)) }}" target="_blank"
                            class="btn btn-sm btn-primary"><i class="ti ti-printer"></i>
                            {{ __('Print') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="row justify-content-between align-items-center ">
            <div class="col-12 d-flex align-items-center justify-content-between justify-content-md-end">
                <div class="all-button-box">
                    <a href="#" data-url="{{ route('vendor.bill.send', $bill->id) }}" data-ajax-popup="true"
                        data-title="{{ __('Send Bill') }}" class="btn btn-sm btn-primary btn-icon-only width-auto">
                        {{ __('SendMail') }}
                    </a>
                </div>
                <div class="mx-2 all-button-box">
                    <a href="{{ route('bill.pdf', Crypt::encrypt($bill->id)) }}" target="_blank"
                        class="btn btn-sm btn-primary btn-icon-only width-auto">
                        <i class="ti ti-printer"></i>{{ __('Print') }}
                    </a>
                </div>
            </div>
        </div>
    @endif
@endsection
@section('content')
    @php
        $vendor = $bill->vendor;
    @endphp
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="invoice">
                        <div class="invoice-print">
                            <div class="row row-gap invoice-title border-1 border-bottom  pb-3 mb-3">
                                <div class="col-sm-4  col-12">
                                    <h2 class="h3 mb-0">{{ __('Bill') }}</h2>
                                </div>
                                <div class="col-sm-8  col-12">
                                    <div class="d-flex invoice-wrp flex-wrap align-items-center gap-md-2 gap-1 justify-content-end">
                                        <div class="d-flex invoice-date flex-wrap align-items-center justify-content-end gap-md-3 gap-1">
                                            <p class="mb-0"><strong>{{ __('Issue Date') }} :</strong> {{ company_date_formate($bill->issue_date,$bill->created_by, $bill->workspace) }}</p>
                                            <p class="mb-0"><strong>{{ __('Due Date') }} :</strong> {{ company_date_formate($bill->due_date,$bill->created_by, $bill->workspace) }}</p>
                                        </div>
                                        <h3 class="invoice-number mb-0">
                                            {{ \Workdo\Account\Entities\Bill::billNumberFormat($bill->bill_id, $company_id, $workspace_id) }}
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="p-sm-4 p-3 invoice-billed">
                                <div class="row row-gap">
                                    <div class="col-lg-4 col-sm-6">
                                        @if (!empty($vendor->billing_name))
                                            @if (!empty($vendor->billing_name) && !empty($vendor->billing_address) && !empty($vendor->billing_zip))
                                                <p class="mb-2"><strong class="h5 mb-1 d-block">{{ __('Billed To') }}
                                                    :</strong>
                                                    {{ !empty($vendor->billing_name) ? $vendor->billing_name : '' }}
                                                    {{ !empty($vendor->billing_address) ? $vendor->billing_address : '' }}
                                                    {{ !empty($vendor->billing_city) ? $vendor->billing_city . ' ,' : '' }}
                                                    {{ !empty($vendor->billing_state) ? $vendor->billing_state . ' ,' : '' }}
                                                    {{ !empty($vendor->billing_zip) ? $vendor->billing_zip : '' }}
                                                    {{ !empty($vendor->billing_country) ? $vendor->billing_country : '' }}
                                                </p>
                                                <p class="mb-1 text-dark">
                                                    {{ !empty($customer->billing_phone) ? $customer->billing_phone : '' }}
                                                </p>
                                                <p class="mb-0">
                                                    <strong>{{ __('Tax Number ') }} :
                                                    </strong>{{ !empty($customer->tax_number) ? $customer->tax_number : '' }}
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="col-lg-4 col-sm-6">
                                        @if (company_setting('bill_shipping_display', $company_id, $workspace_id) == 'on')
                                            @if (!empty($vendor->shipping_name) && !empty($vendor->shipping_address) && !empty($vendor->shipping_zip))
                                            <p class="mb-2">
                                                <strong class="h5 mb-1 d-block">{{ __('Shipped To') }} :</strong>
                                                    <span class="text-muted d-block" style="max-width:80%">
                                                        {{ !empty($vendor->shipping_name) ? $vendor->shipping_name : '' }}
                                                        {{ !empty($vendor->shipping_address) ? $vendor->shipping_address : '' }}
                                                        {{ !empty($vendor->shipping_city) ? $vendor->shipping_city .' ,': '' }}
                                                        {{ !empty($vendor->shipping_state) ? $vendor->shipping_state .' ,': '' }}
                                                        {{ !empty($vendor->shipping_zip) ? $vendor->shipping_zip : '' }}
                                                        {{ !empty($vendor->shipping_country) ? $vendor->shipping_country : '' }}
                                                        {{ !empty($vendor->shipping_phone) ? $vendor->shipping_phone : '' }}
                                                    </span>
                                                </p>
                                                <p class="mb-1 text-dark">
                                                    {{ !empty($customer->billing_phone) ? $customer->billing_phone : '' }}
                                                </p>
                                                <p class="mb-0">
                                                    <strong>{{ __('Tax Number ') }} :
                                                    </strong>{{ !empty($customer->tax_number) ? $customer->tax_number : '' }}
                                                </p>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="col-lg-2 col-sm-6">
                                        <strong>{{ __('Status') }} :</strong><br>
                                        @if ($bill->status == 0)
                                            <span class="badge fix_badge f-12 p-2 d-inline-block bg-primary">{{ __(Workdo\Account\Entities\Bill::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 1)
                                            <span class="badge fix_badge f-12 p-2 d-inline-block bg-warning">{{ __(Workdo\Account\Entities\Bill::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 2)
                                            <span class="badge fix_badge f-12 p-2 d-inline-block bg-danger">{{ __(Workdo\Account\Entities\Bill::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 3)
                                            <span class="badge fix_badge f-12 p-2 d-inline-block bg-info">{{ __(Workdo\Account\Entities\Bill::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 4)
                                            <span class="badge fix_badge f-12 p-2 d-inline-block bg-success">{{ __(Workdo\Account\Entities\Bill::$statues[$bill->status]) }}</span>
                                        @endif
                                    </div>

                                    <div class="col-lg-2 col-sm-6">
                                        <div class="float-sm-end qr-code">
                                            @if (!empty($company_settings['bill_qr_display']) && $company_settings['bill_qr_display'] == 'on')
                                                <div class="col">
                                                    <div class="float-sm-end">
                                                        <p> {!! DNS2D::getBarcodeHTML(
                                                            route('pay.billpay', \Illuminate\Support\Facades\Crypt::encrypt($bill->id)),
                                                            'QRCODE',
                                                            2,
                                                            2,
                                                        ) !!}</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if (!empty($customFields) && count($bill->customField) > 0)
                                <div class="px-4 mt-3">
                                    <div class="row row-gap">
                                        @foreach ($customFields as $field)
                                            <div class="col-xxl-3 col-sm-6">
                                                <strong class="d-block mb-1">{{ $field->name }} </strong>
                                                    @if ($field->type == 'attachment')
                                                        <a href="{{ get_file($bill->customField[$field->id]) }}"
                                                            target="_blank">
                                                            <img src=" {{ get_file($bill->customField[$field->id]) }} "
                                                                class="wid-120 rounded">
                                                        </a>
                                                    @else
                                                        <p  class="mb-0">
                                                            {{ !empty($bill->customField[$field->id]) ? $bill->customField[$field->id] : '-' }}
                                                        </p>
                                                    @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="row mt-4">
                                <div class="col-md-12 invoice-summary mt-3">
                                    <div class="invoice-title border-1 border-bottom mb-3 pb-2">
                                        <h3 class="h4 mb-0">{{ __('Item Summary') }}</h3>
                                        <small>{{ __('All items here cannot be deleted.') }}</small>
                                    </div>
                                    <div class="table-responsive mt-2">
                                        <table class="table mb-0 table-striped">
                                            <tr>
                                                <th class="text-white bg-primary text-uppercase" data-width="40">#</th>
                                                @if ($bill->bill_module == 'account' || $bill->bill_module == '')
                                                    <th class="text-white bg-primary text-uppercase">{{ __('Item Type') }}</th>
                                                    <th class="text-white bg-primary text-uppercase">{{ __('Item') }}</th>
                                                @elseif($bill->bill_module == 'taskly')
                                                    <th class="text-white bg-primary text-uppercase">{{ __('Project') }}</th>
                                                @endif
                                                <th class="text-white bg-primary text-uppercase">{{__('Quantity')}}</th>
                                                <th class="text-white bg-primary text-uppercase">{{ __('Rate') }}</th>
                                                <th class="text-white bg-primary text-uppercase">{{ __('Discount') }}</th>
                                                <th class="text-white bg-primary text-uppercase">{{ __('Tax') }}</th>
                                                <th class="text-white bg-primary text-uppercase">{{ __('Description') }}</th>
                                                <th class="text-right text-white bg-primary text-uppercase" width="12%">{{ __('Price') }}<br>
                                                    <small class="text-danger font-weight-bold">{{ __('after discount & tax') }}</small>
                                                </th>
                                            </tr>
                                            @php
                                                $totalQuantity = 0;
                                                $totalRate = 0;
                                                $totalTaxPrice = 0;
                                                $totalDiscount = 0;
                                                $taxesData = [];
                                                $TaxPrice_array = [];
                                            @endphp

                                            @foreach ($iteams as $key => $iteam)
                                                @if (!empty($iteam->tax))
                                                    @php
                                                        $taxes = Workdo\Account\Entities\AccountUtility::tax($iteam->tax);
                                                        $totalQuantity += $iteam->quantity;
                                                        $totalRate += $iteam->price;
                                                        $totalDiscount += $iteam->discount;
                                                        foreach ($taxes as $taxe) {
                                                            $taxDataPrice = Workdo\Account\Entities\AccountUtility::taxRate($taxe['rate'], $iteam->price, $iteam->quantity, $iteam->discount);
                                                            if (array_key_exists($taxe['name'], $taxesData)) {
                                                                $taxesData[$taxe['name']] = $taxesData[$taxe['name']] + $taxDataPrice;
                                                            } else {
                                                                $taxesData[$taxe['name']] = $taxDataPrice;
                                                            }
                                                        }
                                                    @endphp
                                                @endif
                                                <tr>
                                                    <td>{{ $key + 1 }}</td>
                                                    @if ($bill->bill_module == 'account' || $bill->bill_module == '')
                                                        <td>{{ !empty($iteam->product_type) ? Str::ucfirst($iteam->product_type) : '--' }}
                                                        </td>
                                                        <td>{{ !empty($iteam->product()) ? $iteam->product()->name : '' }}</td>
                                                    @elseif($bill->bill_module == 'taskly')
                                                        <td>{{ !empty($iteam->product()) ? $iteam->product()->title : '' }}</td>
                                                    @endif
                                                    <td>{{ $iteam->quantity }}</td>
                                                    <td>{{ currency_format_with_sym($iteam->price, $company_id, $workspace_id) }}
                                                    </td>
                                                    <td>{{ currency_format_with_sym($iteam->discount, $company_id, $workspace_id) }}
                                                    </td>
                                                    <td>
                                                        @if (!empty($iteam->tax))
                                                            <table>
                                                                @php
                                                                    $totalTaxRate = 0;
                                                                    $data = 0;
                                                                @endphp
                                                                @foreach ($taxes as $tax)
                                                                    @php
                                                                        $taxPrice = Workdo\Account\Entities\AccountUtility::taxRate($tax['rate'], $iteam->price, $iteam->quantity, $iteam->discount);
                                                                        $totalTaxPrice+=$taxPrice;
                                                                        $data+=$taxPrice;
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $tax['name'] . ' (' . $tax['rate'] . '%)' }}
                                                                        </td>
                                                                        <td>{{ currency_format_with_sym($taxPrice, $company_id, $workspace_id) }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                                @php
                                                                    array_push($TaxPrice_array,$data);
                                                                @endphp
                                                            </table>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td style="white-space: break-spaces;">{{ !empty($iteam->description) ? $iteam->description : '-' }}</td>
                                                    @php
                                                        $tr_tex = (array_key_exists($key,$TaxPrice_array) == true) ? $TaxPrice_array[$key] : 0;
                                                    @endphp
                                                    <td class="text-right">
                                                        {{ currency_format_with_sym($iteam->price * $iteam->quantity - $iteam->discount + $tr_tex, $company_id, $workspace_id) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tfoot>
                                                <tr>
                                                    <td></td>
                                                    @if ($bill->bill_module == 'account' || $bill->bill_module == '')
                                                        <td></td>
                                                    @endif
                                                    <td class="bg-color"><b>{{ __('Total') }}</b></td>
                                                    <td class="bg-color"><b>{{ $totalQuantity }}</b></td>
                                                    <td class="bg-color"><b>{{ currency_format_with_sym($totalRate, $company_id, $workspace_id) }}</b>
                                                    </td>
                                                    <td class="bg-color"><b>{{ currency_format_with_sym($totalDiscount, $company_id, $workspace_id) }}</b>
                                                    </td>
                                                    <td class="bg-color"><b>{{ currency_format_with_sym($totalTaxPrice, $company_id, $workspace_id) }}</b>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                                @php
                                                    $colspan = 6;
                                                    if ($bill->bill_module == 'account' || $bill->bill_module == '') {
                                                        $colspan = 7;
                                                    }
                                                @endphp
                                                <tr>
                                                    <td colspan="{{ $colspan }}"></td>
                                                    <td class="text-right">{{ __('Sub Total') }}</td>
                                                    <td class="text-right">
                                                        <b>{{ currency_format_with_sym($bill->getSubTotal(), $company_id, $workspace_id) }}</b>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="{{ $colspan }}"></td>
                                                    <td class="text-right">{{ __('Discount') }}</td>
                                                    <td class="text-right">
                                                        <b>{{ currency_format_with_sym($bill->getTotalDiscount(), $company_id, $workspace_id) }}</b>
                                                    </td>
                                                </tr>
                                                @if (!empty($taxesData))
                                                    @foreach ($taxesData as $taxName => $taxPrice)
                                                        <tr>
                                                            <td colspan="{{ $colspan }}"></td>
                                                            <td class="text-right">{{ $taxName }}</td>
                                                            <td class="text-right">
                                                                <b>{{ currency_format_with_sym($taxPrice, $company_id, $workspace_id) }}</b>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                                <tr>
                                                    <td colspan="{{ $colspan }}"></td>
                                                    <td class="blue-text text-right">{{ __('Total') }}</td>
                                                    <td class="blue-text text-right">
                                                        <b>{{ currency_format_with_sym($bill->getTotal(), $company_id, $workspace_id) }}</b>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="{{ $colspan }}"></td>
                                                    <td class="text-right">{{ __('Paid') }}</td>
                                                    <td class="text-right">
                                                        <b>{{ currency_format_with_sym($bill->getTotal() - $bill->getDue() - $bill->billTotalDebitNote(), $company_id, $workspace_id) }}</b>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="{{ $colspan }}"></td>
                                                    <td class="text-right">{{ __('Debit Note') }}</td>
                                                    <td class="text-right">
                                                        <b>{{ currency_format_with_sym($bill->billTotalDebitNote(), $company_id, $workspace_id) }}</b>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="{{ $colspan }}"></td>
                                                    <td class="text-right">{{ __('Due') }}</td>
                                                    <td class="text-right">
                                                        <b>{{ currency_format_with_sym($bill->getDue(), $company_id, $workspace_id) }}</b>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h5 class="mb-4 h4 d-inline-block font-weight-400">{{ __('Payment Summary') }}</h5>
            <div class="card">
                <div class="py-0 card-body table-border-style">
                    <div class="m-0 table-responsive">
                        <table class="table ">
                            <tr>
                                <th class="text-dark">{{ __('Date') }}</th>
                                <th class="text-dark">{{ __('Amount') }}</th>
                                <th class="text-dark">{{ __('Account') }}</th>
                                <th class="text-dark">{{ __('Reference') }}</th>
                                <th class="text-dark">{{ __('Description') }}</th>
                            </tr>
                            @forelse($bill->payments as $key =>$payment)
                                <tr>
                                    <td>{{ company_date_formate($payment->date, $company_id, $workspace_id) }}</td>
                                    <td>{{ currency_format_with_sym($payment->amount, $company_id, $workspace_id) }}</td>
                                    <td>{{ !empty($payment->bankAccount) ? $payment->bankAccount->bank_name . ' ' . $payment->bankAccount->holder_name : '' }}
                                    </td>
                                    <td>{{ $payment->reference }}</td>
                                    <td>{{ $payment->description }}</td>
                                </tr>
                            @empty
                                @include('layouts.nodatafound')
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h5 class="mb-4 h4 d-inline-block font-weight-400">{{ __('Debit Note Summary') }}</h5>
            <div class="card">
                <div class="py-0 card-body table-border-style">
                    <div class="m-0 table-responsive">
                        <table class="table ">
                            <tr>
                                <th class="text-dark">{{ __('Date') }}</th>
                                <th class="text-dark">{{ __('Amount') }}</th>
                                <th class="text-dark">{{ __('Description') }}</th>
                            </tr>
                            @forelse($bill->debitNote as $key =>$debitNote)
                                <tr>
                                    <td>{{ company_date_formate($debitNote->date, $company_id, $workspace_id) }}</td>
                                    <td>{{ currency_format_with_sym($debitNote->amount, $company_id, $workspace_id) }}</td>
                                    <td>{{ $debitNote->description }}</td>
                                </tr>
                            @empty
                                @include('layouts.nodatafound')
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
