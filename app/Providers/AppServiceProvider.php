<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register() {}



    public function boot()
    {
        // Keep existing media, notification and audit polymorphic identifiers.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(
            require app_path('Supports/legacy_model_namespaces.php')
        );

        \Illuminate\Support\Facades\Validator::resolver(
            fn ($translator, $data, $rules, $messages, $attributes) =>
                new \App\Validation\SecurityValidator($translator, $data, $rules, $messages, $attributes)
        );
    }
}
