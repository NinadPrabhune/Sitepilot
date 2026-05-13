<div class="modal-body">
    <div class="card ">
        <div class="card-body table-border-style full-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{__('Material') }}</th>
                        <th>{{__('Quantity')}}</th>

                    </tr>
                    </thead>
                    <tbody>

                    @foreach ($products as $product)
                        @if(!empty($product->material()))
                            <tr>
                                <td>{{ !empty($product->material())?$product->material()->name:'-' }}</td>
                                <td>{{ $product->quantity }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>
