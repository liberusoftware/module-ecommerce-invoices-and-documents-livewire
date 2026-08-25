<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Documents;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Failures;
use Livewire\Livewire;

it('gives a second merchant its own document under a deliberately identical sale reference', function () {
    $first = issuedDocument('tenant-a', 'order-1', 'person-1', lines: [aLine('Widget bought from the first merchant')]);
    $second = issuedDocument('tenant-b', 'order-1', 'person-1', lines: [aLine('Widget bought from the second merchant')]);

    expect($first->reference)->not->toBe($second->reference);

    Livewire::test(Document::class, mountDocument($first))
        ->assertSee('Widget bought from the first merchant')
        ->assertDontSee('Widget bought from the second merchant');

    Livewire::test(Document::class, mountDocument($second))
        ->assertSee('Widget bought from the second merchant')
        ->assertDontSee('Widget bought from the first merchant');
});

it('counts the right non-zero number of lines through the tenant-restated relation', function () {
    // The guarded restatement's failure mode is reporting zero for everything,
    // which reads as isolation working. So the assertion is the right number in
    // each merchant, not merely a different one.
    $first = issuedDocument('tenant-a', 'order-1', 'person-1', lines: [aLine('One'), aLine('Two'), aLine('Three')]);
    $second = issuedDocument('tenant-b', 'order-1', 'person-1', lines: [aLine('Four'), aLine('Five')]);

    expect(substr_count(Livewire::test(Document::class, mountDocument($first))->html(), 'data-line="'))->toBe(3)
        ->and(substr_count(Livewire::test(Document::class, mountDocument($second))->html(), 'data-line="'))->toBe(2);
});

it('summarises tax per rate through the same relation, once per distinct rate', function () {
    $document = issuedDocument(lines: [
        aLine('Standard rated', 1000, 2000, 200),
        aLine('Also standard rated', 3000, 2000, 600),
        aLine('Reduced rated', 2000, 500, 100),
    ]);

    $html = Livewire::test(Document::class, mountDocument($document))->html();

    expect(substr_count($html, 'data-line="'))->toBe(3)
        ->and(substr_count($html, 'data-rate="'))->toBe(2);
});

it('lists a merchant only its own documents for the same person', function () {
    issuedDocument('tenant-a', 'order-1', 'person-1', lines: [aLine('Widget bought from the first merchant')]);
    issuedDocument('tenant-b', 'order-1', 'person-1', lines: [aLine('Widget bought from the second merchant')]);

    $first = Livewire::test(Documents::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1'])->html();
    $second = Livewire::test(Documents::class, ['tenantId' => 'tenant-b', 'subjectRef' => 'person-1'])->html();

    expect(substr_count($first, 'data-reference="'))->toBe(1)
        ->and(substr_count($second, 'data-reference="'))->toBe(1)
        ->and($first)->not->toBe($second);
});

it('refuses a reader who names the right document and the wrong merchant', function () {
    // The host dropped the ownership filter entirely for `super_admin` and
    // `admin`, so one merchant's admin read every merchant's invoices on any
    // host. Standing here is the buyer reference the document froze, and the
    // tenant is part of every check.
    $document = issuedDocument('tenant-b', 'order-1', 'person-1');

    Livewire::test(Document::class, [
        'tenantId' => 'tenant-a',
        'subjectRef' => 'person-1',
        'reference' => $document->reference,
    ])->assertSee(Failures::UNAVAILABLE);
});
