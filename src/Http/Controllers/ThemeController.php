<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tardis\Theme\ThemePreference;

/**
 * Saves the signed-in user's theme choice (mode and the light/dark themes).
 * A guest has nowhere to store it, so the call is accepted and ignored.
 */
class ThemeController
{
    public function __invoke(Request $request, ThemePreference $preference): Response|JsonResponse
    {
        $choice = array_intersect_key($request->only(['mode', 'light', 'dark']), array_flip(['mode', 'light', 'dark']));

        if ($request->user() === null) {
            return response()->noContent();
        }

        if (! $preference->save($request->user()->getAuthIdentifier(), $choice)) {
            return response()->json(['message' => 'Invalid theme choice.'], 422);
        }

        return response()->noContent();
    }
}
