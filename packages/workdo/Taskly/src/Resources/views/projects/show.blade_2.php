@extends('layouts.main')
@php
if ($project->type == 'project') {
$name = 'Project';
} else {
$name = 'Project Template';
}
@endphp
@section('page-title')
{{ __($name . ' Detail') }}
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/plugins/dropzone.css') }}" type="text/css" />
<link rel="stylesheet" href="{{ asset('packages/workdo/Taskly/src/Resources/assets/css/custom.css') }}" type="text/css" />
<style>

.scrollable-container {
    max-height: 600px;   /* increase height */
    overflow-y: auto;    /* vertical scroll only */
    overflow-x: hidden;  /* prevent horizontal scroll */
}

.scrollable-container::-webkit-scrollbar {
    width: 6px;
}
.scrollable-container::-webkit-scrollbar-thumb {
    background-color: #888;
    border-radius: 3px;
}
.scrollable-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}


</style>


@endpush
@section('page-breadcrumb')
{{ __($name . ' Detail') }}
@endsection
@section('page-action')

<div class="d-flex">
    @if ($project->type == 'project')
    @stack('addButtonHook')
    @else
    @stack('projectConvertButton')
    @endif
    
    <!-- Indent and Purchase Order Buttons -->
    @permission('indent show')
    <div class="col-sm-auto">
        <a class="btn btn-sm btn-primary me-2" 
           href="{{ route('indent.index', ['site_id' => $project->id]) }}"
           data-bs-toggle="tooltip" 
           data-bs-original-title="{{ __('Indents') }}">
            <i class="ti ti-file-invoice"></i>
        </a>
    </div>
    @endpermission
    @permission('purchase-order show')
    <div class="col-sm-auto">
        <a class="btn btn-sm btn-primary me-2" 
           href="{{ route('purchase-order.index', ['site_id' => $project->id]) }}"
           data-bs-toggle="tooltip" 
           data-bs-original-title="{{ __('Purchase Orders') }}">
            <i class="ti ti-shopping-cart"></i>
        </a>
    </div>
    @endpermission
    @permission('grn show')
    <div class="col-sm-auto">
        <a class="btn btn-sm btn-primary me-2" 
           href="{{ route('grn.index', ['site_id' => $project->id]) }}"
           data-bs-toggle="tooltip" 
           data-bs-original-title="{{ __('GRN') }}">
            <i class="ti ti-package"></i>
        </a>
    </div>
    @endpermission
    
    
    
    <!--    <div class="col-md-auto  pb-3">
            <a href="#" class="btn btn-sm  align-items-center cp_link bg-primary me-2"
                data-link="{{ route('project.shared.link', [\Illuminate\Support\Facades\Crypt::encrypt($project->id)]) }}"
                data-toggle="tooltip" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Copy') }}">
                <span class="btn-inner--text text-white">
                    <i class="ti ti-copy"></i></span>
            </a>
        </div>-->
    @permission('project setting')
    @php
    $title =
    module_is_active('ProjectTemplate') && $project->type == 'template'
    ? __('Shared Project Template Settings')
    : __('Shared Project Settings');
    @endphp
    <!--        <div class="col-sm-auto">
                <a href="#" class="btn btn-sm me-2 btn-primary" data-title="{{ $title }}"
                    data-ajax-popup="true" data-size="md" data-bs-toggle="tooltip"
                    data-bs-original-title="{{ __('Shared Project Setting') }}"
                    data-url="{{ route('project.setting', [$project->id]) }}">
                    <i class="ti ti-settings"></i>
                </a>
            </div>-->
    @endpermission
    @permission('task manage')
    <!--        <div class="col-sm-auto">
                <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="{{ route('projects.calendar',[$project->id]) }}"
                    data-bs-original-title="{{ __('Calendar') }}">
                    <i class="ti ti-calendar"></i>
                </a>
            </div>-->
    @endpermission
    <!--        <div class="col-sm-auto">
                <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="{{ route('projects.gantt', [$project->id]) }}"
                    data-bs-original-title="{{ __('Gantt Chart') }}">
                    <i class="ti ti-chart-bar"></i>
                </a>
            </div>-->
    {{-- @permission('project tracker manage') --}}
    {{-- @if (module_is_active('TimeTracker'))
            <!--  <div class="col-sm-auto">
                <a href="{{ route('projecttime.tracker', [$project->id]) }}"  class="btn btn-xs btn-primary btn-icon-only width-auto ">{{ __('Tracker') }}</a>
</div>-->
@endif --}}
{{-- @endpermission --}}
@permission('project finance manage')
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="{{ route('projects.proposal', [$project->id]) }}"
                data-bs-original-title="{{ __('Finance') }}">
                <i class="ti ti-file-analytics"></i>
            </a>
        </div>-->
