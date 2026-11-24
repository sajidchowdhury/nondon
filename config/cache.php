<?php

return [

    'store' => env('CACHE_STORE', 'database'),

    'prefix' => env('CACHE_PREFIX', env('APP_NAME', 'laravel').'_cache_'),

];
