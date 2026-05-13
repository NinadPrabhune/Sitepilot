@extends('layouts.main')

@section('page-title', __('Edit DPR - ') . $dpr->date->format('Y-m-d'))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('machineries.index') }}">{{ __('Machinery') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machineries.show', $machinery) }}">{{ $machinery->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('machinery.dpr.index', $machinery) }}">{{ __('DPRs') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Edit Daily Progress Report') }}</h5>
                <div class="card-header-right">
                    <span class="badge bg-warning">{{ __('Direct Machinery Flow') }}</span>
                </div>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('machinery.dpr.update', [$machinery, $dpr]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <!-- Info Alert -->
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle"></i>
                        {{ __('Editing this DPR may trigger ledger corrections if ledger entries already exist.') }}
                    </div>

                    <!-- Date (Read-only) -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Date') }}</label>
                        <input type="text" class="form-control" value="{{ $dpr->date->format('Y-m-d') }}" disabled>
                        <small class="text-muted">{{ __('Date cannot be changed after creation') }}</small>
                    </div>

                    <!-- Site (Read-only) -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Site') }}</label>
                        <input type="text" class="form-control" value="{{ $dpr->site?->name ?? '-' }}" disabled>
                    </div>

                    <!-- Machine Readings -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Start Reading') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="machine_start_reading" 
                                       class="form-control @error('machine_start_reading') is-invalid @enderror"
                                       value="{{ old('machine_start_reading', $dpr->machine_start_reading) }}" required>
                                @error('machine_start_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('End Reading') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="machine_end_reading" 
                                       class="form-control @error('machine_end_reading') is-invalid @enderror"
                                       value="{{ old('machine_end_reading', $dpr->machine_end_reading) }}" required>
                                @error('machine_end_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Idle Hours') }}</label>
                                <input type="number" step="0.01" name="machine_idle_reading" 
                                       class="form-control @error('machine_idle_reading') is-invalid @enderror"
                                       value="{{ old('machine_idle_reading', $dpr->machine_idle_reading ?? 0) }}">
                                @error('machine_idle_reading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Diesel & Operators -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Diesel Consumption (Liters)') }}</label>
                                <input type="number" step="0.01" name="diesel_consumption" 
                                       class="form-control @error('diesel_consumption') is-invalid @enderror"
                                       value="{{ old('diesel_consumption', $dpr->diesel_consumption ?? 0) }}">
                                @error('diesel_consumption')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Number of Operators') }}</label>
                                <input type="number" name="number_of_operators" 
                                       class="form-control @error('number_of_operators') is-invalid @enderror"
                                       value="{{ old('number_of_operators', $dpr->number_of_operators ?? 0) }}">
                                @error('number_of_operators')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Work Details -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Work Details') }}</label>
                        <textarea name="work_details" rows="3" 
                                  class="form-control @error('work_details') is-invalid @enderror">{{ old('work_details', $dpr->work_details) }}</textarea>
                        @error('work_details')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Maintenance Notes -->
                    <div class="mb-3">
                        <label class="form-label">{{ __('Maintenance Notes') }}</label>
                        <textarea name="maintenance_notes" rows="2" 
                                  class="form-control @error('maintenance_notes') is-invalid @enderror">{{ old('maintenance_notes', $dpr->maintenance_notes) }}</textarea>
                        @error('maintenance_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('machinery.dpr.show', [$machinery, $dpr]) }}" class="btn btn-outline-secondary">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-save"></i> {{ __('Update DPR') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
