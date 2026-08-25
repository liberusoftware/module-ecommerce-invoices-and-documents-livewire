@use(Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Support\Render)

{{-- Every row is filed under the document's own number. Nothing here is a database key. --}}
<div class="invoicing-documents">
    @if ($failure !== null)
        <p class="invoicing-failure">{{ $failure }}</p>
    @elseif ($documents === [])
        <p class="invoicing-empty">No documents have been issued to you.</p>
    @else
        <table class="invoicing-documents-table">
            <thead>
                <tr>
                    <th scope="col">Number</th>
                    <th scope="col">Document</th>
                    <th scope="col">Issued</th>
                    <th scope="col">Status</th>
                    <th scope="col">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $row)
                    <tr wire:key="{{ $row['reference'] }}" data-reference="{{ $row['reference'] }}">
                        <td>{{ Render::number($row['number']) }}</td>
                        <td>{{ Render::kind($row['kind']) }}</td>
                        <td>{{ $row['issuedAt']->toDateString() }}</td>
                        <td>{{ Render::state($row['state']) }}</td>
                        <td>{{ Render::money($row['gross']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
