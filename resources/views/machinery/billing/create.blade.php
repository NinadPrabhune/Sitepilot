@extends('layouts.main')

@section('page-title', __('Create Machinery Bill - ') . Carbon\Carbon::create($year, $month, 1)->format('F Y'))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('machinery.billing.index', ['month' => $month, 'year' => $year]) }}">{{ __('Billing') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="ti ti-file-description"></i> 
                        {{ __('Create Machinery Bill') }}
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Period Information -->
                    <div class="alert alert-info">
                        <strong>{{ __('Billing Period') }}:</strong> 
                        {{ Carbon\Carbon::create($year, $month, 1)->format('F Y') }}
                        <br>
                        <small>{{ __('Only unbilled items for locked month are shown') }}</small>
                    </div>

                    <!-- Supplier Selection -->
                    <form method="POST" action="{{ route('machinery.billing.review') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <input type="hidden" name="year" value="{{ $year }}">
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6>{{ __('Select Suppliers to Bill') }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th width="30">
                                                    <input type="checkbox" id="selectAllSuppliers" onchange="toggleAllSuppliers()">
                                                </th>
                                                <th>{{ __('Supplier') }}</th>
                                                <th>{{ __('Items') }}</th>
                                                <th>{{ __('Hours') }}</th>
                                                <th>{{ __('Diesel (L)') }}</th>
                                                <th>{{ __('Amount') }}</th>
                                                <th>{{ __('View Items') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($unbilledSummary['suppliers'] as $supplier)
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="supplier_ids[]" value="{{ $supplier['supplier_id'] }}" 
                                                               class="supplier-checkbox" onchange="updateSelectedCount()">
                                                    </td>
                                                    <td>{{ $supplier['supplier_name'] }}</td>
                                                    <td>{{ $supplier['items_count'] }}</td>
                                                    <td>{{ number_format($supplier['total_hours'], 2) }}</td>
                                                    <td>{{ number_format($supplier['total_diesel'], 2) }}</td>
                                                    <td class="text-end fw-bold">₹{{ number_format($supplier['total_amount'], 2) }}</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                onclick="viewItems({{ $supplier['supplier_id'] }})">
                                                            <i class="ti ti-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">
                                                        {{ __('No unbilled items available') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-light">
                                                <th colspan="2">{{ __('Total') }}</th>
                                                <th>{{ $unbilledSummary['total_items'] }}</th>
                                                <th>{{ number_format($unbilledSummary['suppliers']->sum('total_hours'), 2) }}</th>
                                                <th>{{ number_format($unbilledSummary['suppliers']->sum('total_diesel'), 2) }}</th>
                                                <th class="text-end fw-bold">₹{{ number_format($unbilledSummary['total_amount'], 2) }}</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label for="remarks">{{ __('Remarks') }}</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                    placeholder="{{ __('Enter any remarks for this billing...') }}"></textarea>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row">
                            <div class="col-md-6">
                                <a href="{{ route('machinery.billing.index', ['month' => $month, 'year' => $year]) }}" 
                                   class="btn btn-secondary w-100">
                                    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
                                </a>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-success w-100" id="reviewBtn" disabled>
                                    <i class="ti ti-file-text"></i> {{ __('Review Selected') }}
                                    <span id="selectedCount">(0)</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-page')
<script>
function toggleAllSuppliers() {
    const selectAll = document.getElementById('selectAllSuppliers');
    const checkboxes = document.querySelectorAll('.supplier-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.supplier-checkbox:checked');
    const count = checkboxes.length;
    const reviewBtn = document.getElementById('reviewBtn');
    const selectedCount = document.getElementById('selectedCount');
    
    selectedCount.textContent = `(${count})`;
    reviewBtn.disabled = count === 0;
}

function viewItems(supplierId) {
    const month = {{ $month }};
    const year = {{ $year }};
    window.location.href = `/machinery/billing/items?month=${month}&year=${year}&supplier_id=${supplierId}`;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>
@endpush
