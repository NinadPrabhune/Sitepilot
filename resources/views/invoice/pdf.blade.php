@php
    $admin_settings = getAdminAllSetting();
    $company_settings = getCompanyAllSetting(creatorId());
@endphp
<div class="modal-body">
    <div class="col-form-label p-0">

        <div class="invoice">
            <div class="card-header border-bottom pb-2 mb-3 d-flex align-items-top justify-content-between gap-2">
                <div class="invoice-number">
                    <img src="{{ get_file(sidebar_logo()) }}" width="140px;">
                </div>
                <div id="printableArea">
                    <a class="btn btn-sm btn-primary text-white" data-bs-toggle="tooltip" data-bs-toggle="bottom"
                        title="{{ __('Download') }}" onclick="saveAsPDF()"><span class="ti ti-download"></span></a>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="mb-3">
                    <div class="invoice-billed p-3">
                        <div class="row row-gap">
                            <div class="col-md-6">
                                    <div class="mb-2">
                                        <strong class="mt-2">{{ __('Invoice ID') }} :</strong>
                                        {{ \App\Models\Invoice::invoiceNumberFormat($invoice->invoice_id) }}
                                    </div>
                                    <div class="mb-2">
                                        <strong>{{ __('Invoice Date') }} :</strong>
                                        {{ company_date_formate($invoice->issue_date) }}
                                    </div>
                                    <div class="mb-2">
                                        <strong>{{ __('Invoice') }} :</strong>
                                        @if ($invoice->status == 0)
                                            <span>{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 1)
                                            <span>{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 2)
                                            <span>{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 3)
                                            <span>{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 4)
                                            <span>{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @endif
                                    </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="float-sm-end qr-code">
                                    @if (!empty($company_settings['invoice_qr_display']) && $company_settings['invoice_qr_display'] == 'on')
                                        @if (module_is_active('Zatca'))
                                            <div class="float-sm-end">
                                                @include('zatca::zatca_qr_code', [
                                                    'invoice_id' => $invoice->id,
                                                ])
                                            </div>
                                        @else
                                            <div class="float-sm-end">
                                                {!! DNS2D::getBarcodeHTML(
                                                    route('pay.invoice', \Illuminate\Support\Facades\Crypt::encrypt($invoice->id)),
                                                    'QRCODE',
                                                    2,
                                                    2,
                                                ) !!}
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="invoice-summary">
                    <div class="invoice-title border-1 border-bottom mb-3 pb-2">
                        <h3 class="h4 mb-0">{{ __('Item List') }}</h3>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr class="thead-default">
                                    @if ($invoice->invoice_module == 'account')
                                        <th class="text-white bg-primary text-uppercase text-start">{{ __('Item') }}</th>
                                    @elseif($invoice->invoice_module == 'taskly')
                                        <th class="text-white bg-primary text-uppercase">{{ __('Project') }}</th>
                                    @endif

                                    <th class="text-white bg-primary text-uppercase">{{ __('Description') }}</th>
                                    <th class="text-white bg-primary text-uppercase">{{ __('Quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($iteams as $key => $iteam)
                                    <tr>
                                        @if ($invoice->invoice_module == 'account')
                                            <td class="text-start">{{ !empty($iteam->product()) ? $iteam->product()->name : '' }}
                                            </td>
                                        @elseif($invoice->invoice_module == 'taskly')
                                            <td>{{ !empty($iteam->product()) ? $iteam->product()->title : '' }}
                                            </td>
                                        @endif
                                        <td class="text-wrap">
                                            {{ !empty($iteam->description) ? $iteam->description : $iteam->product()->description }}
                                        </td>
                                        <td>{{ $iteam->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="invoice-total mt-3 p-3 d-flex align-items-end flex-column justify-content-end"
                        style="min-height: 200px;">
                        <h6 class="mb-0">{{ __('Customer Signature') }} </h6>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>
<script>
    function saveAsPDF() {
        var element = document.getElementById('printableArea');
        var opt = {
            margin: 0.3,
            filename: '{{ \App\Models\Invoice::invoiceNumberFormat($invoice->invoice_id, $invoice->created_by) }}',
            image: {
                type: 'jpeg',
                quality: 1
            },
            html2canvas: {
                scale: 4,
                dpi: 72,
                letterRendering: true
            },
            jsPDF: {
                unit: 'in',
                format: 'A4'
            }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
