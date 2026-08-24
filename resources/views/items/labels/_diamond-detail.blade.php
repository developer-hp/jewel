{{--
    Identity on the left, then the diamonds across the tag: a sieve cell per stone
    (weight-pieces), and beside them DW / DR / DS for each.

    Both halves render from the same collection, in the same order, so the sieve
    list and the detail groups can never fall out of step.

    Styles live in the shell (items/label.blade.php); dompdf parses one <style>.
--}}
<table class="layout">
    <tr>
        <td class="dd-identity">
            @if ($shopName)
                <div class="shop">{{ $shopName }}</div>
            @endif

            <div class="code">{{ $code }}</div>

            @if ($settings->show_net)
                <div class="line">NW : {{ $netWeight }}</div>
            @endif

            @if ($making)
                <div class="line">LB : {{ $making }}</div>
            @endif
        </td>

        @if ($sieves)
            <td>
                <table class="dd-lines">
                    @foreach ($sieves as $sieve)
                        <tr>
                            <td>{{ $sieve }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        @endif

        <td>
            <table class="dd-lines">
                @foreach ($diamondRows as $row)
                    <tr>
                        <td>DW : {{ $row['dw'] }}</td>
                        @if ($row['dr'] !== '')
                            <td>DR : {{ $row['dr'] }}</td>
                        @endif
                        @if ($row['ds'] !== '')
                            <td>DS : {{ $row['ds'] }}</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </td>

        @if ($qr)
            <td class="qr">
                <img src="{{ $qr }}" alt="{{ $code }}">
            </td>
        @endif
    </tr>
</table>
