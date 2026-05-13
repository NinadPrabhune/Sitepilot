@extends('layouts.main')
@section('page-title')
{{__('Manage Purchase Invoices')}}
@endsection
@push('script-page')
@endpush
@section('page-breadcrumb')
{{__('Purchase Invoices')}}
@endsection
@section('page-action')
<div class="d-flex">
    @stack('addButtonHook')
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
       </a>
<!--    <button id="exportSelectedInvoices" class="btn btn-sm btn-primary me-2">
        <i class="ti ti-download"></i> {{ __('Export Selected') }}
    </button>-->
    @permission('purchase-invoice create')
<!--    <a data-size="xl" data-url="{{ route('purchase-invoice.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Create')}}" data-title="{{__('Create Purchase Invoice')}}"  class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i>
    </a>-->
    @endpermission
</div>
@endsection
@push('css')
@include('layouts.includes.datatable-css')
@endpush
@section('content')
<div class="row">
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-end">
                    

                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                            {{ Form::date(
                            'start_date',
                            request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                            ['class' => 'form-control', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                            {{ Form::date(
                            'end_date',
                            request('end_date') ?? \Carbon\Carbon::now()->toDateString(),
                            ['class' => 'form-control', 'placeholder' => 'Select Date']
                            ) }}
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('supplier_filter', __('Supplier'), ['class' => 'form-label']) }}
                            <select id="supplier_filter" class="form-select" name="supplier_filter">
                                <option value="">{{ __('All Suppliers') }}</option>
                                @foreach($suppliers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                           id="applyfilter" data-original-title="{{ __('apply') }}">
                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                        </a>
                        <a href="#!" class="btn btn-sm btn-danger " data-bs-toggle="tooltip"
                           title="{{ __('Reset') }}" id="clearfilter" data-original-title="{{ __('Reset') }}">
                            <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off "></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    {{ $dataTable->table(['width' => '100%']) }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
@include('layouts.includes.datatable-js')
{{ $dataTable->scripts() }}
<script>
//$(document).on('click', '.request-payment-btn', function (e) {
//    e.preventDefault();
//    let invoiceId = $(this).data('id');
//
//    if (confirm("Are you sure you want to request payment for this invoice?")) {
//        
//        
//        $.ajax({
//            url: "{{ route('payment-request.update') }}",
//            type: "POST",
//            data: {
//                _token: "{{ csrf_token() }}",
//                invoice_id: invoiceId
//            },
//            success: function (response) {
////                alert("Payment request submitted successfully.");
//
//                location.reload();
//            },
//            error: function (xhr) {
//                
//                alert("Error: " + xhr.responseText);
//            }
//        });
//
//        
//        
//        
//    }
//});

$(document).on('click', '.request-payment-btn', function (e) {
    e.preventDefault();
    var invoiceId = $(this).data("id");

    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });

    swalWithBootstrapButtons.fire({
        title: "Request Payment?",
        text: "Do you want to send a payment request for this invoice?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Example AJAX call to your route
            $.ajax({
                url: "/payment-request/update", // adjust route if using {id}
                type: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    invoice_id: invoiceId
                },
                success: function (response) {
                    swalWithBootstrapButtons.fire(
                        'Requested!',
                        'Payment request has been sent.',
                        'success'
                    );
                    // Optionally reload or update UI
                    location.reload();
                },
                error: function () {
                    swalWithBootstrapButtons.fire(
                        'Error!',
                        'Something went wrong.',
                        'error'
                    );
                }
            });
        }
    });
});

// ============================================
// Supplier & Date Filter + Export Functionality
// ============================================

// Reload table when supplier filter changes
$('#supplier_filter').change(function(){
    $('#purchase-invoice-table').DataTable().ajax.reload();
});

// Handle select all checkbox (using trait's checkbox class)
$(document).on('change','#select-all-purchase-invoice-checkbox',function(){
    $('.purchase-invoice-checkbox').prop('checked',$(this).prop('checked'));
});

// Handle Export Selected button
$(document).on('click','#exportSelectedInvoices',function(){
    let ids = [];

    $('.purchase-invoice-checkbox:checked').each(function(){
        ids.push($(this).val());
    });

    if(ids.length === 0){
        alert('Please select at least one invoice');
        return;
    }

    // Debug log - console
    
    // Debug log - Laravel log (via AJAX)
    $.ajax({
        url: "{{ route('purchase-invoice.debug-log') }}",
        type: "POST",
        data: {
            _token: "{{ csrf_token() }}",
            ids: ids,
            action: 'export_purchase_invoice'
        },
        success: function(response) {
        },
        error: function(xhr) {
        }
    });

    window.location.href = "/export-selected?model=App\\Models\\PurchaseInvoice&ids=" + ids.join(',');
});

// Update clear filter to also clear supplier
$(document).on('click', '#clearfilter', function() {
    $('input[name=start_date]').val('');
    $('input[name=end_date]').val('');
    $('#supplier_filter').val('');
    $('#purchase-invoice-table').DataTable().draw();
});

</script>


@endpush
