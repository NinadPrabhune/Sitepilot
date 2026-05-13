@extends('layouts.main')
@section('page-title', __('Opening Stock'))
@section('page-breadcrumb', __('Opening Stock'))

@section('page-action')
@permission('opening-stock create')
<a data-size="lg" data-url="{{ route('opening-stock.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" data-bs-original-title="{{__('Add Opening Stock')}}" title="{{__('Add Opening Stock')}}" data-title="{{__('Add Opening Stock')}}" class="btn btn-sm btn-primary">
    <i class="ti ti-plus"></i>
</a>
<a href="{{ route('opening-stock.import.form') }}" class="btn btn-sm btn-success ms-2">
    <i class="ti ti-file-import"></i> {{__('Import')}}
</a>
@endpermission
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table pc-dt-simple" id="opening-stock-table">
                        <thead>
                            <tr>
                                <th>{{ __('Project') }}</th>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Rate') }}</th>
                                <th>{{ __('Current Stock') }}</th>
                                <th>{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $openingTransactions = \App\Models\StockTransaction::with(['project', 'material'])
                                    ->where('type', 'opening')
                                    ->latest()
                                    ->get();
                            @endphp
                            @forelse($openingTransactions as $transaction)
                            <tr>
                                <td>{{ $transaction->project->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->material->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->quantity }}</td>
                                <td>{{ $transaction->rate ?? '-' }}</td>
                                <td>
                                    @php
                                        $currentStock = \App\Models\MaterialProjectStock::getCurrentStock($transaction->project_id, $transaction->material_id);
                                    @endphp
                                    {{ $currentStock }}
                                </td>
                                <td>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d-m-Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center">{{ __('No opening stock records found.') }}</td>
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
