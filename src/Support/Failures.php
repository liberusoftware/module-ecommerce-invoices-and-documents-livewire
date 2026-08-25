<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support;

use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\DocumentsAreImmutable;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\InvoicesAndDocumentsException;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\MoneyMismatch;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;

/**
 * Every domain failure classified once rather than guessed at each call site.
 * One message and no resubmittable or transient flag: nothing here submits.
 * See docs/domain.md sections 4 and 5.
 */
final class Failures
{
    /** The uniform refusal. Every reason a document is not shown reads exactly like every other. */
    public const UNAVAILABLE = 'That document is not available.';

    /** Safe to distinguish: reaching it means the reader already proved the document is theirs. */
    public const UNTOTALLED = 'This document could not be totalled, so it is not being shown. Nothing about it has changed.';

    /**
     * Exposed so the suite can assert the table is complete over every concrete
     * domain exception, and a new one cannot arrive as an unhandled error.
     *
     * @return array<class-string<InvoicesAndDocumentsException>, string>
     */
    public static function table(): array
    {
        return [
            // Uniform: "not for this tenant" reads exactly like "no such reference".
            NotFound::class => self::UNAVAILABLE,

            // A document carrying two currencies or two exponents cannot be summed.
            MoneyMismatch::class => self::UNTOTALLED,

            // Write-path only, and unreachable from two read-only components.
            // Classified so that reaching it renders a refusal rather than a 500.
            DocumentsAreImmutable::class => self::UNAVAILABLE,
        ];
    }

    public static function classify(InvoicesAndDocumentsException $failure): string
    {
        return self::table()[$failure::class] ?? self::UNAVAILABLE;
    }
}
