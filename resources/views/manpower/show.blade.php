@extends('layouts.main')

@section('page-title')
    {{ __('Manpower Record Details') }}
@endsection

@section('page-breadcrumb')
    {{ __('Manpower Record Details') }}
@endsection

@section('page-action')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('manpower.index') }}" class="btn btn-sm btn-secondary">
            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
        </a>
       
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card mb-4">
            <div class="card-header">
                <strong>{{ __('General Information') }}</strong>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-6"><strong>{{ __('Work Date') }}:</strong> {{ \Carbon\Carbon::parse($manpower->work_date)->format('d M, Y') }}</div>
                    <div class="col-md-6"><strong>{{ __('Supplier') }}:</strong> {{ optional($manpower->supplier)->name }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-6"><strong>{{ __('Site') }}:</strong> {{ optional($manpower->site)->name }}</div>
                    <div class="col-md-6"><strong>{{ __('Workspace') }}:</strong> {{ optional($manpower->workspace)->name }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-6"><strong>{{ __('Created By') }}:</strong> {{ optional($manpower->creator)->name }}</div>
                    <div class="col-md-6"><strong>{{ __('Total Count') }}:</strong> {{ $manpower->total_count }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <strong>{{ __('Manpower Details') }}</strong>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 pc-dt-simple">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Count') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($manpower->details as $detail)
                                <tr>
                                    <td>{{ optional($detail->type)->name }}</td>
                                    <td>{{ $detail->count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">{{ __('No manpower details available.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
