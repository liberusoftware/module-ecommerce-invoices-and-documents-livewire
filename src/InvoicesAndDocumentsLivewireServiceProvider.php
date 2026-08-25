<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire;

use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Components\Documents;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Registers two components and their views. It binds no seam: the domain leaves
 * them unbound by design and a default here would answer for the host.
 */
final class InvoicesAndDocumentsLivewireServiceProvider extends ServiceProvider
{
    /**
     * The contract suite iterates this rather than a hand-written list, so a
     * component added without a property partition fails the build.
     *
     * @var array<string, class-string<Component>>
     */
    public const COMPONENTS = [
        'invoicing::documents' => Documents::class,
        'invoicing::document' => Document::class,
    ];

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'invoicing-livewire');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/invoicing-livewire'),
            ], 'views');
        }

        // resolveMissingComponent(), not addNamespace(). Livewire 4's finder
        // returns null for a namespaced name before it consults the registry,
        // and addNamespace() maps one namespace onto one class namespace.
        Livewire::resolveMissingComponent(
            static fn (string $name): ?string => self::COMPONENTS[$name] ?? null,
        );
    }
}
