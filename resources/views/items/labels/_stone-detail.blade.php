{{--
    A row per stone on the left, the piece's identity on the right.

    The left column reads top to bottom as GW, the stone rows, the extra charges,
    then NW — the order the counter checks them in. OC on the right is the sum of
    every amount in that column, which is what makes the tag self-checking: if the
    figures do not add up, something is wrong with the piece, not with the print.

    Styles live in the shell (items/label.blade.php); dompdf parses one <style>.
--}}
<table class="layout">
    <tr>
        <td>
            <table class="sd-lines">
                @if ($settings->show_gross)
                    <tr>
                        <td class="c">GW</td>
                        <td class="w">{{ $grossWeight }}</td>
                        <td class="p"></td>
                        <td class="r"></td>
                        <td class="a"></td>
                    </tr>
                @endif

                @foreach ($stoneRows as $row)
                    <tr>
                        <td class="c">{{ $row['code'] }}</td>
                        <td class="w">{{ $row['weight'] }}</td>
                        <td class="p">{{ $row['pieces'] }}</td>
                        <td class="r">{{ $row['rate'] }}</td>
                        <td class="a">{{ $row['amount'] }}</td>
                    </tr>
                @endforeach

                @foreach ($chargeRows as $row)
                    <tr>
                        <td class="c">{{ $row['label'] }}</td>
                        <td class="w"></td>
                        <td class="p"></td>
                        <td class="r"></td>
                        <td class="a">{{ $row['amount'] }}</td>
                    </tr>
                @endforeach

                @if ($settings->show_net)
                    <tr>
                        <td class="c">NW</td>
                        <td class="w">{{ $netWeight }}</td>
                        <td class="p"></td>
                        <td class="r"></td>
                        <td class="a"></td>
                    </tr>
                @endif
            </table>
        </td>

        <td class="sd-right">
            @if ($shopName)
                <div class="shop">{{ $shopName }}</div>
            @endif

            <div class="code">{{ $code }}</div>

            @if ($making || $purity)
                <div class="line">
                    @if ($making)
                        LB : {{ $making }}
                    @endif
                    @if ($purity)
                        &nbsp;&nbsp;{{ $purity }}
                    @endif
                </div>
            @endif

            @if ($name)
                <div class="desc">{{ $name }}</div>
            @endif

            @if ($settings->show_net)
                <div class="line">NW : {{ $netWeight }}</div>
            @endif

            @if ($ocAmount)
                <div class="line">OC : {{ $ocAmount }}</div>
            @endif
        </td>

        @if ($qr)
            <td class="qr">
                <img src="{{ $qr }}" alt="{{ $code }}">
            </td>
        @endif
    </tr>
</table>
