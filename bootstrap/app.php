<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistory::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bạn chưa đăng nhập hoặc phiên đăng nhập đã kết thúc.',
                ], 401);
            }

            return redirect()->route('session.expired');
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.',
                ], 419);
            }

            if ($request->is('login')) {
                return redirect()
                    ->route('login')
                    ->withInput($request->except(['password', '_token']))
                    ->with('status', 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
            }

            return redirect()
                ->route('session.expired')
                ->with('status', 'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.');
        });
    })->create();
