@extends('layouts.print')

@section('title', __('Receipt :number', ['number' => $r['invoice_number']]))

@section('content')
@php
    // ADR-010: no tax/VAT line on customer-facing receipt (pending legal review).
    // This view deliberately omits any tax/VAT line — only subtotal, discount,
    // total, and payment breakdown are shown.
@endphp
<style>
    @page receipt-80mm { size: 80mm auto; margin: 2mm; }
    .receipt-80mm { page: receipt-80mm; }

    .receipt-80mm {
        width: 76mm;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        line-height: 1.4;
        color: #000;
    }
    .receipt-80mm .receipt-header { text-align: center; margin-bottom: 2mm; }
    .receipt-80mm .receipt-header h1 { font-size: 14px; font-weight: bold; }
    .receipt-80mm .receipt-header p { font-size: 10px; }
    .receipt-80mm .receipt-meta { margin-bottom: 2mm; font-size: 10px; }
    .receipt-80mm .receipt-meta div { display: flex; justify-content: space-between; }
    .receipt-80mm .receipt-customer { margin-bottom: 2mm; font-size: 10px; }
    .receipt-80mm .receipt-items { border-top: 1px dashed #000; padding-top: 1mm; margin-bottom: 2mm; }
    .receipt-80mm .receipt-item { margin-bottom: 1.5mm; }
    .receipt-80mm .receipt-item-name { font-weight: bold; }
    .receipt-80mm .receipt-item-meta { font-size: 9px; }
    .receipt-80mm .receipt-item-price { display: flex; justify-content: space-between; }
    .receipt-80mm .receipt-discount { margin-left: 2mm; }
    .receipt-80mm .receipt-final { font-weight: bold; }
    .receipt-80mm .receipt-totals { border-top: 1px dashed #000; padding-top: 1mm; margin-bottom: 2mm; }
    .receipt-80mm .receipt-totals div { display: flex; justify-content: space-between; }
    .receipt-80mm .receipt-totals .receipt-grand-total { font-weight: bold; font-size: 13px; border-top: 1px solid #000; padding-top: 1mm; margin-top: 1mm; }
    .receipt-80mm .receipt-payments { border-top: 1px dashed #000; padding-top: 1mm; margin-bottom: 2mm; font-size: 10px; }
    .receipt-80mm .receipt-payments div { display: flex; justify-content: space-between; }
    .receipt-80mm .receipt-footer { text-align: center; margin-top: 3mm; font-size: 10px; }
    .receipt-80mm .receipt-policy { font-size: 9px; margin-top: 1mm; }
</style>
<div class="receipt-80mm">
    <div class="receipt-header">
        <h1>{{ $r['branch']['name'] }}</h1>
        @if($r['branch']['address'])<p>{{ $r['branch']['address'] }}</p>@endif
        @if($r['branch']['phone'])<p>{{ __('Tel') }}: {{ $r['branch']['phone'] }}</p>@endif
    </div>

    <div class="receipt-meta">
        <div><span>{{ $r['invoice_number'] }}</span><span>{{ $r['completed_at'] }}</span></div>
        <div><span>{{ __('Salesperson') }}: {{ $r['salesperson'] }}</span></div>
    </div>

    @if($r['customer'])
    <div class="receipt-customer">
        <div>{{ __('Customer') }}: {{ $r['customer']['phone'] }}@if($r['customer']['name']) — {{ $r['customer']['name'] }}@endif</div>
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
        <div><span>{{ ucfirst($p['method']) }}@if($p['reference']) ({{ $p['reference'] }})@endif</span><span>{{ $p['amount'] }}</span></div>
        @endforeach
        <div><span>{{ __('Paid') }}</span><span>{{ $r['total_paid'] }}</span></div>
    </div>

    @if($r['notes'])
    <div class="receipt-customer">{{ __('Notes') }}: {{ $r['notes'] }}</div>
    @endif

    <div class="receipt-footer">
        <p>{{ __('Thank you for your purchase!') }}</p>
        <p class="receipt-policy">{{ __('Returns accepted within :n days at this branch.', ['n' => $r['return_period_days']]) }}</p>
    </div>
</div>
@endsection
