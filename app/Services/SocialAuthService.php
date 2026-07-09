<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthService
{
    private const ALLOWED_PROVIDERS = ['google', 'github'];

    private const STATE_TTL_MINUTES = 10;

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderAllowed($provider);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver
            ->stateless()
            ->with(['state' => $this->generateState()])
            ->redirect();
    }

    public function handleCallback(string $provider, string $code, string $state, string $redirectUri): array
    {
        $this->ensureProviderAllowed($provider);
        $this->verifyState($state);

        request()->merge(['code' => $code]);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        $socialUser = $driver
            ->stateless()
            ->redirectUrl($redirectUri)
            ->user();

        if (! $socialUser->getEmail()) {
            throw ValidationException::withMessages([
                'email' => ["{$provider} did not provide an email address for this account."],
            ]);
        }

        $user = User::firstOrNew(['email' => $socialUser->getEmail()]);

        if (! $user->exists) {
            $user->name = $socialUser->getName() ?? $socialUser->getNickname() ?? 'User';
            $user->password = Hash::make(Str::random(40));
            $user->role = 'student';
            $user->save();

            $this->assignDefaultAvatar($user);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    private function ensureProviderAllowed(string $provider): void
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404, 'Unsupported provider.');
        }
    }

    private function generateState(): string
    {
        return Crypt::encryptString(json_encode([
            'nonce' => Str::random(40),
            'expires_at' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ]));
    }

    private function verifyState(string $state): void
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'state' => ['Invalid or tampered sign-in request.'],
            ]);
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'state' => ['Your sign-in session expired. Please try again.'],
            ]);
        }
    }

    private function assignDefaultAvatar(User $user): void
    {
        $defaultAvatar = Avatar::where('is_default', true)->inRandomOrder()->first();

        if ($defaultAvatar) {
            $user->avatar_id = $defaultAvatar->id;
            $user->save();
        }
    }
}