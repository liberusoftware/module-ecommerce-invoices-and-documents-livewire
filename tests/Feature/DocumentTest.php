<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftCreditNote;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\IssueDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\VoidDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Failures;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Tests\Doubles\FakeSaleSource;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document as DocumentRow;
use Livewire\Livewire;

it('files the document under its own number and never under a key', function () {
    $document = issuedDocument();

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee('INV-00001')
        ->assertSee('Invoice INV-00001');
});

it('renders what the document froze after the sale is gone', function () {
    // The whole claim. The host read the line description off the live product
    // and the buyer off the live customer row, so renaming a product rewrote
    // every past invoice and erasing a customer rewrote every past buyer.
    $document = issuedDocument(lines: [aLine('A blue widget', 2500, 2000, 500)]);

    $source = Config::get('invoices-and-documents.seams.sale');
    expect($source)->toBeInstanceOf(FakeSaleSource::class)
        ->and($source->asked)->toBe(1);

    $source->forget();
    Config::set('invoices-and-documents.seams.sale', null);

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee('A blue widget')
        ->assertSee('A Buyer')
        ->assertSee('A Merchant')
        ->assertSee('25.00 GBP')
        ->assertSee('30.00 GBP');

    expect($source->asked)->toBe(1);
});

it('carries the document currency on every amount', function () {
    // Fault 5: neither `orders` nor `invoices` had a currency column and both
    // views hard-coded a dollar sign over whatever the number happened to be.
    $document = issuedDocument(lines: [aLine('A widget', 1000, 2000, 200, currency: 'JPY', exponent: 0)]);

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee('1000 JPY')
        ->assertSee('200 JPY')
        ->assertSee('1200 JPY')
        ->assertDontSee('GBP');
});

it('states tax per line and summarises it per distinct rate', function () {
    $document = issuedDocument(lines: [
        aLine('Standard rated', 1000, 2000, 200),
        aLine('Reduced rated', 2000, 500, 100),
        aLine('Also standard rated', 3000, 2000, 600),
    ]);

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee('20%')
        ->assertSee('5%')
        // The per-rate block: 40.00 net at 20% and 20.00 net at 5%.
        ->assertSee('40.00 GBP')
        ->assertSee('20.00 GBP')
        ->assertSee('69.00 GBP');
});

it('names the document a credit note corrects', function () {
    $invoice = issuedDocument();

    $outcome = (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [aLine('A widget returned', 1000, 2000, 200)]);
    $creditNote = DocumentRow::query()->findOrFail($outcome->id);
    (new IssueDocument())('tenant-a', $creditNote, 'INV');

    Livewire::test(Document::class, mountDocument($creditNote->fresh() ?? $creditNote))
        ->assertSee('Credit note INV-00002')
        ->assertSee('Corrects INV-00001');
});

it('says a proforma is not numbered rather than showing something else', function () {
    $proforma = issuedDocument(kind: DocumentKind::Proforma, code: null);

    Livewire::test(Document::class, mountDocument($proforma))
        ->assertSee('Proforma Not numbered');
});

it('shows a voided document as void rather than hiding it', function () {
    $document = issuedDocument();
    (new VoidDocument())('tenant-a', $document, 'Raised against the wrong sale');

    Livewire::test(Document::class, mountDocument($document->fresh() ?? $document))
        ->assertSee('INV-00001')
        ->assertSee('Void');
});

it('refuses a document nobody issued, in the words it refuses a missing one', function () {
    $draft = drafted();

    Livewire::test(Document::class, mountDocument($draft))
        ->assertSee(Failures::UNAVAILABLE)
        ->assertDontSee('A widget');
});

it('answers another person and a reference that does not exist identically', function () {
    // Two wrong answers must be indistinguishable, or the page publishes which
    // documents exist.
    $document = issuedDocument();

    $stranger = Livewire::test(Document::class, mountDocument($document, 'person-2'));
    $missing = Livewire::test(Document::class, [
        'tenantId' => 'tenant-a',
        'subjectRef' => 'person-1',
        'reference' => 'no-such-reference',
    ]);

    expect($stranger->get('failure'))->toBe(Failures::UNAVAILABLE)
        ->and($missing->get('failure'))->toBe(Failures::UNAVAILABLE);

    $stranger->assertDontSee('A widget');
    $missing->assertDontSee('A widget');
});

it('refuses a document held by another merchant', function () {
    $document = issuedDocument('tenant-b', 'order-1', 'person-1');

    Livewire::test(Document::class, [
        'tenantId' => 'tenant-a',
        'subjectRef' => 'person-1',
        'reference' => $document->reference,
    ])->assertSee(Failures::UNAVAILABLE)->assertDontSee('A widget');
});

it('shows the buyer and seller tax registrations the document froze', function () {
    $document = issuedDocument();

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee('Tax registration GB123456789');
});

it('refuses a document whose stored amounts will not combine', function () {
    // A restored backup, an import, a hand-edited row: the module's own guards
    // make this unreachable, so the page must still answer rather than fail.
    $document = issuedDocument();

    DB::table('invoicing_documents')->where('reference', $document->reference)->update(['currency_exponent' => 9]);

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee(Failures::UNTOTALLED)
        ->assertDontSee('A widget');
});

it('renders the note the document froze', function () {
    $document = issuedDocument(note: 'Payable within 30 days of issue.');

    Livewire::test(Document::class, mountDocument($document))
        ->assertSee('Payable within 30 days of issue.');
});
