{{-- Shared line-items partial for receipts (all three formats). --}}
@php
    // ADR-010: no tax/VAT line on customer-facing receipt (pending legal review).
    // This partial renders product/price/discount/final only — never a tax line.
@endphp

@foreach($r['items'] as $item)
<div class="receipt-item">
    <div class="receipt-item-name">{{ $item['product_name'] }}@if($item['size_label']) ({{ $item['size_label'] }})@endif</div>
    <div class="receipt-item-meta">{{ $item['barcode'] }}</div>
    <div class="receipt-item-price">
        <span>{{ $item['standard_price'] }}</span>
        @if(((float) $item['discount']) > 0)
        <span class="receipt-discount">-{{ $item['discount'] }}</span>
        @endif
        <span class="receipt-final">{{ $item['final_price'] }}</span>
    </div>
</div>
@endforeach
