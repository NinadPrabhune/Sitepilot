@extends('layouts.main')

@section('page-title', __('Supplier Details'))
@section('page-breadcrumb', __('Supplier Details'))

@section('page-action')
<div class="d-flex gap-2">
    <a href="{{ route('supplier.index') }}" class="btn btn-sm btn-light border">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
</div>
@endsection

@section('content')
<div class="row">
<div class="col-xl-12">

    {{-- ================= SUPPLIER HEADER ================= --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1 fw-bold">{{ $supplier->name ?? __('Not Available') }}</h3>
                <p class="mb-0 text-muted">
                    {{ __('Supplier ID') }} : #{{ $supplier->id }}
                </p>
            </div>
            <div class="text-end">
                <span class="badge bg-{{ $supplier->is_active ? 'success' : 'secondary' }} px-3 py-2">
                    {{ $supplier->is_active ? __('Active') : __('Inactive') }}
                </span>
                <br>
                <small class="text-muted">
                    {{ __('Type') }} :
                    <strong>{{ $supplier->type ? ucfirst($supplier->type) : __('Not Available') }}</strong>
                </small>
            </div>
        </div>
    </div>

    {{-- ================= CONTACT INFORMATION ================= --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">{{ __('Contact Information') }}</h5>
        </div>
        <div class="card-body row g-4">

            @php
                $contactFields = [
                    'Contact Person' => $supplier->contact_person,
                    'Phone' => $supplier->phone,
                    'Email' => $supplier->email,
                    'Address' => $supplier->address,
                    'City' => $supplier->city,
                    'State' => $supplier->state,
                    'Pincode' => $supplier->pincode,
                    'Country' => $supplier->country,
                ];
            @endphp

            @foreach($contactFields as $label => $value)
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <small class="text-muted d-block">{{ __($label) }}</small>
                        <strong>{{ $value ?: __('Not Available') }}</strong>
                    </div>
                </div>
            @endforeach

        </div>
    </div>

    {{-- ================= BUSINESS INFORMATION ================= --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">{{ __('Business Information') }}</h5>
        </div>
        <div class="card-body row g-4">

            @php
                $businessFields = [
                    'GST Number' => $supplier->gst_number,
                    'PAN Number' => $supplier->pan_number,
                    'Registration Number' => $supplier->registration_number,
                    'Payment Terms' => $supplier->payment_terms,
                ];
            @endphp

            @foreach($businessFields as $label => $value)
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <small class="text-muted d-block">{{ __($label) }}</small>
                        <strong>{{ $value ?: __('Not Available') }}</strong>
                    </div>
                </div>
            @endforeach

            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <small class="text-muted d-block">{{ __('Created By') }}</small>
                    <strong>{{ $supplier->creator->name ?? __('Not Available') }}</strong>
                </div>
            </div>

        </div>
    </div>

    {{-- ================= BANKING INFORMATION ================= --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">{{ __('Banking Information') }}</h5>
        </div>
        <div class="card-body row g-4">

            @php
                $bankFields = [
                    'Bank Name' => $supplier->bank_name,
                    'Account Number' => $supplier->account_number,
                    'IFSC Code' => $supplier->ifsc_code,
                ];
            @endphp

            @foreach($bankFields as $label => $value)
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <small class="text-muted d-block">{{ __($label) }}</small>
                        <strong>{{ $value ?: __('Not Available') }}</strong>
                    </div>
                </div>
            @endforeach

        </div>
    </div>

    {{-- ================= UPI SCREENSHOTS ================= --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">{{ __('UPI Screenshots') }}</h5>
        </div>
        <div class="card-body row g-4 text-center">

            @foreach(['upi_screenshot_1','upi_screenshot_2'] as $field)
                <div class="col-md-6">
                    <div class="border rounded p-3 bg-light h-100">
                        <small class="text-muted d-block mb-2">
                            {{ ucfirst(str_replace('_',' ', $field)) }}
                        </small>

                        @if($supplier->$field)
                            <img src="{{ asset($supplier->$field) }}"
                                 class="img-fluid rounded shadow-sm"
                                 style="max-height:250px; cursor:pointer;"
                                 onclick="window.open(this.src,'_blank')">
                        @else
                            <div class="text-muted py-5">
                                {{ __('Not Available') }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

        </div>
    </div>

</div>
</div>
@endsection