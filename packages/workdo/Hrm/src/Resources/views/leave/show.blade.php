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
