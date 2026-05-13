@extends('layouts.main')

@section('page-title', __('Indent Details'))
@section('page-breadcrumb', __('Indents'))

@section('page-action')
<a href="{{ route('indent.index') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{ __('Back to Indents') }}">
    <i class="ti ti-arrow-left"></i>
</a>
@endsection

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    {{ __('Indent') }}: {{ $indent->indent_number }}
                </h5>

                <span class="badge px-3 py-2 fs-6 
                    bg-{{ $indent->status == 'Open' ? 'success' : ($indent->status == 'Partially Closed' ? 'warning text-dark' : 'danger') }}">
                    {{ __($indent->status) }}
                </span>
            </div>

            <div class="card-body">
                <div class="row gy-4">

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Indent Date') }}</small>
                        <div class="fw-bold">
                            {{ \Carbon\Carbon::parse($indent->indent_date)->format('d M Y') }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Site') }}</small>
                        <div class="fw-bold">
                            {{ $indent->site->name ?? '-' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Total Amount') }}</small>
                        <div class="fw-bold text-primary">
                            {{ currency_format_with_sym_indian($indent->total_amount) }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Created By') }}</small>
                        <div>{{ $indent->creator->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Created At') }}</small>
                        <div>{{ $indent->created_at->format('d M Y, h:i A') }}</div>
                    </div>

                    @if($indent->delivery_date)
                    <div class="col-md-4">
                        <small class="text-muted">{{ __('Delivery Date') }}</small>
                        <div class="fw-semibold text-danger">
                            {{ \Carbon\Carbon::parse($indent->delivery_date)->format('d M Y') }}
                        </div>
                    </div>
                    @endif

                </div>

                <div class="row mt-3">

    {{-- Description --}}
    @if($indent->description)
    <div class="col-md-6 mb-3">
        <div class="p-3 border rounded h-100 bg-light">
            <small class="text-muted">{{ __('Description') }}</small>
            <p class="mb-0 mt-1">{{ $indent->description }}</p>
        </div>
    </div>
    @endif


    {{-- Remark --}}
    @if($indent->remark)
    <div class="col-md-6 mb-3">
        <div class="p-3 border rounded h-100 bg-light">
            <small class="text-muted">{{ __('Remark') }}</small>
            <p class="mb-0 mt-1">{{ $indent->remark }}</p>
        </div>
    </div>
    @endif


    {{-- Assigned Users --}}
    @if($indent->assign_to)
    @php
        $assignedUsers = \App\Models\User::whereIn('id', explode(',', $indent->assign_to))->get();
    @endphp
    <div class="col-md-6 mb-3">
        <div class="p-3 border rounded h-100">
            <small class="text-muted">{{ __('Assigned To') }}</small>
            <div class="mt-2">
                @foreach($assignedUsers as $user)
                    <span class="badge bg-info text-dark me-1 mb-1">
                        <i class="ti ti-user"></i> {{ $user->name }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
    @endif


    {{-- Reference File --}}
    @if(!empty($indent->reference_file) && file_exists(public_path($indent->reference_file)))
    <div class="col-md-6 mb-3">
        <div class="p-3 border rounded h-100">
            <small class="text-muted">{{ __('Reference File') }}</small>
            <div class="mt-2">
                <a href="{{ asset($indent->reference_file) }}" target="_blank"
                   class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-file-download"></i> {{ __('Download File') }}
                </a>
            </div>
        </div>
    </div>
    @endif

</div>

            </div>
        </div>
    </div>
</div>


{{-- ======================= INDENT ITEMS ======================= --}}

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Indent Items') }}</h5>
            </div>
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Subtotal') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($indent->items as $item)
                            <tr>
                                <td>{{ $item->material->name ?? '-' }}</td>
                                <td>{{ number_format($item->quantity, 2) }}</td>
                                <td>{{ $item->unit }}</td>
                                <td>{{ currency_format_with_sym_indian($item->price) }}</td>
                                <td class="fw-semibold text-primary">
                                    {{ currency_format_with_sym_indian($item->subtotal) }}
                                </td>
                                <td>{{ $item->remarks ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>

                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold fs-6">
                                    {{ __('Grand Total') }}
                                </td>
                                <td class="fw-bold text-success fs-6">
                                    {{ currency_format_with_sym_indian($indent->total_amount) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>

                    </table>
                </div>

            </div>
        </div>
    </div>
</div>


{{-- ======================= PURCHASE ORDERS ======================= --}}

@if($indent->purchaseOrders->count() > 0)
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Related Purchase Orders') }}</h5>
            </div>
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('PO Number') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Supplier') }}</th>
                                <th>{{ __('Total Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($indent->purchaseOrders as $po)
                            <tr>
                                <td>{{ $po->po_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($po->po_date)->format('d M Y') }}</td>
                                <td>{{ $po->supplier->name ?? '-' }}</td>
                                <td>{{ currency_format_with_sym_indian($po->grand_total) }}</td>
                                <td>
                                    <span class="badge px-3 py-2 
                                        bg-{{ $po->status == 'Pending' ? 'warning text-dark' :
                                             ($po->status == 'Approved' ? 'success' :
                                             ($po->status == 'Completed' ? 'info' : 'danger')) }}">
                                        {{ __($po->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('purchase-order.show', $po->id) }}" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endif

@endsection