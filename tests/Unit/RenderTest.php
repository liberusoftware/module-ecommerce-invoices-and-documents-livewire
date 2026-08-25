<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Render;

it('spells an amount with the currency the document recorded', function () {
    expect(Render::money(new Money(1200, 'GBP')))->toBe('12.00 GBP')
        ->and(Render::money(new Money(1200, 'JPY', 0)))->toBe('1200 JPY')
        ->and(Render::money(new Money(-1250, 'EUR')))->toBe('-12.50 EUR')
        ->and(Render::money(new Money(0, 'USD')))->toBe('0.00 USD');
});

it('never spells an amount with a symbol', function () {
    // Fault 5: the host hard-coded a dollar sign in both invoice views over a
    // money path with no currency column anywhere on it.
    foreach ([new Money(1200, 'USD'), new Money(1200, 'GBP'), new Money(1200, 'JPY', 0)] as $amount) {
        expect(Render::money($amount))->not->toContain('$')
            ->and(Render::money($amount))->not->toContain('£')
            ->and(Render::money($amount))->not->toContain('€');
    }
});

it('says a document is not numbered rather than borrowing something else', function () {
    // A proforma issued without a series has no number. The host filled that
    // gap with the primary key on every invoice it ever showed a customer.
    expect(Render::number(null))->toBe('Not numbered')
        ->and(Render::number('INV-00001'))->toBe('INV-00001');
});

it('names each kind and each state', function () {
    expect(Render::kind(DocumentKind::Invoice))->toBe('Invoice')
        ->and(Render::kind(DocumentKind::CreditNote))->toBe('Credit note')
        ->and(Render::kind(DocumentKind::Receipt))->toBe('Receipt')
        ->and(Render::kind(DocumentKind::Proforma))->toBe('Proforma')
        ->and(Render::state(DocumentState::Draft))->toBe('Draft')
        ->and(Render::state(DocumentState::Issued))->toBe('Issued')
        ->and(Render::state(DocumentState::Delivered))->toBe('Delivered')
        ->and(Render::state(DocumentState::Void))->toBe('Void');
});

it('spells a tax rate from basis points without a float', function () {
    expect(Render::rate(2000))->toBe('20%')
        ->and(Render::rate(1950))->toBe('19.5%')
        ->and(Render::rate(0))->toBe('0%')
        ->and(Render::rate(5))->toBe('0.05%')
        ->and(Render::rate(10000))->toBe('100%')
        ->and(Render::rate(-2000))->toBe('-20%');
});
