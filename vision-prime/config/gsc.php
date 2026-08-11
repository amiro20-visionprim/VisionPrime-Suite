<?php

declare(strict_types=1);

return ['client_id' => env('GSC_CLIENT_ID'), 'client_secret' => env('GSC_CLIENT_SECRET'), 'redirect_uri' => env('GSC_REDIRECT_URI'), 'scopes' => ['openid', 'email', 'https://www.googleapis.com/auth/webmasters.readonly'], 'http_proxy' => env('GSC_HTTP_PROXY'), 'relay_token' => env('GSC_RELAY_TOKEN')];
