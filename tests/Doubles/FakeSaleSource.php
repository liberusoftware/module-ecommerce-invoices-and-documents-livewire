<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Tests\Doubles;

use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\SaleSource;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Sale;

/** A sale the module may read exactly once, at draft. `forget()` is how a test proves it never reads it again. */
final class FakeSaleSource implements SaleSource
{
    /** @var array<string, Sale> */
    private array $sales = [];

    public int $asked = 0;

    public function offer(string $tenantId, string $saleReference, Sale $sale): void
    {
        $this->sales[$tenantId.'|'.$saleReference] = $sale;
    }

    public function forget(): void
    {
        $this->sales = [];
    }

    public function sale(string $tenantId, string $saleReference): ?Sale
    {
        $this->asked++;

        return $this->sales[$tenantId.'|'.$saleReference] ?? null;
    }
}
