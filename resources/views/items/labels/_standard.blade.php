{{--
    The original tag: identity on the left, then up to three columns of KEY: value
    pairs, then the QR. Stones and diamonds are summed into one line each — a
    110 x 18 mm tag cannot carry a line per stone category. The stone-detail layout
    exists for the pieces that need that.

    Styles live in the shell (items/label.blade.php); dompdf parses one <style>.
--}}
@php
    // Rows flow into up to three columns so a long tag stays readable and a short
    // one wraps instead of clipping. Four lines is what 18 mm holds at ~6pt.
    $columns = array_chunk($rows, 4);
@endphp

<table class="layout">
    <tr>
        <td class="std-identity">
            @if ($shopName)
                <div class="shop">{{ $shopName }}</div>
            @endif
            <div class="code">{{ $code }}</div>
            <div class="net">({{ $netWeight }})</div>
        </td>

        @foreach ($columns as $column)
            <td>
                <table class="fields">
                    @foreach ($column as $row)
                        <tr>
                            <td class="k">{{ $row['label'] }}:</td>
                            <td class="v">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        @endforeach

        @if ($qr)
            <td class="qr">
                <img src="{{ $qr }}" alt="{{ $code }}">
            </td>
        @endif
    </tr>
</table>
