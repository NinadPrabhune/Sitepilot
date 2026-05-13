@extends('layouts.main')

@section('page-title', __('Lock Month Confirmation'))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('monthly-control.index') }}">{{ __('Monthly Control') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Lock Month') }}</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">
                        <i class="ti ti-lock"></i> 
                        {{ __('Lock Month Confirmation') }}
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Warning Message -->
                    <div class="alert alert-warning">
                        <h6><i class="ti ti-alert-triangle"></i> {{ __('Important Warning') }}</h6>
                        <ul class="mb-0">
                            <li>{{ __('After locking, no DPR changes will be allowed') }}</li>
                            <li>{{ __('Billing will use this snapshot data') }}</li>
                            <li>{{ __('Unlock required for any corrections') }}</li>
                            <li>{{ __('This action will be logged for audit') }}</li>
                        </ul>
                    </div>

                    <!-- Summary Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h6>{{ __('Period') }}</h6>
                                    <h4>{{ Carbon\Carbon::create($year, $month, 1)->format('F Y') }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h6>{{ __('Total DPRs') }}</h6>
                                    <h4>{{ $billingSummary['total_items'] ?? 0 }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6>{{ __('Total Amount') }}</h6>
                                    <h4>₹{{ number_format($billingSummary['total_amount'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h6>{{ __('Total Hours') }}</h6>
                                    <h4>{{ number_format($billingSummary['total_hours'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h6>{{ __('Total Diesel') }}</h6>
                                    <h4>{{ number_format($billingSummary['total_diesel'] ?? 0, 2) }} L</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Breakdown -->
                    @if(($unbilledSummary['total_suppliers'] ?? 0) > 0)
                        <div class="mb-4">
                            <h6>{{ __('Supplier Breakdown') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Supplier') }}</th>
                                            <th>{{ __('Items') }}</th>
                                            <th>{{ __('Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($unbilledSummary['suppliers'] ?? [] as $supplier)
                                            <tr>
                                                <td>{{ $supplier['supplier_name'] }}</td>
                                                <td>{{ $supplier['items_count'] }}</td>
                                                <td>₹{{ number_format($supplier['total_amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-light">
                                            <th>{{ __('Total') }}</th>
                                            <th>{{ $unbilledSummary['total_items'] ?? 0 }}</th>
                                            <th>₹{{ number_format($unbilledSummary['total_amount'] ?? 0, 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Confirmation Form -->
                    <form method="POST" action="{{ route('monthly-control.lock') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <input type="hidden" name="year" value="{{ $year }}">
                        
                        <div class="form-group mb-3">
                            <label for="remarks">{{ __('Lock Remarks (Optional)') }}</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                placeholder="{{ __('Enter any remarks for this lock...') }}"></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('monthly-control.index') }}" class="btn btn-secondary">
                                <i class="ti ti-arrow-left"></i> {{ __('Cancel') }}
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="ti ti-lock"></i> {{ __('Confirm Lock Month') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
