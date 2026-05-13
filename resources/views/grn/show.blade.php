@extends('layouts.main')
@section('page-title', __('GRN Details'))
@section('page-breadcrumb', __('GRN Details'))

@section('page-action')
<a href="{{ route('grn.index') }}" class="btn btn-sm btn-secondary" data-bs-toggle="tooltip" title="{{__('Back to GRN List')}}">
    <i class="ti ti-arrow-left"></i>
</a>
@permission('grn print')
<a href="{{ route('grn.print', $grn->id) }}" target="_blank" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{__('Print GRN')}}">
    <i class="ti ti-printer"></i>
</a>
@endpermission
@permission('grn delete')
<form method="POST" action="{{ route('grn.destroy', $grn->id) }}" style="display:inline-block;">
    @csrf
    @method('DELETE')
<!--    <button type="button" class="btn btn-sm btn-danger confirm-delete" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
        <i class="ti ti-trash"></i>
    </button>-->
</form>
@endpermission
@endsection

@section('content')
@php 
$isDirectGrn = $grn->isDirectGrn();
@endphp

<div class="row">
    <div class="col-xl-12">
        <!-- GRN Header Info -->
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('GRN Number') }}</label>
                            <p class="fw-bold">{{ $grn->grn_number }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('GRN Date') }}</label>
                            <p>{{ \Carbon\Carbon::parse($grn->grn_date)->format('d-m-Y') }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('GRN Type') }}</label>
                            <p>
                                @if($isDirectGrn)
                                    <span class="badge bg-success">{{ __('Direct GRN') }}</span>
                                @else
                                    <span class="badge bg-primary">{{ __('Against PO') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Status') }}</label>
                            <p>
                                @if($grn->status == 'Completed')
                                    <span class="badge bg-success">{{ __('Completed') }}</span>
                                @else
                                    <span class="badge bg-info">{{ __('Partial') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Assigned To --}}
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">{{ __('Assigned To') }}</label>
                            <div class="mt-1">
                                @if(isset($assignedUsers) && $assignedUsers->isNotEmpty())
                                    @foreach($assignedUsers as $user)
                                        <span class="badge bg-primary">{{ $user->name }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">No users assigned</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO Details (for PO-based GRN) -->
                @if(!$isDirectGrn)
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('PO Number') }}</label>
                            <p>
                                @if($grn->purchaseOrder)
                                <a href="{{ route('purchase-order.show', $grn->purchaseOrder->id) }}" target="_blank">
                                    {{ $grn->purchaseOrder->po_number }}
                                </a>
                                @else
                                -
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('PO Date') }}</label>
                            <p>{{ $grn->purchaseOrder ? \Carbon\Carbon::parse($grn->purchaseOrder->po_date)->format('d-m-Y') : '-' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Supplier') }}</label>
                            <p>{{ $grn->supplier ? $grn->supplier->name : '-' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Site') }}</label>
                            <p>{{ $grn->site ? $grn->site->name : '-' }}</p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Direct GRN Details -->
                @if($isDirectGrn)
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Supplier') }}</label>
                            <p>{{ $grn->supplier ? $grn->supplier->name : '-' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Site') }}</label>
                            <p>{{ $grn->site ? $grn->site->name : '-' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Supplier Invoice Number') }}</label>
                            <p>{{ $grn->supplier_invoice_number ?: '-' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Supplier Invoice Date') }}</label>
                            <p>{{ $grn->supplier_invoice_date ? \Carbon\Carbon::parse($grn->supplier_invoice_date)->format('d-m-Y') : '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Tax Type') }}</label>
                            <p>{{ $grn->tax_type === 'igst' ? 'IGST' : 'CGST/SGST' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Total Taxable Value') }}</label>
                            <p class="fw-bold">{{ number_format($grn->total_taxable_value, 2) }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Total Tax') }}</label>
                            <p class="fw-bold">{{ number_format($grn->total_tax, 2) }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Total Amount') }}</label>
                            <p class="fw-bold text-primary">{{ number_format($grn->total_amount, 2) }}</p>
                        </div>
                    </div>
                </div>

                @if($grn->tax_type === 'igst')
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Total IGST') }}</label>
                            <p>{{ number_format($grn->total_igst, 2) }}</p>
                        </div>
                    </div>
                </div>
                @else
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Total CGST') }}</label>
                            <p>{{ number_format($grn->total_cgst, 2) }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Total SGST') }}</label>
                            <p>{{ number_format($grn->total_sgst, 2) }}</p>
                        </div>
                    </div>
                </div>
                @endif
                @endif

                <!-- Common Details -->
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Received By') }}</label>
                            <p>{{ $grn->received_by ?: '-' }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Created By') }}</label>
                            <p>{{ $grn->creator ? $grn->creator->name : '-' }}</p>
                        </div>
                    </div>
                    @if($grn->vehicle_number)
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Vehicle Number') }}</label>
                            <p>{{ $grn->vehicle_number }}</p>
                        </div>
                    </div>
                    @endif
                    @if($grn->gate_entry_number)
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Gate Entry Number') }}</label>
                            <p>{{ $grn->gate_entry_number }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                @if($grn->delivery_challan_number)
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">{{ __('Delivery Challan Number') }}</label>
                            <p>{{ $grn->delivery_challan_number }}</p>
                        </div>
                    </div>
                </div>
                @endif

                @if($grn->description)
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">{{ __('Description') }}</label>
                            <p>{{ $grn->description }}</p>
                        </div>
                    </div>
                </div>
                @endif

                @if($grn->remarks)
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">{{ __('Remarks') }}</label>
                            <p>{{ $grn->remarks }}</p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- File Attachments -->
                @if($grn->delivery_challan_file || $grn->reference_file)
                <div class="row mt-3">
                    @if($grn->delivery_challan_file)
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ __('Delivery Challan File') }}</label>
                            <p>
                                <a href="{{ asset($grn->delivery_challan_file) }}" target="_blank" class="btn btn-sm btn-info">
                                    <i class="ti ti-file"></i> {{ __('View Delivery Challan') }}
                                </a>
                            </p>
                        </div>
                    </div>
                    @endif
                    @if($grn->reference_file)
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">{{ __('Reference File') }}</label>
                            <p>
                                <a href="{{ asset($grn->reference_file) }}" target="_blank" class="btn btn-sm btn-info">
                                    <i class="ti ti-file"></i> {{ __('View Reference File') }}
                                </a>
                            </p>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <!-- GRN Items -->
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">{{ __('GRN Items') }}</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th class="text-end">{{ __('Ordered Qty') }}</th>
                                <th class="text-end">{{ __('Received Qty') }}</th>
                                <th class="text-end">{{ __('Accepted Qty') }}</th>
                                <th class="text-end">{{ __('Rejected Qty') }}</th>
                                @if($isDirectGrn)
                                <th class="text-end">{{ __('Price') }}</th>
                                <th class="text-end">{{ __('Tax Amount') }}</th>
                                <th class="text-end">{{ __('Subtotal') }}</th>
                                @endif
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($grn->items as $item)
                            <tr>
                                <td>{{ $item->material ? $item->material->name : 'N/A' }}</td>
                                <td>{{ $item->material && $item->material->unit ? $item->material->unit->name : ($item->poItem->unit ?? '-') }}</td>
                                <td class="text-end">{{ number_format($item->ordered_qty, 2) }}</td>
                                <td class="text-end">{{ number_format($item->received_qty, 2) }}</td>
                                <td class="text-end">
                                    <span class="badge bg-success">{{ number_format($item->accepted_qty, 2) }}</span>
                                </td>
                                <td class="text-end">
                                    @if($item->rejected_qty > 0)
                                        <span class="badge bg-danger">{{ number_format($item->rejected_qty, 2) }}</span>
                                    @else
                                        {{ number_format($item->rejected_qty, 2) }}
                                    @endif
                                </td>
                                @if($isDirectGrn)
                                <td class="text-end">{{ number_format($item->price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->tax_amount, 2) }}</td>
                                <td class="text-end">{{ number_format($item->subtotal, 2) }}</td>
                                @endif
                                <td>{{ $item->remarks ?: '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isDirectGrn ? 10 : 7 }}" class="text-center">{{ __('No items found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="3">{{ __('Total') }}</th>
                                <th class="text-end">{{ number_format($grn->total_received_qty, 2) }}</th>
                                <th class="text-end">{{ number_format($grn->total_accepted_qty, 2) }}</th>
                                <th class="text-end">{{ number_format($grn->total_rejected_qty, 2) }}</th>
                                @if($isDirectGrn)
                                <th></th>
                                <th class="text-end">{{ number_format($grn->items->sum('tax_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($grn->items->sum('subtotal'), 2) }}</th>
                                @endif
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
