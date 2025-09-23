<?php

namespace App\Supports;

use Illuminate\Support\Facades\DB;

final class DbUtils {

    public static function flush() : void {
        foreach (array_keys(config('database.connections')) as $name) {
            try {
               DB::disconnect($name);
            } catch (\Throwable $t) { }
        }
    }
}
