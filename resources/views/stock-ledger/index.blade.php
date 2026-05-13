@extends('layouts.main')
@section('page-title', __('Stock Ledger'))
@section('page-breadcrumb', __('Stock Ledger'))

@section('page-action')
@permission('stock-ledger manage')
<a href="{{ route('stock-ledger.export', request()->query()) }}" class="btn btn-sm btn-secondary">
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
                        {{ Form::open(['route' => 'stock-ledger.index', 'method' => 'GET', 'id' => 'stock-ledger-filter']) }}
                        <div class="row">
                            <div class="col-md-2">
                                {{ Form::select('project_id', $projects, $filters['project_id'] ?? null, ['class' => 'form-control', 'placeholder' => __('All Projects')]) }}
                            </div>
                            <div class="col-md-2">
                                {{ Form::select('material_id', $materials, $filters['material_id'] ?? null, ['class' => 'form-control', 'placeholder' => __('All Materials')]) }}
                            </div>
                            <div class="col-md-2">
                                {{ Form::select('type', [
                                    '' => __('All Types'),
                                    'opening' => __('Opening'),
                                    'grn' => __('GRN'),
                                    'issue' => __('Issue'),
                                    'transfer_in' => __('Transfer In'),
                                    'transfer_out' => __('Transfer Out'),
                                    'adjustment' => __('Adjustment'),
                                ], $filters['type'] ?? null, ['class' => 'form-control']) }}
                            </div>
                            <div class="col-md-2">
                                {{ Form::date('start_date', $filters['start_date'] ?? null, ['class' => 'form-control', 'placeholder' => __('Start Date')]) }}
                            </div>
                            <div class="col-md-2">
                                {{ Form::date('end_date', $filters['end_date'] ?? null, ['class' => 'form-control', 'placeholder' => __('End Date')]) }}
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                                <a href="{{ route('stock-ledger.index') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table pc-dt-simple" id="stock-ledger-table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Project') }}</th>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Created By') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d-m-Y H:i') }}</td>
                                <td>{{ $transaction->project->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->material->name ?? 'N/A' }}</td>
                                <td>
                                    @if($transaction->type == 'opening')
                                        <span class="badge bg-info">{{ __('Opening') }}</span>
                                    @elseif($transaction->type == 'grn')
                                        <span class="badge bg-success">{{ __('GRN') }}</span>
                                    @elseif($transaction->type == 'issue')
                                        <span class="badge bg-warning">{{ __('Issue') }}</span>
                                    @elseif($transaction->type == 'transfer_in')
                                        <span class="badge bg-primary">{{ __('Transfer In') }}</span>
                                    @elseif($transaction->type == 'transfer_out')
                                        <span class="badge bg-danger">{{ __('Transfer Out') }}</span>
                                    @elseif($transaction->type == 'adjustment')
                                        <span class="badge bg-secondary">{{ __('Adjustment') }}</span>
                                    @endif
                                </td>
                                <td class="{{ $transaction->quantity < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $transaction->quantity }}
                                </td>
                                <td>{{ $transaction->rate ?? '-' }}</td>
                                <td>
                                    @if($transaction->reference_type)
                                        {{ $transaction->reference_type }} #{{ $transaction->reference_id }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $transaction->creator->name ?? 'N/A' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">{{ __('No stock transactions found.') }}</td>
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
