<?php

namespace ShaunTheGeek\LaravelLocaleSetterTests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ShaunTheGeek\LaravelLocaleSetter\LocaleServiceProvider;

class TestCase extends OrchestraTestCase
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getPackageProviders($app)
    {
        return [
            LocaleServiceProvider::class,
        ];
    }
}
