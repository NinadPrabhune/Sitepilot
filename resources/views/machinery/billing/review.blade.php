@extends('layouts.main')

@section('page-title', __('Review Machinery Bill - ') . Carbon\Carbon::create($year, $month, 1)->format('F Y'))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('machinery.billing.index', ['month' => $month, 'year' => $year]) }}">{{ __('Billing') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Review') }}</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Review Header -->
            <div class="card bg-warning text-white mb-4">
                <div class="card-body text-center">
                    <h5><i class="ti ti-alert-triangle"></i> {{ __('Final Review') }}</h5>
                    <p>{{ __('Please review the billing details before creating bills. This action will create actual bills that can be used for payment requests.') }}</p>
                </div>
            </div>

            @foreach($billingItems as $supplierId => $supplierData)
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="ti ti-building-factory"></i> 
                            {{ $supplierData['supplier']->name }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Supplier Summary -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small>{{ __('Items') }}</small>
                                        <h5>{{ $supplierData['items']->count() }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small>{{ __('Hours') }}</small>
                                        <h5>{{ number_format($supplierData['total_hours'], 2) }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small>{{ __('Diesel (L)') }}</small>
                                        <h5>{{ number_format($supplierData['total_diesel'], 2) }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <small>{{ __('Total Amount') }}</small>
                                        <h5>₹{{ number_format($supplierData['total_amount'], 2) }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Machinery') }}</th>
                                        <th>{{ __('Period') }}</th>
                                        <th>{{ __('Hours') }}</th>
                                        <th>{{ __('Diesel') }}</th>
                                        <th>{{ __('Hourly Rate') }}</th>
                                        <th>{{ __('Diesel Rate') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($supplierData['items'] as $item)
                                        <tr>
                                            <td>{{ $item->machinery->name }}</td>
                                            <td>{{ $item->from_date->format('d M') }} - {{ $item->to_date->format('d M') }}</td>
                                            <td>{{ number_format($item->total_hours, 2) }}</td>
                                            <td>{{ number_format($item->total_diesel, 2) }}</td>
                                            <td>₹{{ number_format($item->rate_per_hour, 2) }}</td>
                                            <td>₹{{ number_format($item->diesel_rate, 2) }}</td>
                                            <td class="text-end fw-bold">₹{{ number_format($item->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <th colspan="3">{{ __('Total') }}</th>
                                        <td>{{ number_format($supplierData['total_hours'], 2) }}</td>
                                        <td>{{ number_format($supplierData['total_diesel'], 2) }}</td>
                                        <td colspan="2"></td>
                                        <td class="text-end fw-bold">₹{{ number_format($supplierData['total_amount'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Action Form -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="ti ti-check"></i> 
                        {{ __('Confirm Bill Creation') }}
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('machinery.billing.store') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <input type="hidden" name="year" value="{{ $year }}">
                        
                        <!-- Hidden supplier IDs -->
                        @foreach($billingItems as $supplierId => $supplierData)
                            <input type="hidden" name="supplier_ids[]" value="{{ $supplierId }}">
                        @endforeach
                        
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h6>{{ __('Total Suppliers') }}</h6>
                                        <h4>{{ $billingItems->count() }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h6>{{ __('Grand Total') }}</h6>
                                        <h4>₹{{ number_format($billingItems->sum('total_amount'), 2) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label for="remarks">{{ __('Bill Remarks') }}</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                    placeholder="{{ __('Enter any remarks for these bills...') }}"></textarea>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row">
                            <div class="col-md-6">
                                <a href="{{ route('machinery.billing.create', ['month' => $month, 'year' => $year]) }}" 
                                       class="btn btn-secondary w-100">
                                    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
                                </a>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="ti ti-check"></i> {{ __('Create Bills') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
