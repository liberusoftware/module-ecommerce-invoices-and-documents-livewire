<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Livewire\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\InvoicesAndDocuments\InvoicesAndDocumentsServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    use RefreshDatabase;

    /**
     * The domain package is a runtime `require`, and the parent's discovery only
     * reads a manifest provider out of `require-dev`. Naming it here is the
     * alternative to declaring the package in both sections, which `composer
     * validate` warns about during Install.
     *
     * Order matters: the parent supplies Livewire's provider after ours, and
     * ours calls Livewire::resolveMissingComponent() in boot().
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [InvoicesAndDocumentsServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');

        // Debug on in every case that renders a component: Livewire swallows a
        // TypeError inside a component method whole, so a signature mistake
        // reads as a session problem rather than as a bug in the method.
        $app['config']->set('app.debug', true);
    }
}
