<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\InvoicesAndDocumentsLivewireServiceProvider;

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

it('reaches for no host application namespace', function () {
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/(?:use|new|extends|implements)\s+App\\\\/');
    }
});

it('imports no other presentation framework', function () {
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toContain('Filament\\');
    }
});

it('depends on no sibling module', function () {
    $composer = json_decode((string) file_get_contents(packageRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    $liberu = array_keys(array_filter(
        [...$composer['require'], ...$composer['require-dev']],
        static fn (string $package): bool => str_starts_with($package, 'liberusoftware/'),
        ARRAY_FILTER_USE_KEY,
    ));

    sort($liberu);

    expect($liberu)->toBe([
        'liberusoftware/ecommerce-invoices-and-documents',
        'liberusoftware/package-testbench',
    ]);

    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/Liberu\\\\Ecommerce\\\\(?!InvoicesAndDocuments)/');
    }
});

it('never names a domain model', function () {
    // The -api rule, adopted here in full. Everything this package renders
    // arrives from a published query; a surface that reached for a model would
    // have taken a shortcut past whatever that query was enforcing, and in this
    // module the query is the freeze.
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/use Liberu\\\\.+\\\\Models\\\\/');
    }
});

it('never writes anything at all', function () {
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        foreach (['->save(', '->create(', '->update(', '->delete(', 'forceFill', 'firstOrCreate', 'increment(', 'decrement(', 'DB::'] as $write) {
            expect($source)->not->toContain($write, $path);
        }
    }
});

it('calls no action that changes a document', function () {
    // Every write in this domain belongs to an operator surface or to the host.
    // A reader loading a page must not draft, issue, void, number or deliver
    // anything, and must not erase anybody.
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        foreach (['DraftDocument', 'DraftCreditNote', 'IssueDocument', 'VoidDocument', 'RecordDelivery', 'BurnNumber', 'OpenSeries', 'ForgetParticipant'] as $action) {
            expect($source)->not->toContain($action, $path);
        }
    }
});

it('decides standing by the buyer reference and never by a role', function () {
    // Fault 14: `hasRole(['super_admin', 'admin'])` dropped the ownership
    // filter entirely, in a second controller, one wave after the same defect.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        foreach (['hasRole', 'Gate::', 'hasPermission', 'is_admin', 'super_admin'] as $forbidden) {
            expect($source)->not->toContain($forbidden, $path);
        }
    }
});

it('never aggregates over a relation that restates the tenant', function () {
    // withCount(), whereHas() and friends build the relation from a fresh
    // instance whose tenant_id is null. This package stays out of the argument
    // by reading only through loaded parents.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        expect($source)->not->toContain('withCount')
            ->and($source)->not->toContain('whereHas')
            ->and($source)->not->toContain('withExists')
            ->and($source)->not->toMatch('/->has\(/');
    }
});

it('uses no join, no raw SQL and no query-builder table access', function () {
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        expect($source)->not->toMatch('/->(?:left|right|inner|cross)?[Jj]oin(?:Sub|Where)?\(/');
        expect($source)->not->toContain('DB::table');
        expect($source)->not->toContain('whereRaw');
        expect($source)->not->toContain('DB::raw');
    }
});

it('uses no framework-foundation helper that illuminate/support does not ship', function () {
    // config(), app(), now() and view() live in laravel/framework, not in
    // illuminate/support. They pass CI because the testbench drags the
    // framework in, and are a lying constraint for a consumer who installed
    // what this package declared. `view` is on this list deliberately: the
    // reference Livewire package calls it in every component and its own
    // boundary suite missed it.
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        foreach (['config', 'app', 'auth', 'now', 'resolve', 'request', 'trans', 'session', 'dispatch', 'route', 'url', 'view'] as $helper) {
            expect($source)->not->toMatch('/(?<!function )(?<![\w>$:])'.$helper.'\s*\(/', $helper.'() in '.$path);
        }

        expect($source)->not->toMatch('/(?<![\w>$:])__\s*\(/');
    }
});

it('mints nothing with a package it did not declare', function () {
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toContain('Str::ulid')
            ->and(sourceCode($path))->not->toContain('Str::uuid');
    }
});

it('has no float and no rounding anywhere in the source or the templates', function () {
    // Money is minor units and a percentage is spelled by integer division. The
    // only division in this package is intdiv().
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        expect($source)->not->toMatch('/\bfloat\b/')
            ->and($source)->not->toContain('(float)')
            ->and($source)->not->toContain('round(')
            ->and($source)->not->toContain('number_format');
    }
});

