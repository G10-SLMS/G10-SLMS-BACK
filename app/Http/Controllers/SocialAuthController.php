<?php

namespace App\Http\Controllers;

use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialAuthService $socialAuthService)
    {
    }

    public function redirect(string $provider): RedirectResponse
    {
        return $this->socialAuthService->redirect($provider);
    }

    public function callback(string $provider, Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
        ]);

        try {
            $result = $this->socialAuthService->handleCallback(
                $provider,
                $request->input('code'),
                $request->input('state'),
                $request->input('redirect_uri'),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => ucfirst($provider) . ' sign-in failed.'], 422);
        }

        return response()->json($result);
    }
}