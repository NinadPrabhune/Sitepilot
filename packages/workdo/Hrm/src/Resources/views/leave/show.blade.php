@extends('layouts.main')

@section('page-title')
    {{ __('Leave Details') }}
@endsection

@section('page-breadcrumb')
    {{ __('Leave Details') }}
@endsection

@section('page-action')
    <a href="{{ route('leave.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        <!-- Leave Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Leave Information') }}</h5>
                <span class="badge bg-secondary">{{ __('#') . $leave->id }}</span>
            </div>
            <div class="card-body">

                <!-- First Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Employee') }}:</strong><br>
                        {{ $employee->name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Leave Type') }}:</strong><br>
                        {{ $leavetype->title ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Applied On') }}:</strong><br>
                        {{ $leave->applied_on ? company_date_formate($leave->applied_on) : __('Not Available') }}
                    </div>
                </div>

                <!-- Second Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Start Date') }}:</strong><br>
                        {{ $leave->start_date ? company_date_formate($leave->start_date) : __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('End Date') }}:</strong><br>
                        {{ $leave->end_date ? company_date_formate($leave->end_date) : __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Total Leave Days') }}:</strong><br>
                        {{ $leave->total_leave_days ?? __('Not Available') }}
                    </div>
                </div>

                <!-- Third Row -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Approved Days') }}:</strong><br>
                        {{ $leave->approved_days ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Status') }}:</strong><br>
                        @if($leave->status === 'Pending')
                            <span class="badge bg-warning">{{ $leave->status }}</span>
                        @elseif($leave->status === 'Approved')
                            <span class="badge bg-success">{{ $leave->status }}</span>
                        @elseif($leave->status === 'Reject')
                            <span class="badge bg-danger">{{ $leave->status }}</span>
                        @else
                            {{ $leave->status ?? __('Not Available') }}
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Status Reason') }}:</strong><br>
                        {{ $leave->status_reason ?? __('Not Available') }}
                    </div>
                </div>

                <!-- Leave Reason -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>{{ __('Leave Reason') }}:</strong><br>
                        {{ $leave->leave_reason ?? __('Not Available') }}
                    </div>
                </div>

            </div>
        </div>

        <!-- Date-wise Approval Status Card -->
        @if($leave->leaveDates && $leave->leaveDates->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Date-wise Approval Status') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Day') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Approved By') }}</th>
                                <th>{{ __('Approved At') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leave->leaveDates->sortBy('leave_date') as $date)
                            <tr>
                                <td>{{ company_date_formate($date->leave_date) }}</td>
                                <td>{{ \Carbon\Carbon::parse($date->leave_date)->format('l') }}</td>
                                <td>
                                    @if($date->status === 'approved')
                                        <span class="badge bg-success">{{ __('Approved') }}</span>
                                    @elseif($date->status === 'rejected')
                                        <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                    @elseif($date->status === 'pending')
                                        <span class="badge bg-warning">{{ __('Pending') }}</span>
                                    @elseif($date->status === 'cancelled')
                                        <span class="badge bg-secondary">{{ __('Cancelled') }}</span>
                                    @else
                                        <span class="badge bg-info">{{ $date->status }}</span>
                                    @endif
                                </td>
                                <td>{{ $date->approver->name ?? '-' }}</td>
                                <td>{{ $date->approved_at ? company_date_formate($date->approved_at) : '-' }}</td>
                                <td>{{ $date->remarks ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="mt-3">
                    <div class="row">
                        <div class="col-md-3">
                            <span class="badge bg-success">{{ __('Approved') }}: {{ $leave->leaveDates->where('status', 'approved')->count() }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-danger">{{ __('Rejected') }}: {{ $leave->leaveDates->where('status', 'rejected')->count() }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-warning">{{ __('Pending') }}: {{ $leave->leaveDates->where('status', 'pending')->count() }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-secondary">{{ __('Cancelled') }}: {{ $leave->leaveDates->where('status', 'cancelled')->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Additional Details Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Additional Information') }}</h5>
            </div>
            <div class="card-body">

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Workspace') }}:</strong><br>
                        {{ $leave->workspace_name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Site') }}:</strong><br>
                        {{ $leave->site_name ?? __('Not Available') }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Last Updated') }}:</strong><br>
                        {{ $leave->updated_at ? \Carbon\Carbon::parse($leave->updated_at)->format('d M Y') : __('Not Available') }}
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
