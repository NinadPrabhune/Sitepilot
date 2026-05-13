@extends('layouts.main')

@section('page-title')
    Maintenance Logs
@endsection

@section('page-breadcrumb')
    Maintenance Logs
@endsection

@section('page-action')
    <a href="{{ route('maintenance.create') }}" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> Add Maintenance
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <strong>Maintenance Logs</strong>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Machinery</th>
                                <th>Vendor</th>
                                <th>Cost</th>
                                <th>Paid By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($maintenanceLogs as $log)
                            <tr>
                                <td>{{ $log->maintenance_date->format('d-M-Y') }}</td>
                                <td>{{ $log->machinery->name ?? 'N/A' }}</td>
                                <td>{{ $log->vendor->name ?? 'N/A' }}</td>
                                <td>₹{{ number_format($log->cost, 2) }}</td>
                                <td>{{ ucfirst($log->paid_by) }}</td>
                                <td>
                                    @if($log->status == 0)
                                        <span class="badge badge-secondary">Pending</span>
                                    @else
                                        <span class="badge badge-success">Completed</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('maintenance.show', $log->id) }}" class="btn btn-sm btn-info">
                                        <i class="ti ti-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No maintenance logs found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $maintenanceLogs->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
