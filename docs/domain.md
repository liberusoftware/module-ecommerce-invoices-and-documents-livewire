# What this surface presents, and every decision behind it

This package is a one-to-one adapter over `liberusoftware/ecommerce-invoices-and-documents`. It
contains no business rules. Every figure it shows was written by that module's actions and is read
back through its published queries and its `CustodyPolicy`.

## 1. The fact that shaped it

The host's invoice was a live view of the catalogue and the customer, not a document. The lines were
a `belongsToMany` straight onto `Product` and the rendered description was the product's *current*
name (`resources/views/invoices/show.blade.php:27`); the buyer was `$invoice->customer->name`
(`:11`), an attribute `Customer` does not have. So renaming a product rewrote every past invoice,
deleting one removed lines from under a header total that still printed, and the store global scope
on `Product` could return fewer lines than the invoice was issued with.

Both components here render **only what the document froze**. Nothing traverses a relation into
another module, and `DocumentTest` proves it the only way that means anything: it issues a document,
unbinds the sale seam entirely, and asserts every printed value is still there.

## 2. Two components, and why not more

| Alias | What it answers |
|---|---|
| `invoicing::documents` | Which documents were issued to me, and what does each say it is |
| `invoicing::document` | One document, in full, as it was issued |

**No download or render component.** The domain's `DocumentRenderer` seam turns a render model into
a file, and nothing is bound to it by default. A component that asked `RenderDocument` on every page
view would generate a file to discover whether one could be generated, and then have nothing to do
with it: handing bytes to a browser is a route's response, not a Livewire render. The render model
*is* the document, and this package shows it. A host that wants a PDF binds a renderer and serves it
from a route of its own.

**No delivery history.** The domain records delivery attempts as rows — the fact the host never had
— but publishes no query for them and does not carry them on the render model. Reading
`$document->deliveries()` here would be this package reaching past the published surface into a
relation. It is listed as a gap in the report instead.

**No cross-tenant "all my documents".** `ExportParticipantRecord` walks a person's documents across
every tenant, deliberately, because a person is not a merchant's property. Rendering that on a
storefront would show one merchant's page the documents of another. It belongs to a subject-access
path, not to a shopper page.

## 3. What a reader is allowed to see

Standing is the buyer reference the document froze, answered by `CustodyPolicy::buyerMayRead`, which
takes the tenant on every call. Never a role name: the host dropped the ownership filter entirely for
`hasRole(['super_admin', 'admin'])` (`app/Http/Controllers/InvoiceController.php:22`, `:42`), so one
merchant's admin read every merchant's invoices.

Two further conditions are this surface's, not the domain's:

- **A document nobody issued is not shown.** `issued_at === null` covers a draft and a draft that was
  voided, both of which have no number. Showing a reader an unnumbered draft would be the host's
  defect in a new place.
- **A redacted document is not shown**, which the domain already answers: erasure rewrites
  `buyer_ref`, so the claim no longer matches.

`buyerMayRead` returning true for a draft is a gap in the domain, and is reported as one.

## 4. One refusal, not four

"No such reference", "that document belongs to another merchant", "that document belongs to another
person" and "that document has not been issued" are one sentence with one meaning. Four
distinguishable answers over a table of financial documents is an enumeration oracle, and the host
was already publishing the row count in the number.

`Failures` classifies every concrete domain exception, so one added later arrives as a refusal rather
than as an unhandled error. `MoneyMismatch` is the one distinct message: reaching it means the reader
already proved the document is theirs, so naming the condition leaks nothing and hiding it would be
the worse answer.

## 5. One message, and no `resubmittable` or `transient`

The presentation brief pairs `Support\Failures` with a `Failure` value object carrying
`resubmittable` and `transient` flags. **This package ships neither flag**, deliberately.

Both components are reads. Every public property is `#[Locked]`, no method writes, and a boundary
case asserts that no domain action which changes a document is even named in `src/`. There is
therefore no reachable state in which either flag is true, and the build brief calls a rule nothing
exercises a lying constraint. One nullable message is the honest shape here.

## 6. Money and numbers

Every amount is spelled by `Support\Render` from the document's own minor units and the currency the
document recorded — `12.00 GBP`, `1200 JPY`. There is no symbol table, no `number_format`, no float,
and a boundary case asserts no currency symbol appears anywhere in the package. Fault 5 was a system
printing a dollar sign over a money path with no currency column on it at all.

Tax rates are spelled from basis points by integer division: `2000` is `20%` and `1950` is `19.5%`.
The per-rate block is the domain's summary, grouped and summed by the domain; this package adds
nothing.

A document is filed under the number it carries. A proforma issued without a series reads **Not
numbered**, which is a fact about that document rather than a missing answer — and never a database
key, which is what the host printed under the heading "Invoice #".

## 7. Idempotency

None, and none is possible to need. This surface submits nothing: there is no form, no action method
and no write. The domain's natural key — `(tenant_id, kind, source_ref)` — arbitrates every write
that does exist, and a client-held key would be strictly weaker than a key that already exists.

## 8. Rendering

`View::make()` through the `View` facade, never the `view()` helper: `view()` lives in
`laravel/framework` and this package requires `illuminate/*` only. A boundary case lists `view`
alongside `config`, `app` and the rest, because the reference Livewire package calls it in every
component and its own boundary suite missed it.
