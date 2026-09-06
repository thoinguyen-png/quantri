<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <title>Cap nhat nhan su</title>
    @vite(['resources/css/quick-onboarding-public.css', 'resources/js/quick-onboarding-public.js'])
</head>

<body>
    <main class="page">
        <section class="card">
            <div class="content">
                <form class="form-card" id="bookingStaffForm" method="POST" action="{{ route('quick-onboarding.public.preview', $entry->token) }}" enctype="multipart/form-data" data-avatar-cropper data-onboarding-form data-ocr-url="{{ route('quick-onboarding.public.ocr', $entry->token, absolute: false) }}" data-ocr-enabled="{{ !empty($ocrEnabled) ? '1' : '0' }}">
                    @csrf
                    @php
                    $draftData = is_array($draftData ?? null) ? $draftData : [];
                    $ocrData = is_array($ocrData ?? null) ? $ocrData : [];
                    $fieldValue = function (string $field, mixed $fallback = '') use ($draftData, $ocrData) {
                    if (old($field) !== null) {
                    return old($field);
                    }

                    $draftValue = $draftData[$field] ?? null;

                    if ($draftValue !== null && trim((string) $draftValue) !== '') {
                    return $draftValue;
                    }

                    $ocrValue = $ocrData[$field] ?? null;

                    if ($ocrValue !== null && trim((string) $ocrValue) !== '') {
                    return $ocrValue;
                    }

                    return $fallback;
                    };
                    $genderValue = (string) $fieldValue('gender');
                    $hasStoredPassword = trim((string) ($draftData['password'] ?? '')) !== '';
                    $hasDraftAvatar = !empty($draftAvatarUrl);
                    $hasDraftCccd = !empty($draftCccdImageUrl);
                    @endphp

                    <div class="form-inner">
                        @if ($errors->any())
                        <p class="form-error">{{ $errors->first() }}</p>
                        @endif

                        <section class="avatar-section" aria-label="Anh dai dien" data-avatar-item>
                            <input
                                class="avatar-input"
                                id="avatarInput"
                                name="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                data-avatar-input />

                            <label class="avatar-dropzone" for="avatarInput" tabindex="0" aria-label="Them anh dai dien">
                                <span class="avatar-preview {{ $hasDraftAvatar ? 'has-image' : '' }}" id="avatarPreview" aria-hidden="true">
                                    <img
                                        id="avatarImage"
                                        src="{{ $draftAvatarUrl ?? '' }}"
                                        alt="Ảnh đại diện"
                                        data-avatar-preview />
                                    <span
                                        class="avatar-placeholder"
                                        data-avatar-fallback>
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4.8 6.2H14.7C15.6941 6.2 16.5 7.00589 16.5 8V17.8C16.5 18.7941 15.6941 19.6 14.7 19.6H4.8C3.80589 19.6 3 18.7941 3 17.8V8C3 7.00589 3.80589 6.2 4.8 6.2Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" />
                                            <path d="M6.1 16.4L9.2 12.9C9.55 12.5 10.18 12.47 10.58 12.82L12 14.05L13.15 12.85C13.53 12.46 14.16 12.45 14.55 12.84L16.1 14.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="7.8" cy="9.8" r="1.35" stroke="currentColor" stroke-width="1.9" />
                                            <path d="M18.2 5.2V11.2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                            <path d="M15.2 8.2H21.2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        </svg>
                                        <span class="avatar-placeholder-text">Anh dai dien</span>
                                    </span>
                                </span>
                            </label>
                        </section>

                        <section class="cccd-section cccd-scan-panel" aria-label="Quet CCCD">
                            <input class="cccd-input" id="cccdInput" name="cccd_image" type="file" accept="image/*" />

                            <div class="cccd-scan-head">
                                <h2>Quet CCCD</h2>
                            </div>

                            <div class="cccd-scan-actions">
                                <button class="cccd-scan-btn cccd-scan-btn--primary" type="button" data-qr-camera-open>
                                    <span class="cccd-scan-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 8V5.8C4 4.80589 4.80589 4 5.8 4H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M16 4H18.2C19.1941 4 20 4.80589 20 5.8V8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M20 16V18.2C20 19.1941 19.1941 20 18.2 20H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M8 20H5.8C4.80589 20 4 19.1941 4 18.2V16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            <path d="M8 8H10.8V10.8H8V8Z" fill="currentColor" />
                                            <path d="M13.2 8H16V10.8H13.2V8Z" fill="currentColor" />
                                            <path d="M8 13.2H10.8V16H8V13.2Z" fill="currentColor" />
                                            <path d="M13.2 13.2H16V16H13.2V13.2Z" fill="currentColor" />
                                        </svg>
                                    </span>
                                    <span>Quet QR truc tiep</span>
                                </button>

                                <button class="cccd-scan-btn cccd-scan-btn--outline" type="button" data-qr-image-pick>
                                    <span class="cccd-scan-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5.8 5H18.2C19.1941 5 20 5.80589 20 6.8V17.2C20 18.1941 19.1941 19 18.2 19H5.8C4.80589 19 4 18.1941 4 17.2V6.8C4 5.80589 4.80589 5 5.8 5Z" stroke="currentColor" stroke-width="1.9" />
                                            <path d="M6.8 16.5L10.1 12.8C10.45 12.41 11.06 12.38 11.45 12.73L13 14.1L14.05 13C14.43 12.6 15.07 12.59 15.47 12.98L18 15.45" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="8.4" cy="8.6" r="1.25" stroke="currentColor" stroke-width="1.9" />
                                        </svg>
                                    </span>
                                    <span>Chon anh CCCD co san</span>
                                </button>
                            </div>

                            <p class="cccd-scan-status {{ $hasDraftCccd ? 'is-success' : '' }}" id="cccdUploadText" data-qr-status aria-live="polite">
                                {{ $hasDraftCccd ? 'Da tai CCCD truoc do' : 'He thong se tu dong doc thong tin tu ma QR CCCD/VNeID.' }}
                            </p>

                            <div class="cccd-upload-box {{ $hasDraftCccd ? 'is-uploaded' : '' }}" id="cccdUploadBox" data-has-existing="{{ $hasDraftCccd ? '1' : '0' }}" hidden></div>
                        </section>

                        <div class="form-grid">
                            <label class="field">
                                <input class="input" name="name" type="text" placeholder="Ho va ten/Nghe danh" autocomplete="name" value="{{ $fieldValue('name', $entry->expected_name ?? '') }}" />
                            </label>

                            <label class="field">
                                <input class="input" name="citizen_id" type="text" placeholder="So CCCD" value="{{ $fieldValue('citizen_id') }}" />
                            </label>

                            <label class="field">
                                <input id="genderInput" name="gender" type="hidden" value="{{ $genderValue }}" />

                                <div class="gender-options" role="group" aria-label="Gioi tinh">
                                    <button class="gender-btn {{ $genderValue === 'Nam' ? 'is-selected' : '' }}" type="button" data-gender="Nam" aria-pressed="{{ $genderValue === 'Nam' ? 'true' : 'false' }}">
                                        Nam
                                    </button>

                                    <button class="gender-btn {{ $genderValue === 'Nu' ? 'is-selected' : '' }}" type="button" data-gender="Nu" aria-pressed="{{ $genderValue === 'Nu' ? 'true' : 'false' }}">
                                        Nu
                                    </button>
                                </div>
                            </label>

                            <label class="field">
                                <input class="input" name="date_of_birth" type="text" placeholder="Ngay sinh" value="{{ $fieldValue('date_of_birth') }}" onfocus="this.type='date'" onblur="if (!this.value) this.type='text'" />
                            </label>

                            <label class="field">
                                <input class="input" name="phone" type="tel" placeholder="So dien thoai" inputmode="tel" autocomplete="tel" value="{{ $fieldValue('phone') }}" required />
                            </label>

                            <label class="field">
                                <input class="input" name="email" type="email" placeholder="Email khong bat buoc" value="{{ $fieldValue('email') }}" />
                            </label>

                            <label class="field">
                                <input class="input" name="address" type="text" placeholder="Dia chi" value="{{ $fieldValue('address') }}" />
                            </label>

                            <label class="field">
                                <input class="input" name="place_of_origin" type="text" placeholder="Que quan / Noi sinh" value="{{ $fieldValue('place_of_origin') }}" />
                            </label>

                            <label class="field">
                                <input class="input" name="issue_date" type="text" placeholder="Ngay cap CCCD" value="{{ $fieldValue('issue_date') }}" onfocus="this.type='date'" onblur="if (!this.value) this.type='text'" />
                            </label>

                            <label class="field">
                                <input class="input" name="password" type="password" placeholder="{{ $hasStoredPassword ? 'Mat khau da duoc giu lai' : 'Mat khau' }}" minlength="6" @unless($hasStoredPassword) required @endunless />
                            </label>
                        </div>

                        <p class="form-hint" id="formError" aria-live="polite">Vui long nhap thong tin ben duoi theo CCCD/VNeID.</p>

                        <button class="submit-btn" id="submitBtn" type="submit">
                            <span id="submitBtnText">Cap nhat</span>
                        </button>
                    </div>
                    <div
                        class="avatar-crop-modal hidden"
                        data-avatar-modal
                        aria-hidden="true">
                        <button
                            class="avatar-crop-modal__backdrop"
                            type="button"
                            data-avatar-modal-close
                            aria-label="Đóng trình chỉnh ảnh"></button>

                        <div class="avatar-crop-modal__position">
                            <div
                                class="avatar-crop-modal__dialog"
                                role="dialog"
                                aria-modal="true"
                                aria-label="Căn chỉnh ảnh đại diện">
                                <header class="avatar-crop-modal__header">
                                    <div>
                                        <h2>Căn chỉnh ảnh đại diện</h2>
                                        <p>Kéo, phóng to hoặc xoay ảnh để phủ kín khung vuông.</p>
                                    </div>

                                    <button
                                        class="avatar-crop-modal__close"
                                        type="button"
                                        data-avatar-modal-close
                                        aria-label="Đóng">
                                        ×
                                    </button>
                                </header>

                                <div class="avatar-crop-modal__body">
                                    <p
                                        class="avatar-crop-error hidden"
                                        data-avatar-error></p>

                                    <div
                                        class="avatar-crop-stage"
                                        data-avatar-stage></div>

                                    <p class="avatar-crop-help">
                                        Hãy phóng to hoặc kéo ảnh phủ kín khung. Không để lộ nền caro ở các cạnh.
                                    </p>

                                    <div class="avatar-crop-tools">
                                        <button
                                            type="button"
                                            data-crop-action="zoom-out">
                                            Thu nhỏ
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="zoom-in">
                                            Phóng to
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="rotate-left">
                                            Xoay trái
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="rotate-right">
                                            Xoay phải
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="reset">
                                            Đặt lại
                                        </button>
                                        <button
                                            type="button"
                                            data-crop-action="reselect"
                                            aria-label="Chọn một ảnh khác">
                                            Chọn lại ảnh
                                        </button>
                                    </div>
                                </div>

                                <footer class="avatar-crop-modal__footer">
                                    <button
                                        class="avatar-crop-cancel"
                                        type="button"
                                        data-crop-action="cancel">
                                        Hủy
                                    </button>

                                    <button
                                        class="avatar-crop-confirm"
                                        type="button"
                                        data-crop-action="confirm">
                                        Xác nhận ảnh
                                    </button>
                                </footer>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <div class="cccd-camera-modal" data-qr-modal hidden aria-hidden="true">
        <div class="cccd-camera-shell" role="dialog" aria-modal="true" aria-label="Quet QR CCCD">
            <div class="cccd-camera-topbar">
                <button class="cccd-camera-icon-btn" type="button" data-qr-modal-close aria-label="Dong camera">
                    <span aria-hidden="true">x</span>
                </button>
                <p data-qr-camera-status>Dua ma QR tren CCCD vao giua khung hinh.</p>
                <button class="cccd-camera-icon-btn" type="button" data-qr-torch hidden aria-label="Bat tat den pin">
                    <span aria-hidden="true">Flash</span>
                </button>
            </div>

            <div class="cccd-camera-view">
                <video data-qr-video playsinline muted></video>
                <div class="cccd-camera-frame" aria-hidden="true"></div>
            </div>

            <div class="cccd-camera-actions">
                <button class="cccd-scan-btn cccd-scan-btn--outline" type="button" data-qr-image-pick>
                    <span>Chon anh co san</span>
                </button>
            </div>
        </div>
    </div>
</body>

</html>