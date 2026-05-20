@forelse($transactions as $transaction)
    @php
        // Get meta as array
        $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
        
        // Check if non-accounting (PO, GRN should be ignored in balance)
        $isNonAccounting = !empty($meta['non_accounting']);
    @endphp
    <tr>
        <td>
            @php
                // Priority: created_at (has time) > transaction_datetime > transaction_date
                $sortDate = $transaction->created_at;
                if (!empty($transaction->transaction_datetime)) {
                    $sortDate = $transaction->transaction_datetime;
                }
            @endphp
            {{ \Carbon\Carbon::parse($sortDate)->format('d M Y') }}
            <br><small class="text-muted">{{ \Carbon\Carbon::parse($sortDate)->format('h:i A') }}</small>
        </td>
        <td>
            @if($transaction->reference_type == 'po')
                <span class="badge bg-primary">{{ __('PO') }}</span>
            @elseif($transaction->reference_type == 'grn')
                <span class="badge bg-secondary">{{ __('GRN') }}</span>
            @elseif($transaction->reference_type == 'invoice')
                <span class="badge bg-info">{{ __('Invoice') }}</span>
            @elseif($transaction->reference_type == 'payment')
                <span class="badge bg-success">{{ __('Payment') }}</span>
            @elseif($transaction->reference_type == 'advance')
                <span class="badge bg-warning">{{ __('Advance') }}</span>
            @elseif($transaction->reference_type == 'adjustment')
                <span class="badge bg-dark">{{ __('Adjustment') }}</span>
            @endif
        </td>
        <td>
            @if($transaction->reference_type == 'po')
                <a href="{{ route('purchase-order.show', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
            @elseif($transaction->reference_type == 'grn')
                <a href="{{ route('grn.show', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
            @elseif($transaction->reference_type == 'invoice')
                <a href="{{ route('purchase-invoice.show', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
            @elseif($transaction->reference_type == 'payment' || $transaction->reference_type == 'advance')
                <a href="{{ route('payments-module.edit', $transaction->reference_id) }}" target="_blank">{{ $transaction->reference_number }}</a>
            @else
                {{ $transaction->reference_number ?? '-' }}
            @endif
        </td>
        <td>{{ $transaction->supplier->name ?? '-' }}</td>
        <td class="text-right" style="text-align: right;">{{ currency_format_with_sym_indian($transaction->reference_amount ?? 0) }}</td>
        <td>{{ $transaction->site->name ?? '-' }}</td>
        <td class="text-right" style="text-align: right;">
            @if($isNonAccounting)
                -
            @else
                @if((float)($transaction->debit ?? 0) > 0)
                    {{ currency_format_with_sym_indian($transaction->debit) }}
                @else
                    -
                @endif
            @endif
        </td>
        <td class="text-right" style="text-align: right;">
            @if($isNonAccounting)
                -
            @else
                @if((float)($transaction->credit ?? 0) > 0)
                    {{ currency_format_with_sym_indian($transaction->credit) }}
                @else
                    -
                @endif
            @endif
        </td>
        <td class="text-right {{ $transaction->balance < 0 ? 'text-danger' : '' }}" style="text-align: right;">
            @if($isNonAccounting)
                -
            @else
                {{ currency_format_with_sym_indian($transaction->balance) }}
            @endif
        </td>
        <td>{{ $transaction->description ?? '-' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="10" class="text-center">{{ __('No transactions found') }}</td>
    </tr>
@endforelse