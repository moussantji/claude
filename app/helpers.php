<?php

use Illuminate\Support\Number;

if (! function_exists('fcfa')) {
    /**
     * Format an amount using the configured currency (FCFA by default).
     * FCFA has no minor units, so we render whole numbers with a thin space
     * thousands separator, e.g. 15 000 FCFA.
     */
    function fcfa($amount): string
    {
        $currency = config('streamwave.currency', 'FCFA');
        $formatted = number_format((float) $amount, 0, ',', ' ');

        return $formatted . ' ' . $currency;
    }
}
