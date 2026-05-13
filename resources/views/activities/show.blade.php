@extends('layouts.main')

@section('page-title')
    {{ __('Activity Details') }}
@endsection

@section('page-breadcrumb')
    {{ __('Activity Details') }}
@endsection

@section('page-action')
    <a href="{{ route('activities.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>

    <button onclick="window.print()" class="btn btn-sm btn-primary ms-2">
        <i class="ti ti-printer"></i> {{ __('Print') }}
    </button>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        <!-- Activity Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Activity Information') }}</h5>
                <span class="badge bg-secondary">{{ __('#') . $activity->id }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-md-8">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Title') }}:</strong><br>
                                {{ $activity->title }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('Scope') }}:</strong><br>
                                {{ $activity->scope }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Quantity') }}:</strong><br>
                                {{ $activity->quantity }} {{ $activity->unit }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('Priority') }}:</strong><br>
                                {{ ucfirst($activity->priority) }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Status') }}:</strong><br>
                                <span class="badge bg-{{ $activity->status === 'completed' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($activity->status) }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('Created By') }}:</strong><br>
                                {{ optional($activity->creator)->name ?? '—' }}
                            </div>
                        </div>

                        @if($activity->reference_file)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Reference File') }}:</strong><br>
                                <a href="{{ asset('storage/' . $activity->reference_file) }}" target="_blank" class="btn btn-sm btn-info">
                                    <i class="ti ti-file"></i> {{ __('View File') }}
                                </a>
                            </div>
                        </div>
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Workspace') }}:</strong><br>
                                {{ optional($activity->workspace)->name ?? '—' }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('Site / Project') }}:</strong><br>
                                {{ optional($activity->site)->name ?? '—' }}
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-4">
                        <strong>{{ __('Date') }}:</strong><br>
                        {{ \Carbon\Carbon::parse($activity->date)->format('d M Y') }}
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- Completed Activities Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Completed Records') }}</h5>
            </div>
            <div class="card-body">
                @if($activity->completeds->count() > 0)
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                               
                                <th>{{ __('Till Date') }}</th>
                                <th>{{ __('Completed') }}</th>
                               
                                <th>{{ __('Scope') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $rem_qty = 0;
                            @endphp
                            @foreach($activity->completeds as $completion)
                            @php
                            $rem_qty += $completion->completed_quantity;
                            @endphp
                            
                                <tr>
                                    {{-- completed_date column --}}
                                    <td>
                                        {{ $completion->completed_date 
                                            ? \Carbon\Carbon::parse($completion->completed_date)->format('d M Y') 
                                            : '—' }}
                                    </td>

                                    

                                    <td>{{ $completion->completed_quantity }} {{ $activity->unit }}</td>
                                    <td>{{ $rem_qty }} {{ $activity->unit }}</td>
                                    <td>{{ $activity->quantity }} {{ $activity->unit }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted">{{ __('No completeds recorded yet.') }}</p>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
