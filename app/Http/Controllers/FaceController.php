<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class FaceController extends Controller
{
    private const FACE_MATCH_THRESHOLD = 0.5;

    public function create()
    {
        return view('face.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'face_descriptor' => ['required', 'string'],
        ]);

        $descriptor = json_decode($data['face_descriptor'], true);

        if (
            !is_array($descriptor) ||
            count($descriptor) < 128 ||
            collect($descriptor)->contains(fn ($value) => !is_numeric($value))
        ) {
            return back()->withErrors([
                'face_descriptor' => 'Dữ liệu khuôn mặt không hợp lệ.',
            ]);
        }

        auth()->user()->update([
            'face_descriptor' => json_encode(array_values($descriptor)),
        ]);

        session()->forget([
            'face_verified_user_id',
            'face_verified_at',
            'face_verified_distance',
        ]);

        return redirect()->route('dashboard')->with('success', 'Đã đăng ký khuôn mặt.');
    }

    public function verifyPass(Request $request): JsonResponse
    {
        if (!auth()->user()->face_descriptor) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn chưa đăng ký khuôn mặt',
            ], 422);
        }

        $data = $request->validate([
            'distance' => ['required', 'numeric', 'min:0', 'max:' . self::FACE_MATCH_THRESHOLD],
        ]);

        session([
            'face_verified_user_id' => auth()->id(),
            'face_verified_at' => now()->toIso8601String(),
            'face_verified_distance' => (float) $data['distance'],
        ]);

        if (config('app.debug')) {
            $scannedAt = session('qr_scanned_at');

            Log::debug('Face verification session stored', [
                'session_id' => session()->getId(),
                'user_id' => auth()->id(),
                'has_qr_token' => session()->has('qr_token'),
                'has_qr_branch_id' => session()->has('qr_branch_id'),
                'has_qr_scanned_at' => session()->has('qr_scanned_at'),
                'has_face_verified_user_id' => session()->has('face_verified_user_id'),
                'has_face_verified_at' => session()->has('face_verified_at'),
                'qr_scanned_at' => $scannedAt instanceof \DateTimeInterface
                    ? $scannedAt->format(DATE_ATOM)
                    : $scannedAt,
                'request_at' => now()->toIso8601String(),
                'route' => $request->route()?->getName(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'threshold' => self::FACE_MATCH_THRESHOLD,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
