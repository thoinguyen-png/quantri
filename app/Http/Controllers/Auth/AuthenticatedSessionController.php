<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\InternalDeviceMarkerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user?->role === 'cashier' && $this->isDesktopBrowserLogin($request)) {
            Auth::guard('web')->login($user, true);
            $request->session()->put('cashier_desktop_session', true);
        } else {
            $request->session()->forget('cashier_desktop_session');
        }

        $redirect = redirect()->intended(route('dashboard', absolute: false));

        return app(InternalDeviceMarkerService::class)->attachTo($redirect, $request, $user);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Bạn đã đăng xuất thành công.');
    }

    private function isDesktopBrowserLogin(Request $request): bool
    {
        if ($request->input('client_context') === 'pwa') {
            return false;
        }

        $userAgent = strtolower($request->userAgent() ?? '');

        if ($userAgent === '') {
            return true;
        }

        return !preg_match('/android|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile|zalo/i', $userAgent);
    }
}
