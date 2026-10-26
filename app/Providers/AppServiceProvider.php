<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Resolve old container keys to the current binding and singleton.
        foreach (require app_path('Supports/legacy_service_namespaces.php') as $old => $current) {
            $this->app->alias($current, $old);
        }
    }



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
