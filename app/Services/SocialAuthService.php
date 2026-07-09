<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialAuthService
{
    public function loginWithGoogleCode(string $code, ?string $redirectUri = null): User
    {
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $redirectUri ?? config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if ($tokenResponse->failed()) {
            throw ValidationException::withMessages([
                'code' => ['Could not verify Google authorization code.'],
            ]);
        }

        $accessToken = $tokenResponse->json('access_token');

        $profileResponse = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if ($profileResponse->failed() || ! $profileResponse->json('email')) {
            throw ValidationException::withMessages([
                'code' => ['Could not fetch Google profile.'],
            ]);
        }

        $profile = $profileResponse->json();

        return $this->findOrCreateUser(
            email: $profile['email'],
            name: $profile['name'] ?? $profile['email'],
        );
    }

    public function loginWithGithubCode(string $code, ?string $redirectUri = null): User
    {
        $tokenResponse = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://github.com/login/oauth/access_token', [
                'code' => $code,
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'redirect_uri' => $redirectUri ?? config('services.github.redirect'),
            ]);

        if ($tokenResponse->failed() || ! $tokenResponse->json('access_token')) {
            throw ValidationException::withMessages([
                'code' => ['Could not verify GitHub authorization code.'],
            ]);
        }

        $accessToken = $tokenResponse->json('access_token');

        $profileResponse = Http::withToken($accessToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get('https://api.github.com/user');

        if ($profileResponse->failed()) {
            throw ValidationException::withMessages([
                'code' => ['Could not fetch GitHub profile.'],
            ]);
        }

        $profile = $profileResponse->json();
        $email = $profile['email'] ?? null;

        if (! $email) {
            $emailsResponse = Http::withToken($accessToken)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/user/emails');

            if ($emailsResponse->successful()) {
                $emails = collect($emailsResponse->json());
                $email = $emails->firstWhere('primary', true)['email']
                    ?? $emails->first()['email']
                    ?? null;
            }
        }

        if (! $email) {
            throw ValidationException::withMessages([
                'code' => ['Your GitHub account has no accessible email address. Please make an email public or use another sign-in method.'],
            ]);
        }

        return $this->findOrCreateUser(
            email: $email,
            name: $profile['name'] ?? $profile['login'],
        );
    }

    protected function findOrCreateUser(string $email, string $name): User
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $defaultAvatar = Avatar::where('is_default', true)->inRandomOrder()->first();

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(40),
            'role' => 'student',
            'avatar_id' => $defaultAvatar?->id,
        ]);
    }
}
