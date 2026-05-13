@extends('layouts.main')

@section('page-title', __('Machinery DPR - ') . $machinery->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('machineries.index') }}">{{ __('Machinery') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machineries.show', $machinery) }}">{{ $machinery->name }}</a></li>
    <li class="breadcrumb-item active">{{ __('Daily Progress Reports') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('machinery.dpr.create', $machinery) }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{ __('Create DPR') }}">
        <i class="ti ti-plus"></i> {{ __('Create DPR') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Daily Progress Reports') }} - {{ $machinery->name }}</h5>
                <div class="card-header-right">
                    <span class="badge bg-warning">{{ __('Direct Machinery Flow') }}</span>
                </div>
            </div>
            <div class="card-body">
                <!-- Source Type Filter -->
                <div class="mb-3">
                    <div class="btn-group">
                        <span class="btn btn-outline-secondary disabled">{{ __('Filter:') }}</span>
                        <a href="{{ route('machinery.dpr.index', $machinery) }}" 
                           class="btn btn-outline-primary {{ !request('status') ? 'active' : '' }}">
                            {{ __('All') }}
                        </a>
                        <a href="{{ route('machinery.dpr.index', array_merge([$machinery], request()->except('page', 'status') + ['status' => 'pending'])) }}" 
                           class="btn btn-outline-warning {{ request('status') == 'pending' ? 'active' : '' }}">
                            {{ __('Pending') }}
                        </a>
                        <a href="{{ route('machinery.dpr.index', array_merge([$machinery], request()->except('page', 'status') + ['status' => 'approved'])) }}" 
                           class="btn btn-outline-success {{ request('status') == 'approved' ? 'active' : '' }}">
                            {{ __('Approved') }}
                        </a>
                    </div>
                </div>

                @if($dprs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Site') }}</th>
                                    <th>{{ __('Hours') }}</th>
                                    <th>{{ __('Diesel') }}</th>
                                    <th>{{ __('Operators') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Ledger') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dprs as $dpr)
                                <tr>
                                    <td>{{ $dpr->date->format('Y-m-d') }}</td>
                                    <td>{{ $dpr->site?->name ?? '-' }}</td>
                                    <td>
                                        @if($dpr->machine_start_reading && $dpr->machine_end_reading)
                                            {{ number_format($dpr->machine_end_reading - $dpr->machine_start_reading - ($dpr->machine_idle_reading ?? 0), 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $dpr->diesel_consumption ?? 0 }}</td>
                                    <td>{{ $dpr->number_of_operators ?? 0 }}</td>
                                    <td>
                                        @php
                                        $statusBadge = match($dpr->status) {
                                            'approved' => ['class' => 'bg-success', 'label' => __('Approved')],
                                            'rejected' => ['class' => 'bg-danger', 'label' => __('Rejected')],
                                            'pending' => ['class' => 'bg-warning', 'label' => __('Pending')],
                                            default => ['class' => 'bg-secondary', 'label' => $dpr->status],
                                        };
                                        @endphp
                                        <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                                    </td>
                                    <td>
                                        @if($dpr->ledgerEntries->count() > 0)
                                            <span class="badge bg-success">{{ __('Linked') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('No Ledger') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="{{ route('machinery.dpr.show', [$machinery, $dpr]) }}" 
                                               class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="{{ __('View') }}">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            @if($dpr->status === 'pending')
                                                <a href="{{ route('machinery.dpr.edit', [$machinery, $dpr]) }}" 
                                                   class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $dprs->links() }}
                @else
                    <div class="text-center py-5">
                        <i class="ti ti-clipboard-text display-4 text-muted"></i>
                        <p class="mt-3 text-muted">{{ __('No DPRs found for this machinery') }}</p>
                        <a href="{{ route('machinery.dpr.create', $machinery) }}" class="btn btn-primary">
                            {{ __('Create First DPR') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
