@extends('layouts.main')

@section('page-title', __('DPR Details - ') . $dpr->date->format('Y-m-d'))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('machineries.index') }}">{{ __('Machinery') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machineries.show', $machinery) }}">{{ $machinery->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machinery.dpr.index', $machinery) }}">{{ __('DPRs') }}</a></li>
    <li class="breadcrumb-item active">{{ $dpr->date->format('Y-m-d') }}</li>
@endsection

@section('action-btn')
    @if($dpr->status === 'pending')
        <a href="{{ route('machinery.dpr.edit', [$machinery, $dpr]) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
            <i class="ti ti-edit"></i> {{ __('Edit') }}
        </a>
    @endif
    <a href="{{ route('machinery.dpr.index', $machinery) }}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="{{ __('Back') }}">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <!-- DPR Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('DPR Details') }}</h5>
                <div class="card-header-right">
                    <span class="badge bg-warning">{{ __('Direct Machinery Flow') }}</span>
                    @php
                    $statusBadge = match($dpr->status) {
                        'approved' => ['class' => 'bg-success', 'label' => __('Approved')],
                        'rejected' => ['class' => 'bg-danger', 'label' => __('Rejected')],
                        'pending' => ['class' => 'bg-warning', 'label' => __('Pending')],
                        default => ['class' => 'bg-secondary', 'label' => $dpr->status],
                    };
                    @endphp
                    <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('Date') }}:</th>
                                <td>{{ $dpr->date->format('Y-m-d') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Site') }}:</th>
                                <td>{{ $dpr->site?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Machinery') }}:</th>
                                <td>{{ $dpr->machinery?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Source Type') }}:</th>
                                <td><span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $dpr->source_type)) }}</span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('Start Reading') }}:</th>
                                <td>{{ number_format($dpr->machine_start_reading, 2) }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('End Reading') }}:</th>
                                <td>{{ number_format($dpr->machine_end_reading, 2) }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Idle Hours') }}:</th>
                                <td>{{ number_format($dpr->machine_idle_reading ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Working Hours') }}:</th>
                                <td>
                                    <strong>{{ number_format($dpr->machine_end_reading - $dpr->machine_start_reading - ($dpr->machine_idle_reading ?? 0), 2) }}</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <h6 class="text-muted">{{ __('Diesel Consumption') }}</h6>
                            <h4>{{ $dpr->diesel_consumption ?? 0 }} <small>L</small></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <h6 class="text-muted">{{ __('Operators') }}</h6>
                            <h4>{{ $dpr->number_of_operators ?? 0 }}</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <h6 class="text-muted">{{ __('Calculated Amount') }}</h6>
                            <h4>₹{{ number_format($dpr->calculated_amount ?? 0, 2) }}</h4>
                        </div>
                    </div>
                </div>

                @if($dpr->work_details)
                    <div class="mt-4">
                        <h6>{{ __('Work Details') }}:</h6>
                        <p class="text-muted">{{ $dpr->work_details }}</p>
                    </div>
                @endif

                @if($dpr->maintenance_notes)
                    <div class="mt-3">
                        <h6>{{ __('Maintenance Notes') }}:</h6>
                        <p class="text-muted">{{ $dpr->maintenance_notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Ledger Entries -->
        @if($dpr->ledgerEntries->count() > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Linked Ledger Entries') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Payment Request') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dpr->ledgerEntries as $entry)
                            <tr>
                                <td>{{ $entry->id }}</td>
                                <td>₹{{ number_format($entry->amount, 2) }}</td>
                                <td>
                                    @if($entry->is_settled)
                                        <span class="badge bg-success">{{ __('Settled') }}</span>
                                    @elseif($entry->is_reversed)
                                        <span class="badge bg-danger">{{ __('Reversed') }}</span>
                                    @elseif($entry->payment_request_id)
                                        <span class="badge bg-warning">{{ __('In Payment') }}</span>
                                    @else
                                        <span class="badge bg-primary">{{ __('Available') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($entry->payment_request_id)
                                        <a href="{{ route('machinery-payment-request.show', $entry->payment_request_id) }}">
                                            #{{ $entry->payment_request_id }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="alert alert-warning">
            <i class="ti ti-alert-circle"></i> {{ __('No ledger entries found for this DPR') }}
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <!-- Actions Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Actions') }}</h5>
            </div>
            <div class="card-body">
                @if($dpr->status === 'pending')
                    <form action="" method="POST" class="d-grid gap-2">
                        @csrf
                        @method('PATCH')
                        <button type="submit" name="action" value="approve" class="btn btn-success">
                            <i class="ti ti-check"></i> {{ __('Approve DPR') }}
                        </button>
                    </form>
                    <hr>
                @endif

                @if($dpr->ledgerEntries->whereNull('payment_request_id')->where('is_settled', false)->where('is_reversed', false)->count() > 0)
                    <a href="{{ route('machinery-payment-request.create') }}?machinery_id={{ $machinery->id }}" 
                       class="btn btn-outline-primary w-100">
                        <i class="ti ti-file-dollar"></i> {{ __('Create Payment Request') }}
                    </a>
                @endif

                <div class="mt-3">
                    <small class="text-muted">
                        {{ __('Created') }}: {{ $dpr->created_at->format('Y-m-d H:i') }}<br>
                        {{ __('By') }}: {{ $dpr->creator?->name ?? '-' }}
                    </small>
                </div>
            </div>
        </div>

        <!-- Calculation Snapshot -->
        @if($dpr->ledgerEntries->first()?->calculation_snapshot)
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">{{ __('Calculation Details') }}</h6>
            </div>
            <div class="card-body">
                @php
                $snapshot = $dpr->ledgerEntries->first()->calculation_snapshot;
                @endphp
                <table class="table table-sm table-borderless">
                    <tr>
                        <th>{{ __('Hours') }}:</th>
                        <td class="text-end">{{ $snapshot['hours'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Rate') }}:</th>
                        <td class="text-end">₹{{ number_format($snapshot['rate'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Operational Cost') }}:</th>
                        <td class="text-end">₹{{ number_format($snapshot['operational_cost'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Diesel Cost') }}:</th>
                        <td class="text-end">₹{{ number_format($snapshot['diesel_cost'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-top">
                        <th>{{ __('Total') }}:</th>
                        <td class="text-end"><strong>₹{{ number_format($snapshot['total_amount'] ?? 0, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
