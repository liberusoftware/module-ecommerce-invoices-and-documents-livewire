# Adoption

## 1. Install

The domain package is not on Packagist, so the host declares where it lives. This package carries the
same `repositories` entry for the same reason.

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-invoices-and-documents" },
        { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-invoices-and-documents-livewire" }
    ]
}
```

```bash
composer require liberusoftware/ecommerce-invoices-and-documents-livewire:^0.1
```

Installing boots nothing. `extra.laravel.providers` is empty on purpose: the host's module manager
registers the provider only when the module is named in `MODULES_ENABLED`.

```dotenv
MODULES_ENABLED=ecommerce-invoices-and-documents,ecommerce-invoices-and-documents-livewire
```

The domain module must be enabled too. This package renders documents; it does not create them.

## 2. What the host must bind

Nothing, for these two components to work — and that is the point of the seams being unbound.

| Seam | Bound by the host when | Effect on this package if unbound |
|---|---|---|
| `Contracts\SaleSource` | Documents are drafted from orders | None. A document already issued renders identically; that is the freeze |
| `Contracts\DocumentRenderer` | A PDF or other file is wanted | None. Neither component asks for a file |
| `Contracts\DocumentTransport` | Documents are emailed | None. Neither component delivers anything |

A host that never binds `SaleSource` has no documents to show, which the list renders as "No
documents have been issued to you." rather than as an error.

## 3. Rendering the components

Both take the tenant and the reader. **Neither derives either one**: the host decides who is asking
and which merchant's storefront they are on, and passes both as mount arguments. `subjectRef` is the
same opaque buyer reference the host handed to `SaleSource` when the document was drafted — it is
what the document froze, and it is what standing is decided on.

```blade
{{-- The reader's documents --}}
<livewire:is component="invoicing::documents"
    :tenant-id="$team->getKey()"
    :subject-ref="$customerReference" />

{{-- One document --}}
<livewire:is component="invoicing::document"
    :tenant-id="$team->getKey()"
    :subject-ref="$customerReference"
    :reference="$documentReference" />
```

`invoicing::documents` also takes `:limit`, defaulting to 25. Every property on both components is
`#[Locked]`: a browser cannot widen the limit, change the tenant, or claim to be somebody else.

`$documentReference` is the reference the domain minted — never a primary key. It is what
`invoicing::documents` puts in each row's `data-reference` attribute, and what `FindDocument` takes.

## 4. Idempotency

There is none in this package, and there is nothing here for one to protect. Both components are
reads: no form, no action method, no write. The domain arbitrates every write it owns with a natural
key — `(tenant_id, kind, source_ref)` — which exists before any client does, so a client-supplied
idempotency key would be strictly weaker than the key already there.

## 5. What the host deletes when it adopts this

| Host file | Why it is not adopted |
|---|---|
| `resources/views/invoices/index.blade.php` | Prints the `invoices` primary key as "Invoice #" and hard-codes a dollar sign |
| `resources/views/invoices/show.blade.php` | Reads the product name and the customer through live relations at render time; `:11` reads a `customer.name` that does not exist |
| `app/Http/Livewire/InvoicePdf.php` | Returns a view that was never written, in a directory Livewire 4 does not discover, with no PDF library installed |
| `app/Http/Controllers/InvoiceController.php` | Drops the ownership filter for two role names, and fatals on a guest |

The controller's routes (`routes/web.php:192–193`) become host routes that mount these two
components. Standing moves from the controller to `CustodyPolicy`, and the invoice number stops being
a database key.

## 6. Styling

The templates carry class names and no styles: `invoicing-documents`, `invoicing-document`,
`invoicing-failure`, `invoicing-lines`, `invoicing-tax-summary`, `invoicing-totals`. Publish them to
change the markup:

```bash
php artisan vendor:publish --tag=views
```

Published copies do not receive fixes. Prefer styling the classes.
