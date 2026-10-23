<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register() {}



    public function boot()
    {
        \Illuminate\Support\Facades\Validator::resolver(
            fn ($translator, $data, $rules, $messages, $attributes) =>
                new \App\Validation\SecurityValidator($translator, $data, $rules, $messages, $attributes)
        );
    }
}
