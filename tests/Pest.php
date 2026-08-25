<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\IssueDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\OpenSeries;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Party;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Sale;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Tests\Doubles\FakeSaleSource;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Tests\TestCase;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;

pest()->extends(TestCase::class)->in('Feature', 'Unit');

/*
 * No test inherits a binding. Half of what this surface claims is about what it
 * renders with nothing bound, and a leaked binding would prove the opposite.
 */
pest()->beforeEach(function (): void {
    Config::set('invoices-and-documents.seams.sale', null);
    Config::set('invoices-and-documents.seams.renderer', null);
    Config::set('invoices-and-documents.seams.transport', null);
    Config::set('invoices-and-documents.retention.years', null);
})->in('Feature', 'Unit');

/*
 * Fixtures build rows through the domain's own actions. Tests may name a domain
 * model; src/ may not, and a boundary case asserts that.
 */

function aLine(
    string $description = 'A widget',
    int $netMinor = 1000,
    int $rateBp = 2000,
    int $taxMinor = 200,
    int $quantityMilli = 1000,
    string $currency = 'GBP',
    int $exponent = 2,
): Line {
    return new Line(
        $description,
        $quantityMilli,
        new Money($netMinor, $currency, $exponent),
        new Money($netMinor, $currency, $exponent),
        $rateBp,
        new Money($taxMinor, $currency, $exponent),
        new Money($netMinor + $taxMinor, $currency, $exponent),
    );
}

/**
 * A sale whose stated totals are the sum of its lines, because `DraftDocument`
 * refuses one where they are not.
 *
 * @param  list<Line>  $lines
 */
function aSale(string $saleReference, string $buyerRef, array $lines): Sale
{
    $currency = Frozen::currencyOf($lines) ?? ['GBP', 2];
    $summary = Frozen::summarise($lines, $currency[0], $currency[1]);

    return new Sale(
        $saleReference,
        new Party('seller-1', 'A Merchant', '1 Trade Street', 'GB123456789'),
        new Party($buyerRef, 'A Buyer', '2 Home Road', null, $buyerRef.'@example.test'),
        $lines,
        $summary->net,
        $summary->tax,
        $summary->gross,
    );
}

/** @param  list<Line>|null  $lines */
function bindSale(string $tenantId, string $saleRef, string $buyerRef, ?array $lines = null): FakeSaleSource
{
    $source = Config::get('invoices-and-documents.seams.sale');
    $source = $source instanceof FakeSaleSource ? $source : new FakeSaleSource();

    $source->offer($tenantId, $saleRef, aSale($saleRef, $buyerRef, $lines ?? [aLine()]));
    Config::set('invoices-and-documents.seams.sale', $source);

    return $source;
}

function openSeries(string $tenantId = 'tenant-a', string $code = 'INV', bool $fiscal = true, int $startAt = 1): string
{
    (new OpenSeries())($tenantId, $code, $code.'-', 5, $fiscal, true, $startAt);

    return $code;
}

/** @param  list<Line>|null  $lines */
function drafted(
    string $tenantId = 'tenant-a',
    string $saleRef = 'order-1',
    string $buyerRef = 'person-1',
    DocumentKind $kind = DocumentKind::Invoice,
    ?array $lines = null,
    ?string $note = null,
): Document {
    bindSale($tenantId, $saleRef, $buyerRef, $lines);
    $outcome = (new DraftDocument())($tenantId, $kind, $saleRef, $note);

    return Document::query()->findOrFail($outcome->id);
}

/** @param  list<Line>|null  $lines */
function issuedDocument(
    string $tenantId = 'tenant-a',
    string $saleRef = 'order-1',
    string $buyerRef = 'person-1',
    DocumentKind $kind = DocumentKind::Invoice,
    ?array $lines = null,
    ?string $code = 'INV',
    bool $fiscal = true,
    ?string $note = null,
): Document {
    $document = drafted($tenantId, $saleRef, $buyerRef, $kind, $lines, $note);

    if ($code !== null) {
        openSeries($tenantId, $code, $fiscal);
    }

    (new IssueDocument())($tenantId, $document, $code);

    return $document->fresh() ?? $document;
}

/** The three mount arguments the document component takes. */
function mountDocument(Document $document, string $subjectRef = 'person-1'): array
{
    return [
        'tenantId' => $document->tenant_id,
        'subjectRef' => $subjectRef,
        'reference' => $document->reference,
    ];
}

/** @return list<string> every file under the given package directories, absolute. */
function filesUnder(string ...$directories): array
{
    $paths = [];

    foreach ($directories as $directory) {
        $path = dirname(__DIR__).'/'.$directory;

        if (! is_dir($path)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $paths[] = $file->getPathname();
            }
        }
    }

    sort($paths);

    return $paths;
}

/** @return list<string> */
function sourceFiles(): array
{
    return array_values(array_filter(
        filesUnder('src'),
        static fn (string $path): bool => str_ends_with($path, '.php'),
    ));
}

/**
 * A source file with its comments stripped. Every rule below is about what the
 * code does, and these files name the host faults they exist to prevent — so a
 * naive grep finds the prose describing the defect rather than the defect.
 */
function sourceCode(string $path): string
{
    $contents = (string) file_get_contents($path);

    if (str_ends_with($path, '.blade.php')) {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
    }

    if (! str_ends_with($path, '.php')) {
        return $contents;
    }

    $code = '';

    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}
