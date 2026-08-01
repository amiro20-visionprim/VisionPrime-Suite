<?php

declare(strict_types=1);

return ['client_id' => env('GSC_CLIENT_ID'), 'client_secret' => env('GSC_CLIENT_SECRET'), 'redirect_uri' => env('GSC_REDIRECT_URI'), 'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly']];