it('hard-codes no currency symbol', function () {
    // Fault 5, exactly as the host shipped it: `${{ ... }}` over a money path
    // with no currency column anywhere on it.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        foreach (['${{', '$ {{', "'$'", '"$"', '£', '€', '¥', '₹'] as $symbol) {
            expect($source)->not->toContain($symbol, $path);
        }
    }
});

it('puts no database key on a page', function () {
    // Fault 1: the host printed the `invoices` primary key and labelled it
    // "Invoice #". A document is addressed by the reference the domain minted.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        expect($source)->not->toMatch('/->id\b/')
            ->and($source)->not->toContain('getKey')
            ->and($source)->not->toContain('document_id')
            ->and($source)->not->toContain('primaryKey');
    }
});

it('registers its declared provider and boots nothing by discovery', function () {
    $composer = json_decode((string) file_get_contents(packageRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents(packageRoot().'/module.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel']['providers'] ?? [])->toBe([])
        ->and($manifest['provider'])->toBe(InvoicesAndDocumentsLivewireServiceProvider::class)
        ->and($manifest['category'])->toBe('presentation')
        ->and(App::getProvider(InvoicesAndDocumentsLivewireServiceProvider::class))->not->toBeNull()
        ->and($composer['version'])->toBe($manifest['version'])
        ->and($composer['extra']['liberu']['name'])->toBe($manifest['name'])
        ->and(array_keys($manifest['requires']['packages']))->toBe(['liberusoftware/ecommerce-invoices-and-documents']);
});

it('binds nothing and registers no route', function () {
    $provider = sourceCode(packageRoot().'/src/InvoicesAndDocumentsLivewireServiceProvider.php');

    expect($provider)->not->toContain('->bind(')
        ->and($provider)->not->toContain('->singleton(')
        ->and($provider)->not->toContain('loadRoutesFrom')
        ->and($provider)->not->toContain('loadMigrationsFrom');
});

it('ships a view for every component it registers', function () {
    foreach (InvoicesAndDocumentsLivewireServiceProvider::COMPONENTS as $name => $class) {
        $view = (string) preg_replace('/^invoicing::/', '', $name);

        expect(file_exists(packageRoot().'/resources/views/'.$view.'.blade.php'))
            ->toBeTrue("Missing a view for [{$name}].");
    }
});

it('ships every document a module repository owes its consumers', function () {
    foreach (['README.md', 'LICENSE.md', 'CHANGELOG.md', 'docs/domain.md', 'docs/adoption.md', 'docs/runbook.md'] as $file) {
        expect(file_exists(packageRoot().'/'.$file))->toBeTrue("Missing {$file}.");
    }
});

it('documents every component alias and how a host renders it', function () {
    $readme = (string) file_get_contents(packageRoot().'/README.md');
    $adoption = (string) file_get_contents(packageRoot().'/docs/adoption.md');

    foreach (array_keys(InvoicesAndDocumentsLivewireServiceProvider::COMPONENTS) as $alias) {
        expect($readme)->toContain($alias)
            ->and($adoption)->toContain($alias);
    }
});

it('tells the host what it owes these components', function () {
    $adoption = (string) file_get_contents(packageRoot().'/docs/adoption.md');

    expect($adoption)->toContain('"type": "vcs"')
        ->and($adoption)->toContain('MODULES_ENABLED')
        ->and($adoption)->toContain('SaleSource')
        ->and($adoption)->toContain('subjectRef')
        ->and($adoption)->toContain('idempotency');
});

it('never invites a reader to wait', function () {
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = mb_strtolower(sourceCode($path));

        foreach (['shortly', 'try again', 'in a moment', 'please wait'] as $phrase) {
            expect($source)->not->toContain($phrase, $path);
        }
    }
});

it('carries no session identifier in any file it ships', function () {
    $files = [
        ...filesUnder('src', 'tests', 'docs', 'resources', '.github'),
        packageRoot().'/README.md',
        packageRoot().'/CHANGELOG.md',
        packageRoot().'/composer.json',
        packageRoot().'/module.json',
    ];

    // Assembled rather than written out, or this file would be the one hit that
    // fails the rule it enforces.
    $needles = [implode('.', ['claude', 'ai']), implode('-', ['Claude', 'Session'])];

    foreach ($files as $path) {
        $contents = (string) file_get_contents($path);

        foreach ($needles as $needle) {
            expect($contents)->not->toContain($needle, $path);
        }
    }
});
