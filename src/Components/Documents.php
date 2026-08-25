<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\InvoicesAndDocumentsException;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\RendersFailures;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\ListDocuments;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\SummariseDocument;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Which documents were issued to me, and what does each one say it is?"
 *
 * The host labelled the `invoices` primary key "Invoice #" on this exact page,
 * and dropped the ownership filter entirely for two role names. Every row here
 * is filed under the document's own number, and standing is the buyer reference
 * the document froze.
 */
final class Documents extends Component
{
    use RendersFailures;

    #[Locked]
    public string $tenantId;

    #[Locked]
    public string $subjectRef;

    /** How many rows to show. Locked, because a browser able to widen it could ask this page for every document a person has. */
    #[Locked]
    public int $limit = 25;

    public function render(): View
    {
        return ViewFactory::make('invoicing-livewire::documents', $this->documents());
    }

    /** @return array<string, mixed> */
    private function documents(): array
    {
        $this->clearFailure();

        $rows = [];

        foreach ((new ListDocuments())($this->tenantId, buyerRef: $this->subjectRef) as $document) {
            if (count($rows) >= $this->limit) {
                break;
            }

            // A document nobody issued is not a document. That covers a draft
            // and a draft that was voided, both of which have no number.
            if ($document->issued_at === null || ! CustodyPolicy::buyerMayRead($document, $this->tenantId, $this->subjectRef)) {
                continue;
            }

            try {
                $gross = (new SummariseDocument())($this->tenantId, $document)->gross;
            } catch (InvoicesAndDocumentsException $failure) {
                $this->fail($failure);

                return ['documents' => []];
            }

            $rows[] = [
                'reference' => $document->reference,
                'number' => $document->number,
                'kind' => $document->kind,
                'state' => $document->state,
                'issuedAt' => $document->issued_at,
                'gross' => $gross,
            ];
        }

        return ['documents' => $rows];
    }
}
