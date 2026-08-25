<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Documents;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\InvoicesAndDocumentsLivewireServiceProvider;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * The mounted contract of every component this package registers, iterated from
 * the provider's constant rather than from a hand-written list, so a component
 * added without deciding its property partition fails the build.
 *
 * The writable set here is empty and that is the decision, not an accident of
 * scope: both components are reads of documents somebody else wrote, and a
 * reader has nothing to say about a document that has been issued.
 */

/** @return array<int, array{class-string<Component>}> */
function registeredComponents(): array
{
    // Nested rows: a flat list of class-strings is PHP's callable-array syntax,
    // and Pest calls it instead of iterating it.
    return array_map(
        static fn (string $class): array => [$class],
        array_values(InvoicesAndDocumentsLivewireServiceProvider::COMPONENTS),
    );
}

/** @return list<ReflectionProperty> */
function publicStateOf(string $component): array
{
    return array_values(array_filter(
        (new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC),
        static fn (ReflectionProperty $property): bool => ! $property->isStatic(),
    ));
}

it('publishes exactly the aliases the documentation promises', function () {
    expect(InvoicesAndDocumentsLivewireServiceProvider::COMPONENTS)->toBe([
        'invoicing::documents' => Documents::class,
        'invoicing::document' => Document::class,
    ]);
});

it('resolves every registered component by its published name', function () {
    foreach (InvoicesAndDocumentsLivewireServiceProvider::COMPONENTS as $name => $class) {
        expect(Livewire::isDiscoverable($name))->toBeTrue("[{$name}] does not resolve.");
        expect(app('livewire')->new($name))->toBeInstanceOf($class);
    }
});

it('resolves nothing it did not publish', function () {
    // resolveMissingComponent() is consulted for every unresolved name in the
    // application, so a resolver answering broadly would hijack another
    // package's components.
    expect(Livewire::isDiscoverable('invoicing::document-desk'))->toBeFalse()
        ->and(Livewire::isDiscoverable('loyalty::points-balance'))->toBeFalse();
});

it('marks every public property either locked or validated, never both and never neither', function (string $component) {
    $properties = publicStateOf($component);

    expect($properties)->not->toBeEmpty();

    foreach ($properties as $property) {
        $locked = $property->getAttributes(Locked::class) !== [];
        $validated = $property->getAttributes(Validate::class) !== [];
        $name = $component.'::$'.$property->getName();

        expect($locked || $validated)->toBeTrue("{$name} is neither #[Locked] nor #[Validate].");
        expect($locked && $validated)->toBeFalse("{$name} is both #[Locked] and #[Validate].");
    }
})->with(registeredComponents());

it('states the locked set of every component exactly, and leaves nothing writable', function (string $component, array $expectedLocked) {
    $writable = [];
    $locked = [];

    foreach (publicStateOf($component) as $property) {
        $property->getAttributes(Validate::class) !== []
            ? $writable[] = $property->getName()
            : $locked[] = $property->getName();
    }

    sort($writable);
    sort($locked);

    expect($writable)->toBe([]);
    expect($locked)->toBe($expectedLocked);
})->with([
    [Documents::class, ['failure', 'limit', 'subjectRef', 'tenantId']],
    [Document::class, ['failure', 'reference', 'subjectRef', 'tenantId']],
]);

it('refuses a client write to a locked property at runtime', function (string $component, array $mount, string $property) {
    // The attribute is a claim; this is the check that Livewire honours it.
    Livewire::test($component, $mount)->set($property, 'tampered');
})->with([
    [Documents::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1'], 'tenantId'],
    [Documents::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1'], 'subjectRef'],
    [Documents::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1'], 'limit'],
    [Documents::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1'], 'failure'],
    [Document::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1', 'reference' => 'nope'], 'tenantId'],
    [Document::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1', 'reference' => 'nope'], 'subjectRef'],
    [Document::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1', 'reference' => 'nope'], 'reference'],
    [Document::class, ['tenantId' => 'tenant-a', 'subjectRef' => 'person-1', 'reference' => 'nope'], 'failure'],
])->throws(CannotUpdateLockedPropertyException::class);

it('holds no database key and no money value as component state', function (string $component) {
    // Fault 1: the host printed the `invoices` primary key and labelled it
    // "Invoice #". A document is addressed here by the reference the domain
    // minted, and no amount is ever client-side state.
    foreach (publicStateOf($component) as $property) {
        $name = mb_strtolower($property->getName());

        foreach (['id', 'key', 'price', 'total', 'amount', 'minor', 'money', 'currency'] as $forbidden) {
            expect($name === $forbidden)->toBeFalse($component.'::$'.$property->getName());
        }

        $type = $property->getType();

        expect($type instanceof ReflectionNamedType && $type->getName() === 'float')->toBeFalse();
    }
})->with(registeredComponents());
