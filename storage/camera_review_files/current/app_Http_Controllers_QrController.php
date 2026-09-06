<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\QrToken;
use Illuminate\Support\Str;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrController extends Controller
{
    public function show()
    {
        return view('qr.show');
    }

    public function generate()
    {
        QrToken::where('expires_at', '<', now())->delete();

        $branchId = auth()->user()->branch_id ?? Branch::query()->value('id');

        if (!$branchId) {
            return response()->json([
                'message' => 'Chưa có chi nhánh để tạo mã QR.',
            ], 422);
        }

        $token = Str::random(40);

        QrToken::create([
            'token' => $token,
            'branch_id' => $branchId,
            'expires_at' => now()->addSeconds(12),
        ]);

        $url = route('attendance.scan', [
            'token' => $token
        ]);

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        $svg = $writer->writeString($url);

        return response()->json([
            'qr' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'generated_at' => now()->format('H:i'),
            'generated_at_full' => now()->format('d/m/Y H:i'),
            'next_refresh_at' => now()->addSeconds(10)->format('H:i:s'),
            'seconds' => 10,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
