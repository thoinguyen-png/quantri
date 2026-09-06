<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RatingQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class RatingQrController extends Controller
{
    public function show(User $user, RatingQrService $ratingQr): JsonResponse
    {
        Gate::authorize('viewRatingQr', $user);

        $token = $this->getOrAbort($user, $ratingQr);

        return response()->json($this->payload($user, $token, $ratingQr))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function svg(User $user, RatingQrService $ratingQr): Response
    {
        Gate::authorize('viewRatingQr', $user);

        $token = $this->getOrAbort($user, $ratingQr);

        return response($ratingQr->svgForToken($token), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function download(User $user, RatingQrService $ratingQr): Response
    {
        Gate::authorize('viewRatingQr', $user);

        $token = $this->getOrAbort($user, $ratingQr);

        return response($ratingQr->svgForToken($token), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="rating-qr.svg"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function enable(User $user, RatingQrService $ratingQr): JsonResponse
    {
        Gate::authorize('manageRatingQr', $user);

        $token = $ratingQr->enableForUser($user, auth()->user());

        return response()->json($this->payload($user->fresh(), $token, $ratingQr));
    }

    public function disable(User $user, RatingQrService $ratingQr): JsonResponse
    {
        Gate::authorize('manageRatingQr', $user);

        $ratingQr->disableForUser($user, auth()->user());

        return response()->json([
            'user_id' => $user->id,
            'rating_qr_enabled' => false,
        ]);
    }

    public function regenerate(User $user, RatingQrService $ratingQr): JsonResponse
    {
        Gate::authorize('manageRatingQr', $user);

        $token = $ratingQr->regenerateForUser($user, auth()->user());

        return response()->json($this->payload($user->fresh(), $token, $ratingQr));
    }

    private function payload(User $user, $token, RatingQrService $ratingQr): array
    {
        return [
            'user_id' => $user->id,
            'rating_qr_enabled' => (bool) $user->rating_qr_enabled,
            'token_id' => $token->id,
            'public_code' => $user->fresh()->public_rating_code,
            'public_url' => $ratingQr->publicUrl($token),
            'svg_url' => route('rating-qrs.svg', $user, absolute: false),
            'download_url' => route('rating-qrs.download', $user, absolute: false),
        ];
    }

    private function getOrAbort(User $user, RatingQrService $ratingQr)
    {
        try {
            return $ratingQr->getOrCreateForUser($user, auth()->user());
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
    }
}
