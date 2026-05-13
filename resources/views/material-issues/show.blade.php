@extends('layouts.main')
@section('page-title')
{{__('Material Issue Details')}}
@endsection
@section('page-breadcrumb')
{{__('Material Issue')}}
@endsection
@section('page-action')
<div class="d-flex">
    <a href="{{ route('material-issues.index') }}" class="btn btn-sm btn-light border me-2">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
</div>
@endsection
@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>{{ __('Material Issue Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th>{{ __('Issue Number') }}</th>
                                <td>{{ $materialIssue->issue_number }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Issue Date') }}</th>
                                <td>{{ $materialIssue->issue_date->format('d-m-Y') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Issue To Type') }}</th>
                                <td>{{ ucfirst($materialIssue->issue_to_type) }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Issue To') }}</th>
                                <td>{{ $materialIssue->issue_to_name }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Status') }}</th>
                                <td><span class="badge bg-success">{{ $materialIssue->status }}</span></td>
                            </tr>
                            <tr>
                                <th>{{ __('Remarks') }}</th>
                                <td>{{ $materialIssue->remarks ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Created By') }}</th>
                                <td>{{ $materialIssue->creator ? $materialIssue->creator->name : 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr>
                <h5>{{ __('Material Items') }}</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materialIssue->items as $item)
                            <tr>
                                <td>{{ $item->material ? $item->material->name : 'N/A' }}</td>
                                <td>{{ $item->quantity }} {{ $item->material && $item->material->unit ? $item->material->unit->name : '' }}</td>
                                <td>{{ $item->rate ?? 'N/A' }}</td>
                                <td>{{ $item->amount ?? 'N/A' }}</td>
                                <td>{{ $item->remarks ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
