@extends('layouts.main')

@section('page-title', __('Monthly Machinery Report - ') . Carbon\Carbon::create($year, $month, 1)->format('F Y'))
@section('breadcrumb')
    <li class="breadcrumb-item active">{{ __('Monthly Report') }}</li>
@endsection

@section('action-btn')
    @if($isLocked)
        <button class="btn btn-warning" onclick="unlockMonth()">
            <i class="ti ti-lock-open"></i> Unlock Month
        </button>
    @endif
@endsection

@section('content')
<div class="container-fluid">
    <!-- Status Banner -->
    <div class="row mb-3">
        <div class="col-12">
            @if(!$isLocked)
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="ti ti-alert-triangle me-2"></i>
                    <div>
                        <strong>{{ __('Month Status: OPEN') }}</strong><br>
                        <small>{{ __('Data can be changed. Lock month before generating billing.') }}</small>
                    </div>
                </div>
            @else
                <div class="alert alert-success d-flex align-items-center">
                    <i class="ti ti-lock me-2"></i>
                    <div>
                        <strong>{{ __('Month Status: LOCKED') }}</strong><br>
                        <small>{{ __('Data is frozen. No changes allowed.') }}</small>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Filters') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label>{{ __('Site') }}</label>
                            <select class="form-control" id="siteFilter">
                                <option value="">{{ __('All Sites') }}</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>{{ __('Status') }}</label>
                            <select class="form-control" id="statusFilter">
                                <option value="">{{ __('All Status') }}</option>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="approved">{{ __('Approved') }}</option>
                                <option value="paid">{{ __('Paid') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>{{ __('Source Type') }}</label>
                            <select class="form-control" id="sourceFilter">
                                <option value="">{{ __('All Sources') }}</option>
                                <option value="activity">{{ __('Activity') }}</option>
                                <option value="machinery_direct">{{ __('Direct Machinery') }}</option>
                                <option value="imported">{{ __('Imported') }}</option>
                                <option value="manual_adjustment">{{ __('Manual Adjustment') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="applyFilters()">
                                <i class="ti ti-search"></i> {{ __('Apply Filters') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>{{ __('Total DPRs') }}</h6>
                    <h3>{{ $summary['total_dprs'] ?? 0 }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>{{ __('Total Hours') }}</h6>
                    <h3>{{ number_format($summary['total_hours'] ?? 0, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>{{ __('Total Diesel (L)') }}</h6>
                    <h3>{{ number_format($summary['total_diesel'] ?? 0, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>{{ __('Total Amount') }}</h6>
                    <h3>₹{{ number_format($summary['total_amount'] ?? 0, 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Integrity Indicators -->
    @if($summary['issues'])
        <div class="row mb-3">
            <div class="col-12">
                <div class="card bg-danger text-white">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="ti ti-alert-triangle"></i> 
                            {{ __('Data Integrity Issues') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('Issue') }}</th>
                                        <th>{{ __('Meaning') }}</th>
                                        <th>{{ __('Count') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summary['issues'] as $issue)
                                        <tr>
                                            <td>
                                                <span class="badge bg-{{ $issue['type'] }}">
                                                    @if($issue['type'] === 'missing_dpr')
                                                        <i class="ti ti-file-off"></i> {{ __('Missing DPR') }}
                                                    @elseif($issue['type'] === 'diesel_mismatch')
                                                        <i class="ti ti-droplet"></i> {{ __('Diesel Mismatch') }}
                                                    @elseif($issue['type'] === 'negative_amount')
                                                        <i class="ti ti-alert-triangle"></i> {{ __('Negative Amount') }}
                                                    @else
                                                        <i class="ti ti-alert-circle"></i> {{ $issue['title'] }}
                                                    @endif
                                                </span>
                                            </td>
                                            <td>{{ $issue['description'] }}</td>
                                            <td>{{ $issue['count'] }}</td>
                                            <td>
                                                @if($issue['action'] === 'review')
                                                    <button class="btn btn-sm btn-warning" onclick="reviewIssue({{ $issue['id'] }})">
                                                        {{ __('Review') }}
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-info" onclick="viewIssue({{ $issue['id'] }})">
                                                        {{ __('View') }}
                                                    </button>
                                                @endif
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

    <!-- Machinery List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Machinery Report') }}</h5>
                    <div class="float-end">
                        @if($isLocked && !$summary['billed'])
                            <button class="btn btn-success" onclick="createBill()">
                                <i class="ti ti-file-description"></i> {{ __('Create Bill') }}
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Machinery') }}</th>
                                    <th>{{ __('Site') }}</th>
                                    <th>{{ __('DPR Count') }}</th>
                                    <th>{{ __('Total Hours') }}</th>
                                    <th>{{ __('Diesel (L)') }}</th>
                                    <th>{{ __('Total Amount') }}</th>
                                    <th>{{ __('Payment Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($machineryData as $data)
                                    <tr>
                                        <td>{{ $data['machinery']->name }}</td>
                                        <td>{{ $data['site']->name }}</td>
                                        <td>{{ $data['dpr_count'] }}</td>
                                        <td>{{ number_format($data['total_hours'], 2) }}</td>
                                        <td>{{ number_format($data['total_diesel'], 2) }}</td>
                                        <td class="text-end fw-bold">₹{{ number_format($data['total_amount'], 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $data['payment_status_color'] }}">
                                                {{ $data['payment_status'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewDetails({{ $data['machinery']->id }})">
                                                <i class="ti ti-eye"></i>
                                            </button>
                                            @if($isLocked && !$data['billed'])
                                                <button class="btn btn-sm btn-success" onclick="createBillForMachinery({{ $data['machinery']->id }})">
                                                    <i class="ti ti-file-plus"></i> {{ __('Create Bill') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            {{ __('No machinery data found for this period') }}
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
</div>
@endsection

@push('script-page')
<script>
// Global variables
let month = {{ $month }};
let year = {{ $year }};
let isLocked = {{ $isLocked ? 'true' : 'false' }};

function applyFilters() {
    const siteId = document.getElementById('siteFilter').value;
    const status = document.getElementById('statusFilter').value;
    const source = document.getElementById('sourceFilter').value;
    
    const params = new URLSearchParams();
    params.set('month', month);
    params.set('year', year);
    
    if (siteId) params.set('site_id', siteId);
    if (status) params.set('status', status);
    if (source) params.set('source_type', source);
    
    window.location.href = `{{ route('machinery.monthly-report.index') }}?${params.toString()}`;
}

async function unlockMonth() {
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

function createBill() {
    window.location.href = `/machinery/billing/create?month=${month}&year=${year}`;
}

function createBillForMachinery(machineryId) {
    window.location.href = `/machinery/billing/create?month=${month}&year=${year}&machinery_id=${machineryId}`;
}

function viewDetails(machineryId) {
    window.location.href = `/machinery/monthly-report/details?month=${month}&year=${year}&machinery_id=${machineryId}`;
}

function reviewIssue(issueId) {
    window.location.href = `/machinery/monthly-report/review-issue?month=${month}&year=${year}&issue_id=${issueId}`;
}

function viewIssue(issueId) {
    window.location.href = `/machinery/monthly-report/view-issue?month=${month}&year=${year}&issue_id=${issueId}`;
}
</script>
@endpush
