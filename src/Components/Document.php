<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\InvoicesAndDocumentsException;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Failures;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\RendersFailures;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\BuildRenderModel;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\FindDocument;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One document, as it was issued.
 *
 * Every value comes from the render model, built out of the document's own
 * rows. The host printed the product name and the customer through live
 * relations at render time, so renaming a product rewrote every past invoice.
 */
final class Document extends Component
{
    use RendersFailures;

    #[Locked]
    public string $tenantId;

    #[Locked]
    public string $subjectRef;

    #[Locked]
    public string $reference;

    public function render(): View
    {
        return ViewFactory::make('invoicing-livewire::document', $this->document());
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        $this->clearFailure();

        $document = (new FindDocument())($this->tenantId, $this->reference);

        // One refusal for four conditions: no such reference, another tenant's,
        // another person's, and one nobody has issued. Four answers here would
        // let a reader tell which documents exist.
        if ($document === null
            || $document->issued_at === null
            || ! CustodyPolicy::buyerMayRead($document, $this->tenantId, $this->subjectRef)) {
            $this->failure = Failures::UNAVAILABLE;

            return ['document' => null];
        }

        try {
            return ['document' => (new BuildRenderModel())($this->tenantId, $document)];
        } catch (InvoicesAndDocumentsException $failure) {
            $this->fail($failure);

            return ['document' => null];
        }
    }
}
