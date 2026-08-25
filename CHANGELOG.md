# Changelog

## 0.1.0

The shopper's side of Invoices and Documents, extracted from a host invoice view that read the
product name, the customer name and the customer email through live relations at render time.

### Components

- `invoicing::documents` — the documents issued to one person, newest first, each filed under the
  number the document carries. Bounded by a locked limit.
- `invoicing::document` — one document: seller, buyer, frozen lines with quantity, unit price, net,
  rate, tax and gross, a tax summary per distinct rate, totals, and the number of the document a
  credit note corrects.

### Decisions

- **Every value comes from what the document froze.** Nothing traverses a relation into another
  module, and the suite proves it by unbinding the sale seam and asserting the render is unchanged.
- **No database key reaches a page.** A document is addressed by the reference the domain minted and
  each row is keyed on it. The host printed the `invoices` primary key under the heading "Invoice #".
- **Every amount carries the currency the document recorded**, spelled from minor units by integer
  division. No symbol, no `number_format`, no float. The host hard-coded a dollar sign in both views
  over a money path with no currency column anywhere on it.
- **A proforma with no series reads "Not numbered"**, which is a fact about that document rather than
  a missing answer.
- **Standing is the buyer reference the document froze**, through `CustodyPolicy`, never a role name.
- **One refusal for four conditions.** No such reference, another merchant's, another person's and
  one nobody issued read identically, because four answers over a table of financial documents is an
  enumeration oracle.
- **A document nobody issued is not shown**, which covers a draft and a draft that was voided.
- **One nullable failure message, and no `resubmittable` or `transient` flags.** This surface submits
  nothing, so both flags would be false in every reachable state, which is a lying constraint.
- **`View::make()`, never the `view()` helper**, which lives in `laravel/framework` rather than in
  the `illuminate/*` components this package declares. The boundary suite lists `view` among the
  banned helpers.

### Deliberately not shipped

- **A download or render component.** Asking the renderer seam on every page view would produce a
  file to find out whether one could be produced, and handing bytes to a browser is a route's job.
- **Delivery history.** The domain records delivery attempts but publishes no query for them and does
  not carry them on the render model; reading the relation here would reach past the published
  surface.
- **A cross-tenant list of a person's documents.** `ExportParticipantRecord` is person-wide by design
  and belongs to a subject-access path, not to a storefront page.
- **Any idempotency key.** Nothing here submits, and the domain's natural key already arbitrates
  every write that exists.
