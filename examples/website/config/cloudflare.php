<?php

declare(strict_types=1);

return [
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('CLOUDFLARE_TRUSTED_PROXIES', ''))))),
];
