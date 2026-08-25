@use(Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Render)

{{-- Every value below was frozen at issue. Nothing on this page reads the catalogue or the customer. --}}
<div class="invoicing-document">
    @if ($document === null)
        <p class="invoicing-failure">{{ $failure }}</p>
    @else
        <header class="invoicing-document-header">
            <h1>{{ Render::kind($document->kind) }} {{ Render::number($document->number) }}</h1>
            <p class="invoicing-document-state">{{ Render::state($document->state) }}</p>
            <p class="invoicing-document-issued">Issued {{ $document->issuedAt?->toDateString() }}</p>

            @if ($document->correctsNumber !== null)
                <p class="invoicing-document-corrects">Corrects {{ $document->correctsNumber }}</p>
            @endif
        </header>

        <section class="invoicing-parties">
            <div class="invoicing-party invoicing-seller">
                <h2>From</h2>
                <p>{{ $document->seller->name }}</p>
                <p>{{ $document->seller->address }}</p>
                @if ($document->seller->taxId !== null)
                    <p>Tax registration {{ $document->seller->taxId }}</p>
                @endif
            </div>
            <div class="invoicing-party invoicing-buyer">
                <h2>To</h2>
                <p>{{ $document->buyer->name }}</p>
                <p>{{ $document->buyer->address }}</p>
                @if ($document->buyer->taxId !== null)
                    <p>Tax registration {{ $document->buyer->taxId }}</p>
                @endif
            </div>
        </section>

        <table class="invoicing-lines">
            <thead>
                <tr>
                    <th scope="col">Description</th>
                    <th scope="col">Quantity</th>
                    <th scope="col">Unit price</th>
                    <th scope="col">Net</th>
                    <th scope="col">Rate</th>
                    <th scope="col">Tax</th>
                    <th scope="col">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $position => $line)
                    <tr wire:key="line-{{ $position }}" data-line="{{ $position }}">
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->quantity() }}</td>
                        <td>{{ Render::money($line->unitNet) }}</td>
                        <td>{{ Render::money($line->net) }}</td>
                        <td>{{ Render::rate($line->taxRateBasisPoints) }}</td>
                        <td>{{ Render::money($line->tax) }}</td>
                        <td>{{ Render::money($line->gross) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="invoicing-tax-summary">
            <caption>Tax</caption>
            <thead>
                <tr>
                    <th scope="col">Rate</th>
                    <th scope="col">Net</th>
                    <th scope="col">Tax</th>
                    <th scope="col">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->summary->byRate as $rate)
                    <tr wire:key="rate-{{ $rate->rateBasisPoints }}" data-rate="{{ $rate->rateBasisPoints }}">
                        <td>{{ Render::rate($rate->rateBasisPoints) }}</td>
                        <td>{{ Render::money($rate->net) }}</td>
                        <td>{{ Render::money($rate->tax) }}</td>
                        <td>{{ Render::money($rate->gross) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="invoicing-totals">
            <dt>Net</dt>
            <dd>{{ Render::money($document->summary->net) }}</dd>
            <dt>Tax</dt>
            <dd>{{ Render::money($document->summary->tax) }}</dd>
            <dt>Total</dt>
            <dd>{{ Render::money($document->summary->gross) }}</dd>
        </dl>

        @if ($document->note !== null)
            <p class="invoicing-note">{{ $document->note }}</p>
        @endif
    @endif
</div>
