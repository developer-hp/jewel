<div><strong>{{ number_format((float) $item->net_weight, 3) }}</strong> <span class="text-muted fs-12">g net</span></div>
<small class="text-muted">
    {{ number_format((float) $item->gross_weight, 3) }} gross
    @if ((float) $item->stone_weight_grams > 0)
        · −{{ number_format((float) $item->stone_weight_grams, 3) }} st
    @endif
    @if ((float) $item->diamond_weight_grams > 0)
        · −{{ number_format((float) $item->diamond_weight_grams, 3) }} dia
    @endif
    @if ((float) $item->other_deduction > 0)
        · −{{ number_format((float) $item->other_deduction, 3) }} oth
    @endif
</small>
