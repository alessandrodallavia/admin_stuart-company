<?php

return [
    // Shared source: deploy the landing alongside the admin, or set LANDING_PATH.
    'path' => env('LANDING_PATH', dirname(__DIR__, 2).'/public_html'),
];
