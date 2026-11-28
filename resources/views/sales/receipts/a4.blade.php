@extends('layouts.print')

@section('title', __('Receipt :number', ['number' => $r['invoice_number']]))

@section('content')
@php
    // ADR-010: no tax/VAT line on customer-facing receipt (pending legal review).
    // This view deliberately omits any tax/VAT line — only subtotal, discount,
    // total, and payment breakdown are shown.
@endphp
<style>
    @page receipt-a4 { size: A4; margin: 15mm; }
    .receipt-a4 { page: receipt-a4; }

    .receipt-a4 {
        width: 180mm;
        margin: 0 auto;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #000;
    }
    .receipt-a4 .receipt-header { text-align: center; margin-bottom: 8mm; border-bottom: 2px solid #000; padding-bottom: 4mm; }
    .receipt-a4 .receipt-header h1 { font-size: 22px; font-weight: bold; }
    .receipt-a4 .receipt-header p { font-size: 11px; margin-top: 1mm; }
    .receipt-a4 .receipt-meta { display: flex; justify-content: space-between; margin-bottom: 6mm; }
    .receipt-a4 .receipt-meta .receipt-meta-block { font-size: 11px; }
    .receipt-a4 .receipt-meta .receipt-meta-block div { margin-bottom: 1mm; }
    .receipt-a4 .receipt-customer { margin-bottom: 6mm; font-size: 11px; }
    .receipt-a4 .receipt-items { margin-bottom: 6mm; }
    .receipt-a4 table.receipt-table { width: 100%; border-collapse: collapse; }
    .receipt-a4 table.receipt-table th { text-align: left; border-bottom: 2px solid #000; padding: 2mm 1mm; font-size: 11px; }
    .receipt-a4 table.receipt-table td { border-bottom: 1px solid #ccc; padding: 2mm 1mm; font-size: 11px; }
    .receipt-a4 table.receipt-table td.receipt-final { font-weight: bold; text-align: right; }
    .receipt-a4 table.receipt-table td.receipt-num { text-align: right; }
    .receipt-a4 .receipt-totals { width: 60mm; margin-left: auto; margin-bottom: 6mm; font-size: 11px; }
    .receipt-a4 .receipt-totals div { display: flex; justify-content: space-between; padding: 1mm 0; }
    .receipt-a4 .receipt-totals .receipt-grand-total { font-weight: bold; font-size: 14px; border-top: 2px solid #000; padding-top: 2mm; margin-top: 2mm; }
    .receipt-a4 .receipt-payments { width: 60mm; margin-left: auto; margin-bottom: 6mm; font-size: 11px; border-top: 1px solid #ccc; padding-top: 2mm; }
    .receipt-a4 .receipt-payments div { display: flex; justify-content: space-between; padding: 1mm 0; }
    .receipt-a4 .receipt-footer { text-align: center; margin-top: 10mm; border-top: 1px solid #ccc; padding-top: 4mm; font-size: 11px; }
    .receipt-a4 .receipt-policy { font-size: 10px; margin-top: 2mm; color: #555; }
</style>
<div class="receipt-a4">
    <div class="receipt-header">
        <h1>{{ $r['branch']['name'] }}</h1>
        @if($r['branch']['address'])<p>{{ $r['branch']['address'] }}</p>@endif
        @if($r['branch']['phone'])<p>{{ __('Tel') }}: {{ $r['branch']['phone'] }}</p>@endif
    </div>

    <div class="receipt-meta">
        <div class="receipt-meta-block">
            <div><strong>{{ __('Invoice') }}:</strong> {{ $r['invoice_number'] }}</div>
            <div><strong>{{ __('Date') }}:</strong> {{ $r['completed_at'] }}</div>
            <div><strong>{{ __('Salesperson') }}:</strong> {{ $r['salesperson'] }}</div>
        </div>
        @if($r['customer'])
        <div class="receipt-meta-block">
            <div><strong>{{ __('Customer') }}:</strong> {{ $r['customer']['phone'] }}</div>
            @if($r['customer']['name'])<div><strong>{{ __('Name') }}:</strong> {{ $r['customer']['name'] }}</div>@endif
        </div>
        @endif
    </div>

    <div class="receipt-items">
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('Barcode') }}</th>
                    <th class="receipt-num">{{ __('Price') }}</th>
                    <th class="receipt-num">{{ __('Disc') }}</th>
                    <th class="receipt-num">{{ __('Final') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($r['items'] as $item)
                <tr>
                    <td>{{ $item['product_name'] }}@if($item['size_label']) ({{ $item['size_label'] }})@endif</td>
                    <td>{{ $item['barcode'] }}</td>
                    <td class="receipt-num">{{ $item['standard_price'] }}</td>
                    <td class="receipt-num">{{ ((float) $item['discount']) > 0 ? '-'.$item['discount'] : '' }}</td>
                    <td class="receipt-num receipt-final">{{ $item['final_price'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
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
        <div><strong><span>{{ __('Paid') }}</span><span>{{ $r['total_paid'] }}</span></strong></div>
    </div>

    @if($r['notes'])
    <div class="receipt-customer"><strong>{{ __('Notes') }}:</strong> {{ $r['notes'] }}</div>
    @endif

    <div class="receipt-footer">
        <p>{{ __('Thank you for your purchase!') }}</p>
        <p class="receipt-policy">{{ __('Returns accepted within :n days at this branch.', ['n' => $r['return_period_days']]) }}</p>
    </div>
</div>
@endsection
