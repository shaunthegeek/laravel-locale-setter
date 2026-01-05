<?php

namespace ShaunTheGeek\LaravelLocaleSetterTests;

class PublishTest extends TestCase
{
    public function test_publish_config()
    {
        $laravelPath = __DIR__.'/../vendor/orchestra/testbench-core/laravel';
        $this->artisan('vendor:publish', ['--provider' => 'ShaunTheGeek\LaravelLocaleSetter\LocaleServiceProvider'])->run();
        $this->assertFileExists($laravelPath.'/config/locale.php');
    }
}
