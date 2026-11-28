@extends('layouts.print')

@section('title', __('Receipt :number', ['number' => $r['invoice_number']]))

@section('content')
@php
    // ADR-010: no tax/VAT line on customer-facing receipt (pending legal review).
    // This view deliberately omits any tax/VAT line — only subtotal, discount,
    // total, and payment breakdown are shown.
@endphp
<style>
    @page receipt-58mm { size: 58mm auto; margin: 1.5mm; }
    .receipt-58mm { page: receipt-58mm; }

    .receipt-58mm {
        width: 55mm;
        font-family: 'Courier New', Courier, monospace;
        font-size: 9px;
        line-height: 1.3;
        color: #000;
    }
    .receipt-58mm .receipt-header { text-align: center; margin-bottom: 1.5mm; }
    .receipt-58mm .receipt-header h1 { font-size: 11px; font-weight: bold; }
    .receipt-58mm .receipt-header p { font-size: 8px; }
    .receipt-58mm .receipt-meta { margin-bottom: 1.5mm; font-size: 8px; }
    .receipt-58mm .receipt-meta div { display: flex; justify-content: space-between; }
    .receipt-58mm .receipt-customer { margin-bottom: 1.5mm; font-size: 8px; }
    .receipt-58mm .receipt-items { border-top: 1px dashed #000; padding-top: 1mm; margin-bottom: 1.5mm; }
    .receipt-58mm .receipt-item { margin-bottom: 1mm; }
    .receipt-58mm .receipt-item-name { font-weight: bold; font-size: 9px; }
    .receipt-58mm .receipt-item-meta { font-size: 7px; }
    .receipt-58mm .receipt-item-price { display: flex; justify-content: space-between; font-size: 8px; }
    .receipt-58mm .receipt-discount { margin-left: 1mm; }
    .receipt-58mm .receipt-final { font-weight: bold; }
    .receipt-58mm .receipt-totals { border-top: 1px dashed #000; padding-top: 1mm; margin-bottom: 1.5mm; font-size: 8px; }
    .receipt-58mm .receipt-totals div { display: flex; justify-content: space-between; }
    .receipt-58mm .receipt-totals .receipt-grand-total { font-weight: bold; font-size: 11px; border-top: 1px solid #000; padding-top: 1mm; margin-top: 1mm; }
    .receipt-58mm .receipt-payments { border-top: 1px dashed #000; padding-top: 1mm; margin-bottom: 1.5mm; font-size: 8px; }
    .receipt-58mm .receipt-payments div { display: flex; justify-content: space-between; }
    .receipt-58mm .receipt-footer { text-align: center; margin-top: 2mm; font-size: 8px; }
    .receipt-58mm .receipt-policy { font-size: 7px; margin-top: 1mm; }
</style>
<div class="receipt-58mm">
    <div class="receipt-header">
        <h1>{{ $r['branch']['name'] }}</h1>
        @if($r['branch']['phone'])<p>{{ __('Tel') }}: {{ $r['branch']['phone'] }}</p>@endif
    </div>

    <div class="receipt-meta">
        <div><span>{{ $r['invoice_number'] }}</span><span>{{ $r['completed_at'] }}</span></div>
        <div><span>{{ $r['salesperson'] }}</span></div>
    </div>

    @if($r['customer'])
    <div class="receipt-customer">
        <div>{{ $r['customer']['phone'] }}@if($r['customer']['name']) — {{ $r['customer']['name'] }}@endif</div>
    </div>
    @endif

    <div class="receipt-items">
        @include('sales.receipts._line-items')
    </div>

    <div class="receipt-totals">
        <div><span>{{ __('Subtotal') }}</span><span>{{ $r['subtotal'] }}</span></div>
        @if(((float) $r['total_discount']) > 0)
        <div><span>{{ __('Discount') }}</span><span>-{{ $r['total_discount'] }}</span></div>
        @endif
        <div class="receipt-grand-total"><span>{{ __('Total') }}</span><span>{{ $r['total_amount'] }}</span></div>
    </div>

    <div class="receipt-payments">
        @foreach($r['payments'] as $p)
        <div><span>{{ ucfirst($p['method']) }}</span><span>{{ $p['amount'] }}</span></div>
        @endforeach
        <div><span>{{ __('Paid') }}</span><span>{{ $r['total_paid'] }}</span></div>
    </div>

    @if($r['notes'])
    <div class="receipt-customer">{{ $r['notes'] }}</div>
    @endif

    <div class="receipt-footer">
        <p>{{ __('Thank you!') }}</p>
        <p class="receipt-policy">{{ __('Returns within :n days at this branch.', ['n' => $r['return_period_days']]) }}</p>
    </div>
</div>
@endsection
