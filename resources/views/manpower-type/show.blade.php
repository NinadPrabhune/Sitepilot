@extends('layouts.main')
@section('page-title')
    {{__('Material Stock Details')}}
@endsection

@push('scripts')
@endpush
@section('page-breadcrumb')
   {{__('Material Stock Details')}}
@endsection
@section('action-btn')
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table mb-0 pc-dt-simple" id="assets">
                            <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Quantity') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($material as $materials)
                                <tr class="font-style">

                                    <td>{{ !empty($materials->product)? $materials->product->name:'' }}</td>
                                    <td>{{ $materials->quantity }}</td>
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

