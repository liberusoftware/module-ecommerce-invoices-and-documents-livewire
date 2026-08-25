# Ecommerce: Invoices and Documents — Livewire

The shopper's side of `liberusoftware/ecommerce-invoices-and-documents`: two Livewire 4 components
that show a person the documents issued to them, and one of those documents in full.

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white) ![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-invoices-and-documents-livewire?sort=semver)](https://github.com/liberusoftware/module-ecommerce-invoices-and-documents-livewire/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-invoices-and-documents-livewire/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-invoices-and-documents-livewire/actions/workflows/tests.yml)

## The one fact that shaped it

The invoice this replaces was a live view of the catalogue and the customer rather than a document.
Its line descriptions were the products' *current* names, its buyer was the current customer row, and
the only frozen values were a quantity, a price and a header total. Renaming a product rewrote every
past invoice; deleting one removed lines from under a total that still printed.

So every value these components render comes from what the document froze at issue. The suite proves
it by unbinding the sale seam entirely and asserting the page is unchanged.

## What it publishes

| Component | Alias | Shows |
|---|---|---|
| `Components\Documents` | `invoicing::documents` | The documents issued to one person, newest first, each under the number it carries |
| `Components\Document` | `invoicing::document` | One document: parties, frozen lines, tax per line and per rate, totals |

## What it owns

- Deciding what a reader sees, from the domain's answer about what they may see.
- Spelling a domain value as text: an amount with its currency, a rate from basis points, a kind, a
  state, and a document with no number.
- Classifying every domain failure into one sentence a reader may be shown.

## What it does not own

- Any business rule. It drafts, issues, numbers, voids, delivers and erases nothing, and a boundary
  case asserts no action that changes a document is even named in `src/`.
- Authorization. `CustodyPolicy` answers standing; this package calls it and never re-derives it.
- Files. The domain's renderer seam is the host's to bind and a route's to serve.
- Who the reader is. The host passes the tenant and the buyer reference; neither is derived here and
  neither is writable by a browser.

## Requirements

- **PHP 8.5**, **Laravel 13**, **Livewire 4**
- `liberusoftware/ecommerce-invoices-and-documents` `^0.1`

## Quick start

See [docs/adoption.md](docs/adoption.md) — the domain package is not on Packagist, so the host needs
a `repositories` entry, and both modules need naming in `MODULES_ENABLED`.

## Documentation

- [What this surface presents, and why](docs/domain.md)
- [Adoption](docs/adoption.md)
- [Runbook](docs/runbook.md)
- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
