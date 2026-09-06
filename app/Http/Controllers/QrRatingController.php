<?php

namespace App\Http\Controllers;

use App\Exceptions\RatingSubmissionException;
use App\Http\Requests\StoreQrRatingRequest;
use App\Models\CustomerRating;
use App\Models\RatingQrToken;
use App\Models\User;
use App\Services\GuestBrowserTokenService;
use App\Services\InternalDeviceMarkerService;
use App\Services\RatingEligibilityService;
use App\Services\RatingQrService;
use App\Services\RatingSubmissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class QrRatingController extends Controller
{
    private const GUEST_BROWSER_COOKIE = 'guest_rating_token';

    public function __construct(
        private readonly RatingQrService $ratingQr,
        private readonly RatingEligibilityService $eligibility,
        private readonly GuestBrowserTokenService $guestBrowser,
        private readonly RatingSubmissionService $submission,
        private readonly InternalDeviceMarkerService $internalDeviceMarker
    ) {
    }

    public function show(string $token, Request $request): Response|RedirectResponse
    {
        if ($this->isInternalDevice($request)) {
            $this->logQrDecision($request, 'legacy_token', 'blocked_internal_device');

            return $this->viewResponse($token, 'blocked_internal_device', route('qr-rating.store', ['token' => $token]));
        }

        [$guestToken, $cookie] = $this->guestToken($request);
        $context = $this->ratingContext($token, $request, $guestToken, 'legacy_token');

        if ($context['state'] !== 'blocked_duplicate_rating_today' && ($redirect = $this->legacyRedirect($token))) {
            return $cookie ? $redirect->withCookie($cookie) : $redirect;
        }

        $response = $this->viewResponse(
            $token,
            $context['state'],
            route('qr-rating.store', ['token' => $token]),
            $context['data'],
            $context['status']
        );

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    public function showByCode(string $publicRatingCode, Request $request): Response
    {
        $formAction = route('qr-rating.short.store', ['publicRatingCode' => $publicRatingCode]);

        if ($this->isInternalDevice($request)) {
            $this->logQrDecision($request, 'public_code', 'blocked_internal_device');

            return $this->viewResponse($publicRatingCode, 'blocked_internal_device', $formAction);
        }

        [$guestToken, $cookie] = $this->guestToken($request);
        $context = $this->ratingContextByCode($publicRatingCode, $request, $guestToken);
        $response = $this->viewResponse(
            $publicRatingCode,
            $context['state'],
            $formAction,
            $context['data'],
            $context['status']
        );

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    public function store(string $token, StoreQrRatingRequest $request): RedirectResponse|Response|JsonResponse
    {
        return $this->storeWithToken($token, $request, $this->shortUrlForLegacyToken($token) ?? route('qr-rating.show', ['token' => $token]), 'legacy_token');
    }

    public function storeByCode(string $publicRatingCode, StoreQrRatingRequest $request): RedirectResponse|Response|JsonResponse
    {
        $token = $this->rawTokenForPublicCode($publicRatingCode);
        $redirectUrl = route('qr-rating.short.show', ['publicRatingCode' => $publicRatingCode]);

        if (!$token) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'QR không còn hiệu lực.'], 404);
            }

            return $this->viewResponse(
                $publicRatingCode,
                'unavailable',
                route('qr-rating.short.store', ['publicRatingCode' => $publicRatingCode]),
                [
                    'title' => 'QR không còn hiệu lực',
                    'message' => 'Mã QR này không thể dùng để gửi đánh giá.',
                ],
                404
            );
        }

        return $this->storeWithToken($token, $request, $redirectUrl, 'public_code');
    }

    public function thankYou(): Response
    {
        $hour = (int) Carbon::now('Asia/Ho_Chi_Minh')->format('G');

        if ($hour >= 10 && $hour <= 17) {
            $message = ['CHÚC QUÝ KHÁCH MỘT NGÀY VUI VẺ!'];
        } elseif ($hour >= 18 || $hour <= 3) {
            $message = ['CHÚC QUÝ KHÁCH CÓ MỘT BUỔI TỐI TRỌN VẸN!'];
        } else {
            $message = ['CHÚC QUÝ KHÁCH VỀ NHÀ AN TOÀN!'];
        }

        return response()
            ->view('qr_ratings.thank-you', [
                'message' => $message,
                'confirmation' => 'Ý KIẾN CỦA QUÝ KHÁCH ĐÃ ĐƯỢC GHI NHẬN.',
            ])
            ->withHeaders($this->noStoreHeaders());
    }

    private function storeWithToken(string $token, StoreQrRatingRequest $request, string $redirectUrl, string $routeType): RedirectResponse|Response|JsonResponse
    {
        if ($this->isInternalDevice($request)) {
            $this->logQrDecision($request, 'post', 'blocked_internal_device');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Vui lòng dùng thiết bị của khách.',
                ], 403);
            }

            return $this->viewResponse($token, 'blocked_internal_device', $redirectUrl, status: 403);
        }

        [$guestToken, $cookie] = $this->guestToken($request);

        try {
            $this->submission->submit(
                qrToken: $token,
                guestBrowserToken: $guestToken,
                rating: $request->validated('rating'),
                comment: $request->validated('comment'),
                now: Carbon::now(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                routeType: $routeType
            );
        } catch (RatingSubmissionException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $this->publicErrorMessage($exception),
                ], 422);
            }

            $redirect = redirect()
                ->to($redirectUrl)
                ->with('qr_error_modal', [
                    'title' => 'CHƯA GỬI ĐƯỢC',
                    'message' => $this->publicErrorMessage($exception),
                ])
                ->withInput();

            return $this->withOptionalCookie($redirect, $cookie);
        }

        $redirect = redirect()
            ->route('qr-rating.thank-you')
            ->with('rating_success', true);

        if ($request->expectsJson()) {
            $response = response()->json([
                'message' => 'Ý kiến của Quý khách đã được ghi nhận.',
                'thank_you_url' => route('qr-rating.thank-you'),
            ]);

            return $cookie ? $response->withCookie($cookie) : $response;
        }

        return $this->withOptionalCookie($redirect, $cookie);
    }

    private function ratingContext(string $token, Request $request, string $guestToken, string $routeType): array
    {
        $qrToken = RatingQrToken::with('user.branch')
            ->where('token_hash', $this->ratingQr->hashToken($token))
            ->first();

        if (!$qrToken) {
            return [
                'state' => 'unavailable',
                'status' => 404,
                'data' => [
                    'title' => 'QR không còn hiệu lực',
                    'message' => 'Mã QR này không thể dùng để gửi đánh giá.',
                ],
            ];
        }

        try {
            $context = $this->eligibility->resolve($qrToken, Carbon::now());
        } catch (RatingSubmissionException $exception) {
            Log::warning('QR rating eligibility failed.', [
                'reason' => $exception->reason,
                'qr_token_id' => $qrToken->id,
                'employee_id' => $qrToken->user_id,
                'now' => Carbon::now()->toDateTimeString(),
            ]);
            $this->logQrDecision($request, $routeType, 'blocked_eligibility', [
                'employee_id' => $qrToken->user_id,
                'decision_reason' => $exception->reason,
            ]);

            return [
                'state' => 'unavailable',
                'status' => 200,
                'data' => [
                    'title' => $exception->reason === 'qr_inactive'
                        ? 'QR không còn hiệu lực'
                        : 'Chưa thể đánh giá lúc này',
                    'message' => $exception->reason === 'qr_inactive'
                        ? 'Mã QR này đã bị khóa hoặc đã được cấp lại.'
                        : $this->eligibilityErrorMessage($exception),
                ],
            ];
        }

        $employee = $context['employee'];
        $guestBrowserHash = $this->guestBrowser->hashToken($guestToken);

        if ($this->hasDuplicateRatingToday($context, $guestBrowserHash)) {
            $this->logQrDecision($request, $routeType, 'blocked_duplicate_rating_today', $this->safeContext($context, $guestBrowserHash));

            return [
                'state' => 'blocked_duplicate_rating_today',
                'status' => 200,
                'data' => [
                    'title' => 'ĐÃ GỬI ĐÁNH GIÁ',
                    'message' => 'Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi. Cảm ơn bạn đã phản hồi!',
                ],
            ];
        }

        $this->logQrDecision($request, $routeType, 'allowed', $this->safeContext($context, $guestBrowserHash));
        $citizenLast4 = $this->employeeCitizenLast4($employee);

        return [
            'state' => 'allowed',
            'status' => 200,
            'data' => [
                'employee_id' => $employee->id,
                'employee_code' => $citizenLast4,
                'citizen_last4' => $citizenLast4,
                'employee_name' => $employee->name,
                'employee_initial' => mb_strtoupper(mb_substr(trim($employee->name), 0, 1)),
                'employee_avatar_url' => $this->employeeAvatarUrl($employee),
                'branch_name' => $employee->branch?->name,
            ],
        ];
    }

    private function ratingContextByCode(string $publicRatingCode, Request $request, string $guestToken): array
    {
        $user = User::query()
            ->where('public_rating_code', $publicRatingCode)
            ->first();

        if (!$user) {
            return [
                'state' => 'unavailable',
                'status' => 404,
                'data' => [
                    'title' => 'QR không còn hiệu lực',
                    'message' => 'Mã QR này không thể dùng để gửi đánh giá.',
                ],
            ];
        }

        try {
            $qrToken = $this->ratingQr->getOrCreateForUser($user);
            $context = $this->eligibility->resolve($qrToken, Carbon::now());
        } catch (\RuntimeException $exception) {
            return [
                'state' => 'unavailable',
                'status' => 200,
                'data' => [
                    'title' => 'QR không còn hiệu lực',
                    'message' => 'Mã QR này đã bị khóa hoặc chưa được bật.',
                ],
            ];
        } catch (RatingSubmissionException $exception) {
            Log::warning('QR rating eligibility failed.', [
                'reason' => $exception->reason,
                'employee_id' => $user->id,
                'public_rating_code' => $publicRatingCode,
                'now' => Carbon::now()->toDateTimeString(),
            ]);
            $this->logQrDecision($request, 'public_code', 'blocked_eligibility', [
                'employee_id' => $user->id,
                'decision_reason' => $exception->reason,
            ]);

            return [
                'state' => 'unavailable',
                'status' => 200,
                'data' => [
                    'title' => $exception->reason === 'qr_inactive'
                        ? 'QR không còn hiệu lực'
                        : 'Chưa thể đánh giá lúc này',
                    'message' => $exception->reason === 'qr_inactive'
                        ? 'Mã QR này đã bị khóa hoặc đã được cấp lại.'
                        : $this->eligibilityErrorMessage($exception),
                ],
            ];
        }

        $employee = $context['employee'];
        $guestBrowserHash = $this->guestBrowser->hashToken($guestToken);

        if ($this->hasDuplicateRatingToday($context, $guestBrowserHash)) {
            $this->logQrDecision($request, 'public_code', 'blocked_duplicate_rating_today', $this->safeContext($context, $guestBrowserHash));

            return [
                'state' => 'blocked_duplicate_rating_today',
                'status' => 200,
                'data' => [
                    'title' => 'ĐÃ GỬI ĐÁNH GIÁ',
                    'message' => 'Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi. Cảm ơn bạn đã phản hồi!',
                ],
            ];
        }

        $this->logQrDecision($request, 'public_code', 'allowed', $this->safeContext($context, $guestBrowserHash));
        $citizenLast4 = $this->employeeCitizenLast4($employee);

        return [
            'state' => 'allowed',
            'status' => 200,
            'data' => [
                'employee_id' => $employee->id,
                'employee_code' => $citizenLast4,
                'citizen_last4' => $citizenLast4,
                'employee_name' => $employee->name,
                'employee_initial' => mb_strtoupper(mb_substr(trim($employee->name), 0, 1)),
                'employee_avatar_url' => $this->employeeAvatarUrl($employee),
                'branch_name' => $employee->branch?->name,
            ],
        ];
    }

    private function viewResponse(string $token, string $state, string $formAction, array $data = [], int $status = 200): Response
    {
        return response()
            ->view('qr_ratings.show', [
                'token' => $token,
                'formAction' => $formAction,
                'state' => $state,
                'data' => $data,
                'ratingLabels' => [
                    'bad' => 'Te',
                    'average' => 'Trung binh',
                    'good' => 'Tot',
                ],
                'thankYouUrl' => route('qr-rating.thank-you'),
                'errorModal' => session('qr_error_modal'),
            ], $status)
            ->withHeaders($this->noStoreHeaders());
    }

    private function employeeAvatarUrl(User $employee): ?string
    {
        $path = trim((string) $employee->face_image_path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:image')) {
            return $path;
        }

        $relativePath = ltrim($path, '/');
        $localCandidates = [];

        if (str_starts_with($relativePath, 'storage/')) {
            $localCandidates[] = public_path($relativePath);
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        $localCandidates[] = storage_path('app/public/' . $relativePath);
        $localCandidates[] = public_path($relativePath);

        foreach ($localCandidates as $localPath) {
            if (is_file($localPath)) {
                $mime = mime_content_type($localPath) ?: 'image/jpeg';
                $contents = file_get_contents($localPath);

                if ($contents !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                }
            }
        }

        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return is_file(storage_path('app/public/' . ltrim($path, '/')))
            ? asset('storage/' . ltrim($path, '/'))
            : null;
    }

    private function employeeCitizenLast4(User $employee): string
    {
        $digits = preg_replace('/\D+/', '', (string) $employee->citizen_id);

        if (strlen($digits) >= 4) {
            return substr($digits, -4);
        }

        return $employee->employee_code ?: '----';
    }

    private function isInternalDevice(Request $request): bool
    {
        return $this->internalDeviceMarker->hasMarker($request)
            || $this->internalDeviceMarker->isInternalUser(auth()->user());
    }

    private function guestToken(Request $request): array
    {
        $token = $request->cookie(self::GUEST_BROWSER_COOKIE);

        if (is_string($token) && strlen($token) >= 32) {
            return [$token, null];
        }

        $token = $this->guestBrowser->makeToken();

        return [
            $token,
            cookie(
                name: self::GUEST_BROWSER_COOKIE,
                value: $token,
                minutes: 60 * 24 * 365,
                path: '/',
                domain: null,
                secure: $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https',
                httpOnly: true,
                raw: false,
                sameSite: 'Lax'
            ),
        ];
    }

    private function publicErrorMessage(RatingSubmissionException $exception): string
    {
        return match ($exception->reason) {
            'duplicate_rating', 'duplicate_rating_today' => 'Thiết bị này đã đánh giá nhân viên này hôm nay.',
            'browser_employee_limit_reached' => 'Trình duyệt này đã đạt giới hạn đánh giá trong ngày.',
            'qr_invalid', 'qr_inactive' => 'QR không còn hiệu lực.',
            'attendance_not_active', 'segment_not_active', 'shift_not_schedulable' => 'Nhân sự hiện chưa trong ca làm nên chưa thể nhận đánh giá.',
            'invalid_rating' => 'Vui lòng chọn lại mức đánh giá.',
            default => 'Nhân sự hiện chưa sẵn sàng nhận đánh giá.',
        };
    }

    private function eligibilityErrorMessage(RatingSubmissionException $exception): string
    {
        return match ($exception->reason) {
            'attendance_not_active', 'segment_not_active', 'shift_not_schedulable' => 'Nhân sự hiện chưa trong ca làm nên chưa thể nhận đánh giá.',
            default => 'Nhân sự hiện chưa sẵn sàng nhận đánh giá.',
        };
    }

    private function withOptionalCookie(RedirectResponse $response, mixed $cookie): RedirectResponse
    {
        return $cookie ? $response->withCookie($cookie) : $response;
    }

    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    private function legacyRedirect(string $token): ?RedirectResponse
    {
        $url = $this->shortUrlForLegacyToken($token);

        return $url ? redirect()->to($url) : null;
    }

    private function shortUrlForLegacyToken(string $token): ?string
    {
        $qrToken = RatingQrToken::with('user')
            ->where('token_hash', $this->ratingQr->hashToken($token))
            ->first();

        if (!$qrToken?->user) {
            return null;
        }

        $code = $this->ratingQr->ensurePublicCode($qrToken->user);

        return route('qr-rating.short.show', ['publicRatingCode' => $code]);
    }

    private function rawTokenForPublicCode(string $publicRatingCode): ?string
    {
        $user = User::query()
            ->where('public_rating_code', $publicRatingCode)
            ->first();

        if (!$user) {
            return null;
        }

        try {
            $token = $this->ratingQr->getOrCreateForUser($user);
        } catch (\RuntimeException $exception) {
            return null;
        }

        return $this->ratingQr->rawToken($token);
    }

    private function hasDuplicateRatingToday(array $context, string $guestBrowserHash): bool
    {
        return CustomerRating::query()
            ->where('employee_id', $context['employee']->id)
            ->whereDate('business_date', $context['business_date'])
            ->where('guest_browser_hash', $guestBrowserHash)
            ->exists();
    }

    private function safeContext(array $context, string $guestBrowserHash): array
    {
        return [
            'employee_id' => $context['employee']->id,
            'business_date' => (string) $context['business_date'],
            'branch_id' => $context['branch_id'],
            'guest_browser_hash_prefix' => substr($guestBrowserHash, 0, 10),
        ];
    }

    private function logQrDecision(Request $request, string $routeType, string $decision, array $context = []): void
    {
        Log::info('QR rating decision.', $context + [
            'has_internal_marker' => $this->internalDeviceMarker->hasMarker($request),
            'has_guest_browser_token' => is_string($request->cookie(self::GUEST_BROWSER_COOKIE)),
            'ip_hash_prefix' => substr((string) $this->guestBrowser->hashNullable($request->ip()), 0, 10),
            'user_agent_hash_prefix' => substr((string) $this->guestBrowser->hashNullable($request->userAgent()), 0, 10),
            'decision' => $decision,
            'route_type' => $routeType,
        ]);
    }

}
