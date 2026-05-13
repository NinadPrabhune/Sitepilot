@extends('layouts.main')

@section('page-title')
    Reports
@endsection

@section('page-breadcrumb')
    Reports
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <strong>Financial Reports</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title">Machinery Ledger Summary</h5>
                                <p class="card-text">View detailed ledger entries for machinery</p>
                                <a href="{{ route('reports.machinery-ledger-summary') }}" class="btn btn-primary">View Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title">Supplier Outstanding</h5>
                                <p class="card-text">Track supplier balances and payments</p>
                                <a href="{{ route('reports.supplier-outstanding') }}" class="btn btn-primary">View Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title">Monthly Cost Report</h5>
                                <p class="card-text">Analyze monthly costs by type</p>
                                <a href="{{ route('reports.monthly-cost') }}" class="btn btn-primary">View Report</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
