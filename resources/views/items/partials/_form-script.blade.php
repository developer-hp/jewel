{{--
    Shared by the item form and the Order Items screen, which builds a piece to order
    and therefore needs the identical stone, diamond and live-weight behaviour.

    Expects, on the page including it:
      #metal_type_id  #purity_id  #gross_weight  #other_deduction  .extra-charge
      the two _stone-section modals, and the #sum-* summary spans.
    Selectors that find nothing (#item_group_id on the order screen) simply no-op.

    Variables: $caratToGram $puritiesByMetal $purityRates $item $stoneRows $diamondRows
--}}
@push('js')
    <script>
        $(function () {
            const CARAT_TO_GRAM = {{ $caratToGram }};
            const puritiesByMetal = @js($puritiesByMetal);
            // Per-gram rate for each purity, so the summary can price the piece live.
            const purityRates = @js($purityRates);
            const selectedPurity = @js(old('purity_id', $item->purity_id));

            // Stones and diamonds share the stones[] array; offsetting the diamond
            // indices keeps new rows from overwriting each other.
            const nextIndex = { stone: {{ $stoneRows->count() }}, diamond: 1000 + {{ $diamondRows->count() }} };

            function populatePurities(keepSelection) {
                const metalId = $('#metal_type_id').val();
                const list = puritiesByMetal[metalId] || [];
                const $purity = $('#purity_id');
                const previous = keepSelection ? selectedPurity : $purity.val();

                $purity.empty().append(
                    $('<option>').val('').text(list.length ? 'Select…' : 'No purities for this metal type')
                );

                list.forEach(function (purity) {
                    $purity.append($('<option>').val(purity.id).text(purity.name));
                });

                if (previous) {
                    $purity.val(String(previous));
                }
                refreshTotals();
            }

            function refreshRow($row) {
                const $option = $row.find('.stone-master option:selected');
                const unit = $option.data('unit') || '';
                const masterRate = parseFloat($option.data('rate'));

                const pieces = parseInt($row.find('.stone-pieces').val(), 10) || 0;
                const carat = parseFloat($row.find('.stone-carat').val()) || 0;
                const grams = parseFloat($row.find('.stone-grams').val()) || 0;

                const $rate = $row.find('.stone-rate');
                // Blank rate falls back to the master's default, matching the server.
                const rate = $rate.val() === '' ? (isNaN(masterRate) ? 0 : masterRate) : parseFloat($rate.val()) || 0;
                $rate.attr('placeholder', isNaN(masterRate) ? 'master rate' : masterRate);

                let amount = 0;
                if (unit === 'piece') amount = rate * pieces;
                else if (unit === 'carat') amount = rate * carat;
                else if (unit === 'gram') amount = rate * grams;
                else if (unit === 'fixed') amount = rate;

                $row.find('.stone-unit').text(unit ? unit : '—');
                $row.find('.stone-amount').text(amount.toFixed(2));

                return {
                    grams: grams,
                    carat: carat,
                    amount: amount,
                    deduct: $row.find('.stone-deduct').is(':checked'),
                };
            }

            function refreshSection(section) {
                const $table = $('table[data-section="' + section + '"]');
                let grams = 0, amount = 0, deducted = 0, carat = 0, rows = 0;

                $table.find('.stone-row').each(function () {
                    const result = refreshRow($(this));
                    grams += result.grams;
                    amount += result.amount;
                    carat += result.carat;
                    rows += 1;
                    if (result.deduct) deducted += result.grams;
                });

                $table.find('.section-grams').text(grams.toFixed(4));
                $table.find('.section-amount').text(amount.toFixed(2));

                // The rows are behind a popup, so say what is in there.
                const $summary = $('#' + section + '-trigger-summary');
                if (rows === 0) {
                    $summary.text('No ' + section + 's added');
                } else {
                    $summary.html(
                        '<strong>' + rows + '</strong> row' + (rows === 1 ? '' : 's') +
                        ' · ' + carat.toFixed(3) + ' ct' +
                        ' · ₹' + amount.toFixed(2)
                    );
                }

                return { deducted: deducted, amount: amount };
            }

            function refreshTotals() {
                const stone = refreshSection('stone');
                const diamond = refreshSection('diamond');

                const gross = parseFloat($('#gross_weight').val()) || 0;
                const other = parseFloat($('#other_deduction').val()) || 0;
                const net = gross - stone.deducted - diamond.deducted - other;

                $('#sum-gross').text(gross.toFixed(3));
                $('#sum-stone').text(stone.deducted.toFixed(3));
                $('#sum-diamond').text(diamond.deducted.toFixed(3));
                $('#sum-other').text(other.toFixed(3));
                $('#sum-net').text(net.toFixed(3));
                $('#net-warning').toggleClass('d-none', net > 0);

                const perGram = parseFloat(purityRates[$('#purity_id').val()] || 0);
                $('#sum-metal').text((net > 0 ? net * perGram : 0).toFixed(2));
                $('#sum-stone-value').text((stone.amount + diamond.amount).toFixed(2));

                let extra = 0;
                $('.extra-charge').each(function () { extra += parseFloat($(this).val()) || 0; });
                $('#sum-extra').text(extra.toFixed(2));
            }

            $('#metal_type_id').on('change', function () { populatePurities(false); });
            $('#purity_id, #gross_weight, #other_deduction, .extra-charge').on('input change', refreshTotals);

            // Carat and gram mirror each other. `syncing` stops the write to one field
            // from firing the handler that would immediately write back to the other.
            let syncing = false;

            $(document).on('input', '.stone-carat', function () {
                if (syncing) return;
                syncing = true;
                const carat = parseFloat($(this).val());
                $(this).closest('tr').find('.stone-grams')
                    .val(isNaN(carat) ? '' : (carat * CARAT_TO_GRAM).toFixed(4));
                syncing = false;
                refreshTotals();
            });

            $(document).on('input', '.stone-grams', function () {
                if (syncing) return;
                syncing = true;
                const grams = parseFloat($(this).val());
                $(this).closest('tr').find('.stone-carat')
                    .val(isNaN(grams) ? '' : (grams / CARAT_TO_GRAM).toFixed(3));
                syncing = false;
                refreshTotals();
            });

            $(document).on('input change', '.stone-row input, .stone-row select', refreshTotals);

            $('.add-stone-row').on('click', function () {
                const section = $(this).data('section');
                const html = $('#' + section + '-row-template').html().replace(/__INDEX__/g, nextIndex[section]++);

                $('table[data-section="' + section + '"] .stone-rows').append(html);
                // The sections now sit in a modal, not a card.
                $(this).closest('.modal-content').find('.empty-hint').remove();
                refreshTotals();
            });

            $(document).on('click', '.remove-row', function () {
                $(this).closest('tr').remove();
                refreshTotals();
            });

            $('#item_group_id').on('change', function () {
                const $option = $(this).find('option:selected');

                $('#code-preview').text($option.data('next') || 'auto-generated on save');

                // Item name follows the group. Overwrites whatever is there, by design.
                const groupName = $option.data('name');
                if (groupName) {
                    $('#name').val(groupName);
                }
            });

            // Enter inside a popup row would submit the whole item; keep it in the row.
            $(document).on('keydown', '.modal input', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });

            populatePurities(true);
            refreshTotals();
        });
    </script>
@endpush
