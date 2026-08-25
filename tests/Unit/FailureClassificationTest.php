<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\DocumentsAreImmutable;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\InvoicesAndDocumentsException;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\MoneyMismatch;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Failures;

/** @return list<class-string<InvoicesAndDocumentsException>> */
function concreteDomainExceptions(): array
{
    $directory = dirname(__DIR__, 2).'/vendor/liberusoftware/ecommerce-invoices-and-documents/src/Exceptions';
    $classes = [];

    foreach ((array) glob($directory.'/*.php') as $path) {
        $class = 'Liberu\\Ecommerce\\InvoicesAndDocuments\\Exceptions\\'.basename((string) $path, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, InvoicesAndDocumentsException::class)) {
            continue;
        }

        if ((new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}

it('finds the domain exceptions it claims to classify', function () {
    // Guards the guard: a table asserted complete over an empty list is a green
    // test that reached nothing.
    expect(concreteDomainExceptions())->not->toBeEmpty();
});

it('classifies every concrete domain exception', function () {
    // So a domain exception added later arrives as a refusal a reader can
    // understand rather than as an unhandled error.
    foreach (concreteDomainExceptions() as $class) {
        expect(array_key_exists($class, Failures::table()))->toBeTrue("[{$class}] is unclassified.");
    }
});

it('answers a refusal for a failure nobody classified', function () {
    $unknown = new class('Something new.') extends InvoicesAndDocumentsException {};

    expect(Failures::classify($unknown))->toBe(Failures::UNAVAILABLE);
});

it('tells a reader nothing about which document exists', function () {
    // Two wrong answers must be indistinguishable. "Not for this tenant" is the
    // only shape NotFound has on this surface, and it reads like every other
    // reason a document is not shown.
    expect(Failures::classify(NotFound::document()))->toBe(Failures::UNAVAILABLE)
        ->and(Failures::classify(NotFound::series('INV')))->toBe(Failures::UNAVAILABLE)
        ->and(Failures::classify(DocumentsAreImmutable::forDeletion('doc-1')))->toBe(Failures::UNAVAILABLE);
});

it('separates a document that cannot be totalled from one that is not available', function () {
    // Reaching this means the reader already proved the document is theirs, so
    // naming the condition leaks nothing and hiding it would be a worse answer.
    expect(Failures::classify(MoneyMismatch::currency('GBP', 'EUR')))->toBe(Failures::UNTOTALLED)
        ->and(Failures::UNTOTALLED)->not->toBe(Failures::UNAVAILABLE);
});

it('never invites a reader to wait', function () {
    // Nothing on a read-only surface resolves by waiting: every message here is
    // a fact about a document, not about a queue.
    foreach ([...array_values(Failures::table()), Failures::UNAVAILABLE, Failures::UNTOTALLED] as $message) {
        foreach (['shortly', 'try again', 'in a moment', 'please wait', 'later'] as $phrase) {
            expect(mb_strtolower($message))->not->toContain($phrase);
        }
    }
});
