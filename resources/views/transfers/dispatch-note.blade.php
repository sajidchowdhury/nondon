@extends('layouts.print')

@section('title', __('Dispatch Note :number', ['number' => $transfer->request_number]))

@section('content')
<style>
    @page dispatch-note { size: A4; margin: 15mm; }
    .dispatch-note { page: dispatch-note; }
    .dispatch-note { width: 180mm; margin: 0 auto; font-family: Arial, sans-serif; font-size: 12px; color: #000; }
    .dispatch-note h1 { font-size: 20px; text-align: center; margin-bottom: 4mm; }
    .dispatch-note .header { display: flex; justify-content: space-between; margin-bottom: 6mm; border-bottom: 2px solid #000; padding-bottom: 3mm; }
    .dispatch-note .header div { font-size: 11px; }
    .dispatch-note table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .dispatch-note th { text-align: left; border-bottom: 2px solid #000; padding: 2mm 1mm; font-size: 11px; }
    .dispatch-note td { border-bottom: 1px solid #ccc; padding: 2mm 1mm; font-size: 11px; }
    .dispatch-note .footer { margin-top: 8mm; border-top: 1px solid #ccc; padding-top: 4mm; font-size: 11px; text-align: center; color: #555; }
</style>
<div class="dispatch-note">
    <h1>{{ __('Dispatch Note') }}</h1>
    <div class="header">
        <div>
            <strong>{{ __('Request') }}:</strong> {{ $transfer->request_number }}<br>
            <strong>{{ __('From') }}:</strong> {{ $transfer->fromBranch?->name }}<br>
            <strong>{{ __('To') }}:</strong> {{ $transfer->toBranch?->name }}<br>
        </div>
        <div>
            <strong>{{ __('Dispatched') }}:</strong> {{ $transfer->dispatched_at?->format('Y-m-d H:i') }}<br>
            <strong>{{ __('Dispatched by') }}:</strong> {{ $transfer->dispatchedBy?->full_name ?? '' }}<br>
            <strong>{{ __('Status') }}:</strong> {{ ucfirst($transfer->status) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('#') }}</th>
                <th>{{ __('Barcode') }}</th>
                <th>{{ __('Serial') }}</th>
                <th>{{ __('Product') }}</th>
                <th>{{ __('Size') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->garment?->barcode ?? '' }}</td>
                <td>{{ $item->garment?->fiscal_year_serial ?? '' }}</td>
                <td>{{ $item->product?->name ?? '' }}</td>
                <td>{{ $item->productSize?->size_label ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($transfer->notes)
    <p><strong>{{ __('Notes') }}:</strong> {{ $transfer->notes }}</p>
    @endif

    <div class="footer">
        <p>{{ __('Total items') }}: {{ $transfer->items->count() }}</p>
        <p>{{ __('Nondon ERP — Dispatch Note') }}</p>
    </div>
</div>
@endsection
