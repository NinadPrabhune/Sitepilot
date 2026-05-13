@extends('layouts.main')

@section('page-title', __('Machinery Billing - ') . Carbon\Carbon::create($year, $month, 1)->format('F Y'))
@section('breadcrumb')
    <li class="breadcrumb-item active">{{ __('Billing') }}</li>
@endsection

@section('action-btn')
    @if($unbilledSummary['total_items'] > 0)
        <a href="{{ route('machinery.billing.create', ['month' => $month, 'year' => $year]) }}" class="btn btn-success">
            <i class="ti ti-plus"></i> {{ __('Create Bill') }}
        </a>
    @endif
@endsection

@section('content')
<div class="container-fluid">
    <!-- Status Banner -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="ti ti-info-circle"></i>
                <strong>{{ __('Billing Period') }}:</strong> 
                {{ Carbon\Carbon::create($year, $month, 1)->format('F Y') }}
                <span class="float-end">{{ __('Month must be locked for billing operations') }}</span>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>{{ __('Total Bills') }}</h6>
                    <h3>{{ $bills->count() }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>{{ __('Total Billed') }}</h6>
                    <h3>₹{{ number_format($bills->sum('total_amount'), 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>{{ __('Unbilled Items') }}</h6>
                    <h3>{{ $unbilledSummary['total_items'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>{{ __('Suppliers') }}</h6>
                    <h3>{{ $unbilledSummary['total_suppliers'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Generated Bills') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Bill ID') }}</th>
                                    <th>{{ __('Supplier') }}</th>
                                    <th>{{ __('Period') }}</th>
                                    <th>{{ __('DPRs') }}</th>
                                    <th>{{ __('Hours') }}</th>
                                    <th>{{ __('Diesel (L)') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bills as $bill)
                                    <tr>
                                        <td>#{{ str_pad($bill->id, 6, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $bill->supplier->name }}</td>
                                        <td>{{ $bill->from_date->format('d M') }} - {{ $bill->to_date->format('d M') }}</td>
                                        <td>{{ $bill->total_dprs }}</td>
                                        <td>{{ number_format($bill->total_hours, 2) }}</td>
                                        <td>{{ number_format($bill->total_diesel, 2) }}</td>
                                        <td class="text-end">₹{{ number_format($bill->total_amount, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $bill->status === 'draft' ? 'secondary' : ($bill->status === 'submitted' ? 'warning' : ($bill->status === 'approved' ? 'info' : 'success')) }}">
                                                {{ ucfirst($bill->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('machinery.billing.show', $bill->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('View') }}">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                @if($bill->status === 'draft')
                                                    <button class="btn btn-sm btn-warning" onclick="editBill({{ $bill->id }})" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                        <i class="ti ti-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteBill({{ $bill->id }})" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                @endif
                                                @if($bill->status === 'approved' && !$bill->payment_request_id)
                                                    <button class="btn btn-sm btn-success" onclick="createPayment({{ $bill->id }})" data-bs-toggle="tooltip" title="{{ __('Create Payment') }}">
                                                        <i class="ti ti-cash"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            {{ __('No bills generated for this period') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unbilled Items Preview -->
    @if($unbilledSummary['total_items'] > 0)
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Unbilled Items') }} ({{ $unbilledSummary['total_suppliers'] }} {{ __('Suppliers') }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                        <tr>
                                            <th>{{ __('Supplier') }}</th>
                                            <th>{{ __('Items') }}</th>
                                            <th>{{ __('Hours') }}</th>
                                            <th>{{ __('Diesel (L)') }}</th>
                                            <th>{{ __('Amount') }}</th>
                                            <th>{{ __('Action') }}</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    @foreach($unbilledSummary['suppliers'] as $supplier)
                                        <tr>
                                            <td>{{ $supplier['supplier_name'] }}</td>
                                            <td>{{ $supplier['items_count'] }}</td>
                                            <td>{{ number_format($supplier['total_hours'], 2) }}</td>
                                            <td>{{ number_format($supplier['total_diesel'], 2) }}</td>
                                            <td class="text-end">₹{{ number_format($supplier['total_amount'], 2) }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="createBillForSupplier({{ $supplier['supplier_id'] }})">
                                                    {{ __('Create Bill') }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <th>{{ __('Total') }}</th>
                                        <th>{{ $unbilledSummary['total_items'] }}</th>
                                        <th>{{ number_format($unbilledSummary['suppliers']->sum('total_hours'), 2) }}</th>
                                        <th>{{ number_format($unbilledSummary['suppliers']->sum('total_diesel'), 2) }}</th>
                                        <th class="text-end">₹{{ number_format($unbilledSummary['total_amount'], 2) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('script-page')
<script>
function createBillForSupplier(supplierId) {
    const month = {{ $month }};
    const year = {{ $year }};
    window.location.href = `/machinery/billing/create?month=${month}&year=${year}&supplier_id=${supplierId}`;
}

async function createPayment(billId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Create Payment Request?',
        text: 'Create payment request for this bill?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, create it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `/payment-request/create-from-bill/${billId}`;
        }
    });
}

function editBill(billId) {
    window.location.href = `/machinery/billing/${billId}/edit`;
}

async function deleteBill(billId) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Delete Bill?',
        text: 'Are you sure you want to delete this bill?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/machinery/billing/${billId}`;
            form.innerHTML = '<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="{{ csrf_token() }}">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
@endpush
