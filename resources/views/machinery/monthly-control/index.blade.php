@extends('layouts.main')

@section('page-title', __('Monthly Control Dashboard'))
@section('page-breadcrumb')
    {{ __('Machinery') }} > {{ __('Monthly Control') }}
@endsection

@section('page-action')
    <div class="d-flex">
        @stack('addButtonHook')
        <a href="{{ route('machineries.index') }}" class="btn btn-sm btn-light border me-2">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
        </a>
        @if($isLocked)
            <button class="btn btn-sm btn-warning" onclick="unlockMonth({{ $currentMonth }}, {{ $currentYear }})">
                <i class="ti ti-lock-open"></i> {{ __('Unlock Month') }}
            </button>
        @else
            <button class="btn btn-sm btn-success" onclick="lockMonth({{ $currentMonth }}, {{ $currentYear }})">
                <i class="ti ti-lock"></i> {{ __('Lock Month') }}
            </button>
        @endif
    </div>
@endsection

@section('content')
<div class="row">
    <!-- Period Selection Filter -->
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-end">
                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('month', __('Month'), ['class' => 'form-label']) }}
                            <select name="month" id="monthSelect" class="form-control select">
                                @foreach($months as $month)
                                    <option value="{{ $month['value'] }}" {{ $month['value'] == $currentMonth ? 'selected' : '' }}>
                                        {{ $month['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-12 col-12">
                        <div class="btn-box me-2">
                            {{ Form::label('year', __('Year'), ['class' => 'form-label']) }}
                            <select name="year" id="yearSelect" class="form-control select">
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ $year == $currentYear ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-auto float-end mt-4">
                        <a class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="{{ __('Load Data') }}"
                           onclick="loadMonthData()" data-original-title="{{ __('Load Data') }}">
                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12 mt-2">
        @if($isLocked)
            <div class="card border-start border-4 border-success">
                <div class="card-body bg-light">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-lock text-success fs-3"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1 text-success fw-semibold">{{ __('Month Locked') }}</h6>
                            <small class="text-muted">{{ __('Locked by') }}: {{ $lockDetails->lockedBy->name }} ({{ $lockDetails->locked_at->format('Y-m-d H:i') }})</small>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card border-start border-4 border-warning">
                <div class="card-body bg-light">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-lock-open text-warning fs-3"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1 text-warning fw-semibold">{{ __('Month Open') }}</h6>
                            <small class="text-muted">{{ __('Data can be changed. Lock month before generating billing.') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Summary Cards -->
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12 mt-3">
        <div class="row g-3">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-light p-3">
                                    <i class="ti ti-file-description text-primary fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">{{ __('Total DPRs') }}</h6>
                                <h3 class="mb-0 fw-bold text-dark">{{ $billingSummary['total_items'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-light p-3">
                                    <i class="ti ti-currency-rupee text-success fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">{{ __('Total Amount') }}</h6>
                                <h3 class="mb-0 fw-bold text-dark">₹{{ number_format($billingSummary['total_amount'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-light p-3">
                                    <i class="ti ti-clock text-warning fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">{{ __('Unbilled Items') }}</h6>
                                <h3 class="mb-0 fw-bold text-dark">{{ $unbilledSummary['total_items'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-light p-3">
                                    <i class="ti ti-users text-info fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">{{ __('Suppliers') }}</h6>
                                <h3 class="mb-0 fw-bold text-dark">{{ $unbilledSummary['total_suppliers'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12 mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 text-dark">{{ __('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12">
                        <a href="/machinery/monthly-report?month={{ $currentMonth }}&year={{ $currentYear }}" 
                           class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                            <i class="ti ti-file-text fs-3 mb-2"></i>
                            <span class="fw-medium">{{ __('View Report') }}</span>
                        </a>
                    </div>
                    @if(!$isLocked)
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12">
                            <a href="{{ route('monthly-control.lock-confirm') }}?month={{ $currentMonth }}&year={{ $currentYear }}" 
                               class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                <i class="ti ti-lock fs-3 mb-2"></i>
                                <span class="fw-medium">{{ __('Lock Month') }}</span>
                            </a>
                        </div>
                    @endif
                    @if($isLocked && ($billingSummary['total_items'] ?? 0) > 0)
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12">
                            <a href="/monthly-control/generate-billing?month={{ $currentMonth }}&year={{ $currentYear }}" 
                               class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                <i class="ti ti-file-description fs-3 mb-2"></i>
                                <span class="fw-medium">{{ __('Generate Billing') }}</span>
                            </a>
                        </div>
                    @endif
                    @if($isLocked && ($unbilledSummary['total_items'] ?? 0) > 0)
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12">
                            <a href="/monthly-control/group-bills?month={{ $currentMonth }}&year={{ $currentYear }}" 
                               class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                                <i class="ti ti-stack fs-3 mb-2"></i>
                                <span class="fw-medium">{{ __('Group Bills') }}</span>
                            </a>
                        </div>
                    @endif
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12">
                        <a href="{{ route('machinery-payment.index') }}" 
                           class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-decoration-none">
                            <i class="ti ti-cash fs-3 mb-2"></i>
                            <span class="fw-medium">{{ __('View Payments') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unbilled Items Preview -->
    @if(($unbilledSummary['total_items'] ?? 0) > 0)
        <div class="col-sm-12 col-lg-12 col-xl-12 col-md-12 mt-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-dark">{{ __('Unbilled Items Summary') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        {{ $dataTable->table(['width' => '100%']) }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('css')
    @include('layouts.includes.datatable-css')
@endpush

@push('script-page')
<script>
function loadMonthData() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = `{{ route('monthly-control.index') }}?month=${month}&year=${year}`;
}

function viewMonthlyReport() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = `/machinery/monthly-report?month=${month}&year=${year}`;
}

function lockMonth(month, year) {
    window.location.href = `{{ route('monthly-control.lock-confirm') }}?month=${month}&year=${year}`;
}

function confirmLockMonth() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    lockMonth(month, year);
}

async function unlockMonth(month, year) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: 'Unlock Month?',
        text: 'Are you sure you want to unlock this month? This will allow data changes.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, unlock it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `/monthly-control/unlock?month=${month}&year=${year}`;
        }
    });
}

function generateBilling() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = `/monthly-control/generate-billing?month=${month}&year=${year}`;
}

function groupBills() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = `/monthly-control/group-bills?month=${month}&year=${year}`;
}

function viewPayments() {
    window.location.href = '{{ route('machinery-payment.index') }}';
}
</script>
@endpush

@push('scripts')
@include('layouts.includes.datatable-js')
@if(isset($dataTable))
{{ $dataTable->scripts() }}
@endif
@endpush