@endpermission
@if (module_is_active('Procurement'))
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="{{ route('rfx.index') }}"
                data-bs-original-title="{{ __('RFx') }}">
                <i class="ti ti-clipboard"></i>
            </a>
        </div>-->
@endif
@permission('bug manage')
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="{{ route('projects.bug.report', [$project->id]) }}"
                data-bs-original-title="{{ __('Bug Report') }}">
                <i class="ti ti-bug"></i>
            </a>
        </div>-->
@endpermission
@permission('task manage')
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm btn-primary" data-bs-toggle="tooltip" href="{{ route('projects.task.board', [$project->id]) }}"
                data-bs-original-title="{{ __('Task Board') }}">
                <i class="ti ti-layout-kanban"></i>
            </a>
        </div>-->
@endpermission





@permission('purchase-invoice manage')
<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="{{ route('purchase-invoice.index') }}"
       data-bs-toggle="tooltip" 
       data-bs-original-title="{{ __('Purchase Invoice') }}">
        <i class="ti ti-file-invoice"></i>
    </a>
</div>
@endpermission

@permission('activity manage')
<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="{{ route('activities.index') }}"
       data-bs-toggle="tooltip" 
       data-bs-original-title="{{ __('Site Activity') }}">
        <i class="ti ti-activity"></i>
    </a>
</div>
@endpermission

@permission('man-power manage')
<!--<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="{{ route('manpower.index') }}"
       data-bs-toggle="tooltip" 
       data-bs-original-title="{{ __('Man-Power') }}">
        <i class="ti ti-users"></i>
    </a>
</div>-->
@endpermission

@permission('consumption-log manage')
<!--<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="{{ route('daily-consumption.index') }}"
       data-bs-toggle="tooltip" 
       data-bs-original-title="{{ __('Consumption Log') }}">
        <i class="ti ti-notebook"></i>
    </a>
</div>-->
@endpermission

@permission('material-transfer manage')
<!--<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="{{ route('material-transfer.index') }}"
       data-bs-toggle="tooltip" 
       data-bs-original-title="{{ __('Material Transfer') }}">
        <i class="ti ti-transfer"></i>
    </a>
</div>-->
@endpermission




</div>

@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/plugins/dropzone.min.css') }}">
<style>
    @media (max-width: 1300px) {
        .row1 {
            display: flex;
            flex-wrap: wrap;
        }
    }
