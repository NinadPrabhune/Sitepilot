@extends('layouts.main')

@section('page-title')
    {{ __('Material Transfer Details') }}
@endsection

@section('page-breadcrumb')
   {{ __('Material Transfer Details') }}
@endsection

@section('action-btn')
    <a href="{{ route('material-transfer.index') }}" 
       class="btn btn-sm btn-primary">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <h5>{{ __('Transfer Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    {{-- Record Number --}}
                    <div class="form-group col-md-6">
                        <label class="form-label">{{ __('Record Number') }}</label>
                        <p class="form-control-plaintext">{{ $materialTransfer->record_number }}</p>
                    </div>

                    {{-- Record Date --}}
                    <div class="form-group col-md-6">
                        <label class="form-label">{{ __('Record Date') }}</label>
                        <p class="form-control-plaintext">{{ \Carbon\Carbon::parse($materialTransfer->record_date)->format('d M Y') }}</p>
                    </div>

                    {{-- From Site --}}
                    <div class="form-group col-md-6">
                        <label class="form-label">{{ __('From Site') }}</label>
                        <p class="form-control-plaintext">{{ $materialTransfer->fromSite->name ?? '-' }}</p>
                    </div>

                    {{-- To Site --}}
                    <div class="form-group col-md-6">
                        <label class="form-label">{{ __('To Site') }}</label>
                        <p class="form-control-plaintext">{{ $materialTransfer->toSite->name ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Material Table --}}
        <div class="card mt-3">
            <div class="card-header">
                <h5>{{ __('Transferred Materials') }}</h5>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                               
                                <th>{{ __('Quantity | Unit') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materialTransfer->items as $item)
                                <tr>
                                    <td>{{ $item->material->name ?? '' }}</td>
                                    
                                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td>{{ number_format($item->price, 2) }}</td>
                                    <td>{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="text-end mt-2">
                        <label class="fw-bold">{{ __('Total Amount:') }}</label>
                        <span class="fw-bold">{{ number_format($materialTransfer->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
