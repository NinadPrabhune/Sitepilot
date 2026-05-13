@extends('layouts.main')

@section('page-title')
    {{ __('Material Details') }}
@endsection

@section('page-breadcrumb')
    {{ __('Material Details') }}
@endsection

@section('page-action')
    <a href="{{ route('material.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
  
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">

        <!-- Material Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Material Information') }}</h5>
                <span class="badge bg-secondary">{{ __('#') . $material->id }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Left Column: Details -->
                    <div class="col-md-8">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Name') }}:</strong><br>
                                {{ $material->name }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('SKU') }}:</strong><br>
                                {{ $material->sku }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('HSN/SAC') }}:</strong><br>
                                {{ $material->hsn_sac ?? '—' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Category') }}:</strong><br>
                                {{ optional($material->category)->name ?? '—' }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('GST Rate') }}:</strong><br>
                                {{ optional($material->gstMaster)->name ?? '—' }} ({{ optional($material->gstMaster)->total_gst ?? '0' }}%)
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Unit') }}:</strong><br>
                                {{ optional($material->unit)->name ?? '—' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>{{ __('Status') }}:</strong><br>
                                <span class="badge bg-{{ $material->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($material->status) }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>{{ __('Price') }}:</strong><br>
                                <span class="h6 text-success">{{ currency_format_with_sym($material->price) }}</span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>{{ __('Description') }}:</strong><br>
                                {{ $material->description ?? __('No description available') }}
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Image -->
                    <div class="col-md-4 text-center">
                        <strong>{{ __('Image') }}:</strong><br>
                        @if($material->image)
                            <img src="{{ asset('/' . ltrim($material->image, '/')) }}" 
                                 alt="{{ $material->name }}" 
                                 class="img-fluid rounded border mt-2" 
                                 style="max-height: 200px;">
                        @else
                            <img src="{{ asset('images/material/No_Image_Available.jpeg') }}" 
                                 alt="{{ __('No Image Available') }}" 
                                 class="img-fluid rounded border mt-2" 
                                 style="max-height: 200px;">
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Details Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ __('Stock Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>{{ __('Quantity') }}:</strong><br>
                        {{ $material->quantity }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Reorder Level') }}:</strong><br>
                        {{ $material->reorder_level }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ __('Last Updated') }}:</strong><br>
                        {{ \Carbon\Carbon::parse($material->updated_at)->format('d M Y') }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