</style>
@endpush
@section('content')
<div class="row row-gap mb-4 ">
    <div class="col-xxl-6 col-12">
        <div class="dashboard-card project-detail-card">
            <div class="card-inner">
                <div class="card-content">
                    <h2 class="text-primary">{{ $project->name }}</h2>
                    <p>{{ $project->description }}</p>
                    <div class="btn-wrp d-flex gap-3">
                        @permission('project edit')
                        <a href="#" class="btn btn-light" tabindex="0" data-size="lg" data-url="{{ route('projects.edit',$project->id) }}" data-ajax-popup="true" data-title="{{ __('Edit ') . $name }}" data-bs-toggle="tooltip"  title="{{__('Edit')}}" data-original-title="{{__('Edit')}}">
                            <i class="ti ti-pencil text-success"></i>
                        </a>
                        @endpermission
                        @permission('project delete')
                        {{ Form::open(['route' => ['projects.destroy', $project->id], 'class' => 'm-0']) }}
                        @method('DELETE')
                        <!--                                    <a href="#" class="btn btn-light show_confirm" tabindex="0" data-bs-toggle="tooltip" title=""
                                                            data-bs-original-title="{{__('Delete')}}" aria-label="{{__('Delete')}}"
                                                            data-confirm-yes="delete-form-{{ $project->id }}">
                                                                <i class="ti ti-trash text-danger"></i>
                                                            </a>-->
                        {{ Form::close() }}
                        @endpermission
                    </div>
                </div>
                @if ($project->type == 'project')
                <div class="status-info">
                    <div class="status-wrp">
                        <span class="d-block">{{ __('Start Date') }}:</span>
                        <p class="mb-0 text-primary">{{ company_date_formate($project->start_date) }}</p>
                    </div>
                    <div class="status-wrp">
                        <span class="d-block">{{ __('Due Date') }}:</span>
                        <p class="mb-0 text-primary">{{ company_date_formate($project->end_date) }}</p>
                    </div>
                    <div class="status-wrp">
                        <span class="d-block">{{ __('Total Members') }}:</span>
                        <p class="mb-0 text-primary">{{ (int) $project->users->count() + (int) $project->clients->count() }}</p>
                    </div>
                    <div class="status-wrp ">
                        <span class="d-block">
                            @if ($project->status == 'Finished')
                            <div class="badge bg-success p-2 f-12 text-capitalize"> {{ __('Finished') }}
                            </div>
                            @elseif($project->status == 'Ongoing')
                            <div class="badge bg-secondary p-2 f-12 text-capitalize">{{ __('Ongoing') }}
                            </div>
                            @else
                            <div class="badge bg-warning p-2 f-12 text-capitalize">{{ __('OnHold') }}
                            </div>
                            @endif
                        </span>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xxl-6 col-12">
        <div class="row dashboard-wrp">
            @if ($project->type == 'project')
            <div class="col-sm-6 col-12">
                <div class="dashboard-project-card">
                    <div class="card-inner  d-flex justify-content-between">
                        <div class="card-content">
                            <div class="theme-avtar bg-white">
                                <i class="fas fas fa-calendar-day text-danger"></i>
                            </div>
                            <h3 class="mt-3 mb-0 text-danger">{{ __('Days left') }}</h3>
                        </div>
                        <h3 class="mb-0">{{ $daysleft }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-12">
                <div class="dashboard-project-card">
                    <div class="card-inner  d-flex justify-content-between">
                        <div class="card-content">
                            <div class="theme-avtar bg-white">
                                <i class="fas fa-money-bill-alt"></i>
                            </div>
                            <h3 class="mt-3 mb-0">{{ __('Budget') }}</h3>
                        </div>
                        <h3 class="mb-0">{{ company_setting('defult_currancy') }}
                            {{ number_format($project->budget) }}</h3>
                    </div>
                </div>
            </div>
            @endif
            @php
            $class = $project->type == 'template' ? 'col-lg-6 col-6 mt-3' : 'col-lg-3 col-6 mt-3';
            @endphp
            <!--                <div class="col-sm-6 col-12">
                                <div class="dashboard-project-card">
                                    <div class="card-inner  d-flex justify-content-between">
                                        <div class="card-content">
                                            <div class="theme-avtar bg-white">
                                                <i class="ti ti-file-invoice text-danger"></i>
                                            </div>
                                        <h3 class="mt-3 mb-0">{{ __('Total Task') }}</h3>
                                        </div>
                                        <h3 class="mb-0">{{ $project->countTask() }}</h3>
                                    </div>
                                </div>
                            </div>-->
            <!--                <div class="col-sm-6 col-12">
                                <div class="dashboard-project-card">
                                    <div class="card-inner d-flex justify-content-between">
                                        <div class="card-content">
                                            <div class="theme-avtar bg-white">
                                                <i class="ti ti-message-circle-2"></i>
                                            </div>
                                            <h3 class="mt-3 mb-0">{{ __('Comment') }}</h3>
                                        </div>
                                        <h3 class="mb-0">{{ $project->countTaskComments() }}</h3>
                                    </div>
                                </div>
                            </div>-->
        </div>
    </div>



</div>

<div class="row project-detail-wrp">
    <div class="col-xxl-12 col-md-12">
        <div class="card deta-card">
            <div class="card-header p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ __('Team Members') }}
                            ({{ count($project->users) }})
                        </h5>
                    </div>
                    <div class="text-end">
                        <p class="text-muted d-sm-flex align-items-center mb-0">

                                                      <a href="#" class="btn btn-sm btn-primary"
                                                           data-ajax-popup="true" data-title="{{ __('Invite') }}"
                                                           data-bs-toggle="tooltip" data-bs-title="{{ __('Invite') }}"
                                                           data-url="{{ route('projects.invite.popup', [$project->id]) }}"><i
                                                                class="ti ti-brand-telegram"></i></a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
    <!-- Scrollable vertical container -->
    <div class="scrollable-container">
        <div class="row">
            @foreach ($project->users as $user)
                <div class="col-xxl-3 col-md-3 mb-3">
                    <div class="list-group-item p-2">
                        <div class="row align-items-center justify-content-between">
                            <div class="col-sm-auto mb-2 mb-sm-0">
                                <div class="d-flex align-items-center justify-content-center">
                                    <a href="#" class="text-start">
                                        <img alt="image" data-bs-toggle="tooltip"
                                             data-bs-placement="top"
                                             title="{{ $user->name }}"
                                             @if ($user->avatar) src="{{ get_file($user->avatar) }}" @else src="{{ get_file('avatar.png') }}" @endif
                                             class="rounded border border-primary"
                                             width="32px" height="32px">
                                    </a>
                                    <div class="px-2">
                                        <h6 class="m-0" style="font-size:0.9rem;">{{ $user->name }}</h6>
                                        <p class="text-muted mb-0" style="font-size:0.75rem;">
                                            {{ $user->email }}
                                            <span class="text-primary">
                                                - {{ (int) count($project->user_done_tasks($user->id)) }}/{{ (int) count($project->user_tasks($user->id)) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-auto text-sm-end d-flex align-items-center justify-content-center">
                                @auth('web')
                                    @if ($user->id != Auth::id())
                                        @permission('team member remove')
                                            <!-- delete form here if needed -->
                                        @endpermission
                                    @endif
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>






        </div>
    </div>
</div>
<div class="col-xxl-12 col-12">
    <div class="card invoice-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">{{ __('Last 10 Purchase Invoices') }}</h5>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice No') }}</th>
                            <th>{{ __('Invoice Date') }}</th>
                            <th>{{ __('Invoice Type') }}</th>
                            <th>{{ __('Supplier') }}</th>

                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Total Amount') }}</th>
                            <th>{{ __('Payment Status') }}</th>
                            <th>{{ __('Invoice File') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
                            <td>
                                @php
                                $map = [
                                'minor_misc_service' => ['label' => 'Minor/Misc Service', 'class' => 'badge bg-warning text-dark'],
                                'general_po' => ['label' => 'General PO', 'class' => 'badge bg-primary'],
                                ];
                                $type = $invoice->invoice_type;
                                $label = $map[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
                                $class = $map[$type]['class'] ?? 'badge bg-secondary';
                                @endphp
                                <span class="{{ $class }}">{{ $label }}</span>
                            </td>
                            <td>{{ optional($invoice->supplier)->name ?? '—' }}</td>

                            <td>{{ optional($invoice->creator)->name ?? '—' }}</td>
                            <td>{{ currency_format_with_sym($invoice->total_amount) }}</td>
                            <td>
                                @php
                                $statusMap = [
                                'unpaid' => ['label' => 'Unpaid', 'class' => 'badge bg-secondary'],
                                'paid' => ['label' => 'Paid', 'class' => 'badge bg-success'],
                                'overpaid' => ['label' => 'Overpaid', 'class' => 'badge bg-info text-dark'],
                                'partially paid' => ['label' => 'Partially Paid', 'class' => 'badge bg-warning text-dark'],
                                ];
                                $status = strtolower($invoice->payment_status);
                                $label = $statusMap[$status]['label'] ?? ucfirst($status);
                                $class = $statusMap[$status]['class'] ?? 'badge bg-secondary';
                                @endphp
                                <span class="{{ $class }}">{{ $label }}</span>
                            </td>
                            <td>
                                @if($invoice->invoice_file)
                                <a href="{{ asset('storage/' . ltrim($invoice->invoice_file, '/')) }}" target="_blank">{{ __('Download') }}</a>
                                @else
                                N/A
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
<div class="col-xxl-12 col-12">
    <div class="card activity-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">{{ __('Last 10 Activities') }}</h5>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Scope') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Completed Quantity') }}</th>
                            <th>{{ __('Unit') }}</th>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Progress') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Created At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activities as $activity)
                        @php
                        $completed = $activity->completeds->sum('completed_quantity');
                        $total = $activity->quantity;
                        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
                        @endphp
                        <tr>
                            <td>{{ $activity->title }}</td>
                            <td>{{ \Carbon\Carbon::parse($activity->date)->format('d-m-Y') }}</td>
                            <td>{{ $activity->scope }}</td>
                            <td>{{ $activity->quantity }}</td>
                            <td>{{ $completed }}</td>
                            <td>{{ $activity->unit }}</td>
                            <td>{{ ucfirst($activity->priority) }}</td>
                            <td>
                                <div class="progress_wrapper">
                                    <div class="progress" style="width: 120px">
                                        <div class="progress-bar bg-success" role="progressbar"
                                             style="width: {{ $percentage }}%;"
                                             aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="progress_labels">
                                        <div class="total_progress">
                                            <strong>{{ $percentage }}%</strong>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ optional($activity->creator)->name ?? '—' }}</td>
                            <td>{{ $activity->created_at->format('d-m-Y, h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12">
    <div class="card manpower-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">{{ __('Last 10 Manpower Records') }}</h5>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th>{{ __('Work Date') }}</th>
                            <th>{{ __('Supplier') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th>{{ __('Total Count') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Created At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($manpowers as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->work_date)->format('d-m-Y') }}</td>
                            <td>{{ optional($row->supplier)->name ?? '—' }}</td>
                            <td>{{ optional($row->site)->name ?? '—' }}</td>
                            <td>{{ $row->total_count }}</td>
                            <td>{{ optional($row->creator)->name ?? '—' }}</td>
                            <td>{{ $row->created_at->format('d-m-Y, h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12">
    <div class="card consumption-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">{{ __('Last 10 Daily Consumptions') }}</h5>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th>{{ __('Consumption No') }}</th>
                            <th>{{ __('Consumption Date') }}</th>
                            <th>{{ __('Consumption Type') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th>{{ __('Consumption File') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Created At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($consumptions as $master)
                        <tr>
                            <td>{{ $master->consumption_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($master->consumption_date)->format('d-m-Y') }}</td>
                            <td>{{ ucfirst($master->consumption_type) }}</td>
                            <td>{{ optional($master->site)->name ?? '—' }}</td>
                            <td>
                                @if($master->consumption_file)
                                <a href="{{ asset('storage/' . ltrim($master->consumption_file, '/')) }}" target="_blank">{{ __('Download') }}</a>
                                @else
                                N/A
                                @endif
                            </td>
                            <td>{{ optional($master->creator)->name ?? '—' }}</td>
                            <td>{{ $master->created_at->format('d-m-Y, h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12">
    <div class="card">
        <div class="card-header p-3">
            <h5 class="mb-0">{{ __('Materials Transferred (Materials Out)') }}</h5>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th>{{ __('Record No') }}</th>
                            <th>{{ __('Record Date') }}</th>
                            <th>{{ __('From Site') }}</th>
                            <th>{{ __('To Site') }}</th>
                            <th>{{ __('Total Amount') }}</th>
                            <th>{{ __('Record File') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Created At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transferredFrom as $transfer)
                        <tr>
                            <td>{{ $transfer->record_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($transfer->record_date)->format('d-m-Y') }}</td>
                            <td>{{ optional($transfer->fromSite)->name ?? '—' }}</td>
                            <td>{{ optional($transfer->toSite)->name ?? '—' }}</td>
                            <td>{{ currency_format_with_sym($transfer->total_amount) }}</td>
                            <td>
                                @if($transfer->record_file)
                                <a href="{{ asset('storage/' . ltrim($transfer->record_file, '/')) }}" target="_blank">{{ __('Download') }}</a>
                                @else
                                N/A
                                @endif
                            </td>
                            <td>{{ optional($transfer->creator)->name ?? '—' }}</td>
                            <td>{{ $transfer->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12 mt-4">
    <div class="card">
        <div class="card-header p-3">
            <h5 class="mb-0">{{ __('Materials Transferred (Materials In)') }}</h5>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th>{{ __('Record No') }}</th>
                            <th>{{ __('Record Date') }}</th>
                            <th>{{ __('From Site') }}</th>
                            <th>{{ __('To Site') }}</th>
                            <th>{{ __('Total Amount') }}</th>
                            <th>{{ __('Record File') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Created At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transferredTo as $transfer)
                        <tr>
                            <td>{{ $transfer->record_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($transfer->record_date)->format('d-m-Y') }}</td>
                            <td>{{ optional($transfer->fromSite)->name ?? '—' }}</td>
                            <td>{{ optional($transfer->toSite)->name ?? '—' }}</td>
                            <td>{{ currency_format_with_sym($transfer->total_amount) }}</td>
                            <td>
                                @if($transfer->record_file)
                                <a href="{{ asset('storage/' . ltrim($transfer->record_file, '/')) }}" target="_blank">{{ __('Download') }}</a>
                                @else
                                N/A
                                @endif
                            </td>
                            <td>{{ optional($transfer->creator)->name ?? '—' }}</td>
                            <td>{{ $transfer->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>



@endsection
@push('scripts')

@endpush
