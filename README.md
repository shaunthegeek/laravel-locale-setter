# Laravel Locale Setter Middleware

Automatically scanning the lang directory for locales, detecting the HTTP Accept-Language header, and setting the Laravel locale — all are handled seamlessly by this package.

## Installation

```
composer require shaunthegeek/laravel-locale-setter
```

## Add Middleware

```
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(remove: [
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);
    $middleware->web(append: [
        ShaunTheGeek\LaravelLocaleSetter\Middleware\DetectLocale::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);
})
```
