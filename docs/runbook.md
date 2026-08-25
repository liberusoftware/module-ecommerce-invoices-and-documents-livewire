# Runbook

## The list is empty and the reader says they have invoices

In order:

1. **The reader is being identified by something other than the buyer reference.** `subjectRef` must
   be the same opaque reference the host gave `SaleSource` as the buyer at draft. A host passing a
   user id where it once passed a customer id shows every reader an empty list, and no error.
2. **The tenant is wrong.** `tenantId` scopes every query. A storefront resolving to the wrong team
   shows an empty list rather than another merchant's documents, which is the correct failure.
3. **Nothing has been issued.** A drafted document is not shown, and neither is a draft that was
   voided. Check `state` and `issued_at` on the rows: `issued_at` null is the whole condition.
4. **Erasure has run.** `ForgetParticipant` rewrites `buyer_ref` on any document past its retention
   window, so the reader's claim no longer matches. That is erasure working, and it is not
   reversible.

## A document reads "That document is not available."

One message covers four conditions on purpose, so this cannot be diagnosed from the page. From the
database, in order: does a row with that `reference` exist in that `tenant_id`; is its `buyer_ref`
the reader's; and is `issued_at` set. A reference from another merchant and a reference that never
existed are indistinguishable to a reader by design.

## A document reads "This document could not be totalled"

The document's stored `currency` or `currency_exponent` will not construct a `Money`. The module's
own guards make this unreachable through its actions, so a document in this state arrived some other
way — a restored backup, an import, a hand-edited row. **Do not fix it with an `UPDATE`**: an issued
document is immutable and the model will refuse. Establish what the document said when it was issued,
void it with a reason, and issue a correcting document.

## A proforma reads "Not numbered"

Correct, and not a fault. A proforma may not be filed under a fiscal series, and one issued with no
series has no number. If a number is wanted, open a non-fiscal series and issue proformas under it.

## A number in the list looks wrong

Check `invoicing_series` before anything else. The number is `prefix + zero-padded next_value`, spent
inside the transaction that wrote the document. If a gapless series has spent numbers with a hole in
them, `CheckSeriesContinuity` names the missing values, and that is the domain's alarm rather than
this package's — nothing here can allocate, burn or renumber anything.

## Nothing renders at all

The provider registers components through `Livewire::resolveMissingComponent()`, which the
application consults for unresolved names. If `invoicing::documents` resolves to nothing, the module
is not in `MODULES_ENABLED`, or another package registered a resolver that answers before this one
and returns a wrong class rather than null.

## A page is slow

The list summarises each document from its own frozen lines, which is one query per row. It is
bounded by `limit` (25 by default) — lower it. Neither component caches: a document cannot change
after it is issued, so a cache here would only ever hide a document that had just been issued.
