<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support;

use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\InvoicesAndDocumentsException;
use Livewire\Attributes\Locked;

/**
 * The one property every component uses to render a classified refusal. Locked,
 * because a browser able to write it could clear a refusal or manufacture one.
 */
trait RendersFailures
{
    /** What the reader is told about the last refusal, or null if there wasn't one. */
    #[Locked]
    public ?string $failure = null;

    protected function clearFailure(): void
    {
        $this->failure = null;
    }

    protected function fail(InvoicesAndDocumentsException $failure): void
    {
        $this->failure = Failures::classify($failure);
    }
}
