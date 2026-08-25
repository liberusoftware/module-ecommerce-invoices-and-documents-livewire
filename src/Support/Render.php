<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;

/**
 * The one place a domain value becomes text, so no template invents its own.
 * The host hard-coded a dollar sign in both invoice views over a money path
 * with no currency column on it; every amount here carries the recorded code.
 */
final class Render
{
    /** A proforma issued without a series has no number. That is a fact about the document, not a missing answer. */
    public const UNNUMBERED = 'Not numbered';

    public static function money(Money $amount): string
    {
        return $amount->decimal().' '.$amount->currency;
    }

    public static function number(?string $number): string
    {
        return $number ?? self::UNNUMBERED;
    }

    public static function kind(DocumentKind $kind): string
    {
        return ucfirst(str_replace('_', ' ', $kind->value));
    }

    public static function state(DocumentState $state): string
    {
        return ucfirst($state->value);
    }

    /** Basis points as a percentage, by integer division: 2000 is 20%, 1950 is 19.5%. */
    public static function rate(int $basisPoints): string
    {
        $sign = $basisPoints < 0 ? '-' : '';
        $magnitude = abs($basisPoints);
        $fraction = rtrim(str_pad((string) ($magnitude % 100), 2, '0', STR_PAD_LEFT), '0');

        return $sign.intdiv($magnitude, 100).($fraction === '' ? '' : '.'.$fraction).'%';
    }
}
