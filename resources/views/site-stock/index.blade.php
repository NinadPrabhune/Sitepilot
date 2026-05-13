@extends('layouts.main')
@section('page-title', __('Site Stock Report'))
@section('page-breadcrumb', __('Site Stock'))

@section('page-action')
@permission('site-stock manage')
<a href="{{ route('site-stock.export', request()->query()) }}" class="btn btn-sm btn-secondary">
    <i class="ti ti-download"></i> {{ __('Export') }}
</a>
@endpermission
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-12">
                        {{ Form::open(['route' => 'site-stock.index', 'method' => 'GET', 'id' => 'site-stock-filter']) }}
                        <div class="row">
                            <div class="col-md-4">
                                {{ Form::select('project_id', $projects, $filters['project_id'] ?? null, ['class' => 'form-control', 'placeholder' => __('All Projects')]) }}
                            </div>
                            <div class="col-md-4">
                                {{ Form::select('material_id', $materials, $filters['material_id'] ?? null, ['class' => 'form-control', 'placeholder' => __('All Materials')]) }}
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                                <a href="{{ route('site-stock.index') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table pc-dt-simple" id="site-stock-table">
                        <thead>
                            <tr>
                                <th>{{ __('Project') }}</th>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Current Stock') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockReport as $stock)
                            <tr>
                                <td>{{ $stock->project->name ?? 'N/A' }}</td>
                                <td>{{ $stock->material->name ?? 'N/A' }}</td>
                                <td>{{ $stock->material->unit->name ?? '-' }}</td>
                                <td>{{ $stock->current_stock }}</td>
                                <td>
                                    @if($stock->current_stock > 0)
                                        <span class="badge bg-success">{{ __('In Stock') }}</span>
                                    @elseif($stock->current_stock == 0)
                                        <span class="badge bg-warning">{{ __('Out of Stock') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ __('Negative') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center">{{ __('No stock records found.') }}</td>
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
