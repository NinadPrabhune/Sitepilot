<div class="modal-body">
    <div class="card ">
        <div class="card-body table-border-style full-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{__('Item') }}</th>
                        <th>{{__('Quantity')}}</th>

                    </tr>
                    </thead>
                    <tbody>

                    @foreach ($products as $product)
                        @if(!empty($product->item()))
                            <tr>
                                <td>{{ !empty($product->item())?$product->item()->name:'-' }}</td>
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
