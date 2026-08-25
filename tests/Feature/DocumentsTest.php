<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\ForgetParticipant;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\VoidDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Documents;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Failures;
use Livewire\Livewire;

function listFor(string $tenantId = 'tenant-a', string $subjectRef = 'person-1', int $limit = 25): array
{
    return ['tenantId' => $tenantId, 'subjectRef' => $subjectRef, 'limit' => $limit];
}

it('lists a reader their documents under the numbers the documents carry', function () {
    issuedDocument(saleRef: 'order-1');
    issuedDocument(saleRef: 'order-2', kind: DocumentKind::Receipt);

    Livewire::test(Documents::class, listFor())
        ->assertSee('INV-00001')
        ->assertSee('INV-00002')
        ->assertSee('Invoice')
        ->assertSee('Receipt')
        ->assertSee('12.00 GBP')
        ->assertSee('Issued');
});

it('addresses each row by the reference the domain minted', function () {
    // Fault 1: the host's list page printed the `invoices` primary key under
    // the heading "Invoice #", which counts through every merchant on the
    // deployment.
    $document = issuedDocument();

    Livewire::test(Documents::class, listFor())
        ->assertSee('data-reference="'.$document->reference.'"', escape: false);
});

it('shows nothing nobody issued', function () {
    drafted(saleRef: 'order-1');
    $abandoned = drafted(saleRef: 'order-2', kind: DocumentKind::Receipt);
    (new VoidDocument())('tenant-a', $abandoned, 'Never going to be issued');

    Livewire::test(Documents::class, listFor())
        ->assertSee('No documents have been issued to you.')
        ->assertDontSee('A widget');
});

it('shows a reader nothing issued to somebody else', function () {
    issuedDocument(buyerRef: 'person-1');

    Livewire::test(Documents::class, listFor(subjectRef: 'person-2'))
        ->assertSee('No documents have been issued to you.')
        ->assertDontSee('INV-00001');
});

it('bounds the list at its locked limit', function () {
    issuedDocument(saleRef: 'order-1');
    issuedDocument(saleRef: 'order-2', kind: DocumentKind::Receipt);
    issuedDocument(saleRef: 'order-3', kind: DocumentKind::Proforma, code: null);

    // Newest first, so the two most recent survive the limit.
    Livewire::test(Documents::class, listFor(limit: 2))
        ->assertSee('Proforma')
        ->assertSee('Receipt')
        ->assertDontSee('INV-00001');
});

it('keeps a voided document on the list rather than hiding it', function () {
    $document = issuedDocument();
    (new VoidDocument())('tenant-a', $document, 'Raised against the wrong sale');

    Livewire::test(Documents::class, listFor())
        ->assertSee('INV-00001')
        ->assertSee('Void');
});

it('drops a document from the list once erasure has redacted it', function () {
    // Erasure rewrites the buyer reference the reader's claim is made on, so a
    // redacted document is nobody's to read. Retention outranks erasure until
    // the window has passed, which is what this travels past.
    Config::set('invoices-and-documents.retention.years', 1);
    issuedDocument();

    Carbon::setTestNow(Carbon::now()->addYears(2));
    (new ForgetParticipant())('person-1');
    Carbon::setTestNow();

    Livewire::test(Documents::class, listFor())
        ->assertSee('No documents have been issued to you.')
        ->assertDontSee('INV-00001');
});

it('refuses the whole list when one document will not total', function () {
    // Suppress every figure derived from the missing input, not only the one
    // that could not be produced.
    $document = issuedDocument();

    DB::table('invoicing_documents')->where('reference', $document->reference)->update(['currency_exponent' => 9]);

    Livewire::test(Documents::class, listFor())
        ->assertSee(Failures::UNTOTALLED)
        ->assertDontSee('INV-00001');
});
