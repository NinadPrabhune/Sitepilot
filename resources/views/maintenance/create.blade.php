@extends('layouts.main')

@section('page-title')
    Add Maintenance Log
@endsection

@section('page-breadcrumb')
    Add Maintenance Log
@endsection

@section('page-action')
    <a href="{{ route('maintenance.index') }}" class="btn btn-sm btn-secondary">
        <i class="ti ti-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <strong>Add Maintenance Log</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('maintenance.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="machinery_id">Machinery <span class="text-danger">*</span></label>
                                <select class="form-select" id="machinery_id" name="machinery_id" required>
                                    <option value="">Select Machinery</option>
                                    @foreach($machineries as $machinery)
                                        <option value="{{ $machinery->id }}">{{ $machinery->name }} ({{ $machinery->vehicle_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vendor_id">Vendor</label>
                                <select class="form-select" id="vendor_id" name="vendor_id">
                                    <option value="">Select Vendor</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="maintenance_date">Maintenance Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cost">Cost (₹) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost" required min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="paid_by">Paid By <span class="text-danger">*</span></label>
                                <select class="form-select" id="paid_by" name="paid_by" required>
                                    <option value="company">Company</option>
                                    <option value="supplier">Supplier</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="site_id">Site <span class="text-danger">*</span></label>
                                <select class="form-select" id="site_id" name="site_id" required>
                                    <option value="">Select Site</option>
                                    @foreach($sites as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="attachment">Attachment (PDF/Image)</label>
                                <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="form-text text-muted">Max size: 5MB</small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-save"></i> Save Maintenance Log
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
