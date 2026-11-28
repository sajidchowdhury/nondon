@extends('layouts.print')

@section('title', 'Print Labels — '.$format['name'])

@section('content')
    <div class="labels">
        @foreach($labels as $label)
            <div class="label-block {{ str_replace('_', '-', $format['key']) }}" style="font-size: {{ $format['font_size_pt'] }}pt;">
                <img
                    class="label-barcode"
                    src="{{ $label['barcode_image'] }}"
                    alt="Barcode {{ $label['barcode'] }}"
                    style="height: {{ $format['barcode_height_px'] }}px;"
                >
                <div class="label-serial">{{ $label['serial'] }}</div>
                <div class="label-name">{{ $label['product_name'] }}</div>
                @if($format['layout'] === 'standard' && $label['size_label'])
                    <div class="label-size">{{ $label['size_label'] }}</div>
                @endif
                @if($showPrice && array_key_exists('standard_price', $label) && $label['standard_price'] !== null)
                    <div class="label-price">{{ number_format((float) $label['standard_price'], 2) }}</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Open the browser print dialog once the sheet is fully rendered --}}
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
@endsection
