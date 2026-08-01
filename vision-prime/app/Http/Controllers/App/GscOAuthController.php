<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Gsc\Services\GoogleOAuthState;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class GscOAuthController extends Controller
{
    public function redirect(CurrentOrganization $org, GoogleOAuthState $state): RedirectResponse
    {
        $query = http_build_query(['client_id' => config('gsc.client_id'), 'redirect_uri' => config('gsc.redirect_uri'), 'response_type' => 'code', 'scope' => implode(' ', config('gsc.scopes')), 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state->create($org->id())]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request, CurrentOrganization $org, GoogleOAuthState $state): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        abort_unless($state->validate($request->string('state')->toString(), $org->id()), 403);
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', ['code' => $request->string('code')->toString(), 'client_id' => config('gsc.client_id'), 'client_secret' => config('gsc.client_secret'), 'redirect_uri' => config('gsc.redirect_uri'), 'grant_type' => 'authorization_code'])->throw()->json();
        $payload = explode('.', (string) ($token['id_token'] ?? ''));
        $claims = isset($payload[1]) ? json_decode(base64_decode(strtr($payload[1], '-_', '+/')), true) : [];
        \DB::table('gsc_accounts')->updateOrInsert(['organization_id' => $org->id(), 'google_subject' => $claims['sub'] ?? hash('sha256', $token['access_token'])], ['email' => $claims['email'] ?? 'unknown', 'token_ciphertext' => Crypt::encryptString(json_encode($token)), 'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)), 'status' => 'connected', 'updated_at' => now(), 'created_at' => now()]);

        return redirect('/app/gsc')->with('status', 'اتصال سرچ کنسول انجام شد.');
    }
}
