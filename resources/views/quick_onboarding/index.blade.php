<x-app-layout>
    <style>
        .quick-shell * {
            box-sizing: border-box;
        }

        .quick-shell {
            min-height: calc(100vh - 72px);
            background:
                radial-gradient(circle at top left, rgba(143, 0, 255, 0.09), transparent 34%),
                linear-gradient(180deg, #f6f7fb 0%, #eef1f6 100%);
            color: #111827;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .quick-shell button,
        .quick-shell input,
        .quick-shell select {
            font: inherit;
        }

        .quick-shell button {
            -webkit-tap-highlight-color: transparent;
        }

        .demo-shell {
            display: grid;
            place-items: start center;
            min-height: calc(100vh - 72px);
            padding: 22px;
        }

        .demo-phone {
            --role-primary: #8f00ff;
            width: min(430px, 100%);
            min-height: calc(100vh - 116px);
            padding: 14px;
            border: 1px solid rgba(148, 163, 184, 0.36);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.62);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(18px);
        }

        .demo-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 5px 4px 14px;
        }

        .demo-title {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .demo-title span {
            color: var(--role-primary);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .22em;
            text-transform: uppercase;
        }

        .demo-title strong {
            overflow: hidden;
            color: #0f172a;
            font-size: 23px;
            font-weight: 950;
            line-height: 1.1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .demo-counter {
            display: grid;
            place-items: center;
            min-width: 42px;
            height: 42px;
            padding: 0 13px;
            border: 0;
            border-radius: 999px;
            background: #ffffff;
            color: #111827;
            font-size: 13px;
            font-weight: 950;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
        }

        .portal-view--karaoke .kara-quick {
            --quick-primary: var(--role-primary, #8f00ff);
            --quick-text: #111827;
            --quick-muted: #6b7280;
            --quick-card: #ffffff;
            --quick-border: color-mix(in srgb, var(--quick-primary) 40%, #efd7ff);
            --quick-soft: color-mix(in srgb, var(--quick-primary) 10%, #ffffff);
            --quick-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
            display: grid;
            gap: 12px;
            width: 100%;
        }

        .portal-view--karaoke .kara-quick-tabs {
            display: grid;
            grid-template-columns: 1fr;
            min-height: 50px;
            border-radius: 8px;
            background: rgba(17, 24, 39, 0.04);
        }

        .portal-view--karaoke .kara-quick-tabs__item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            padding: 14px 10px;
            border: 0;
            border-radius: 8px;
            background: #ffffff;
            color: var(--quick-text);
            cursor: default;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
            box-shadow: 0 5px 14px rgba(15, 23, 42, 0.14);
        }

        .portal-view--karaoke .kara-quick-panel {
            display: grid;
            gap: 12px;
            padding: 14px;
            border-radius: 12px;
            background: var(--quick-card);
            box-shadow: var(--quick-shadow);
        }

        .portal-view--karaoke .kara-quick-panel__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .portal-view--karaoke .kara-quick-panel__header div {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .portal-view--karaoke .kara-quick-panel__header h3 {
            margin: 0;
            color: var(--quick-text);
            font-size: 16px;
            font-weight: 900;
            line-height: 1.2;
        }

        .portal-view--karaoke .kara-quick-panel__header p {
            margin: 0;
            color: var(--quick-muted);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }

        .portal-view--karaoke .kara-quick-panel__badge {
            flex: 0 0 auto;
            min-height: 30px;
            padding: 8px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--quick-primary) 10%, #ffffff);
            color: var(--quick-primary);
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
            text-transform: uppercase;
        }

        .portal-view--karaoke .kara-quick-create-form {
            display: grid;
            gap: 10px;
            padding: 12px;
            border: 1.5px dashed var(--quick-primary);
            border-radius: 10px;
            background: var(--quick-soft);
        }

        .portal-view--karaoke .kara-quick-create-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 96px;
            gap: 8px;
        }

        .portal-view--karaoke .kara-quick-field {
            display: grid;
            gap: 6px;
        }

        .portal-view--karaoke .kara-quick-field span {
            color: var(--quick-text);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.2;
        }

        .portal-view--karaoke .kara-quick-field input,
        .portal-view--karaoke .kara-quick-field select {
            width: 100%;
            min-width: 0;
            min-height: 42px;
            padding: 0 12px;
            border: 1px solid var(--quick-border);
            border-radius: 8px;
            background: #ffffff;
            color: var(--quick-text);
            font-size: 14px;
            font-weight: 750;
            outline: none;
        }

        .portal-view--karaoke .kara-quick-field input:focus,
        .portal-view--karaoke .kara-quick-field select:focus {
            border-color: var(--quick-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--quick-primary) 14%, transparent);
        }

        .portal-view--karaoke .kara-quick-create-form__actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .portal-view--karaoke .kara-quick-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 42px;
            padding: 10px 12px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 850;
            line-height: 1;
            text-decoration: none;
        }

        .portal-view--karaoke .kara-quick-btn svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.4;
        }

        .portal-view--karaoke .kara-quick-btn--primary {
            background: var(--quick-primary);
            color: #ffffff;
            box-shadow: 0 8px 18px color-mix(in srgb, var(--quick-primary) 25%, transparent);
        }

        .portal-view--karaoke .kara-quick-btn:active {
            transform: translateY(1px);
        }

        .portal-view--karaoke .kara-quick-list-section {
            display: grid;
            gap: 10px;
        }

        .portal-view--karaoke .kara-quick-list-section__title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .portal-view--karaoke .kara-quick-list-section__title h3 {
            margin: 0;
            color: var(--quick-text);
            font-size: 16px;
            font-weight: 900;
            line-height: 1.2;
        }

        .portal-view--karaoke .kara-quick-list-section__title span {
            color: var(--quick-muted);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }

        .portal-view--karaoke .kara-quick-list {
            display: grid;
            gap: 10px;
        }

        .portal-view--karaoke .kara-quick-empty {
            display: grid;
            place-items: center;
            min-height: 150px;
            padding: 18px;
            border: 1.5px dashed color-mix(in srgb, var(--quick-primary) 26%, #e5e7eb);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.72);
            color: var(--quick-muted);
            text-align: center;
        }

        .portal-view--karaoke .kara-quick-empty strong {
            color: var(--quick-text);
            font-size: 15px;
            font-weight: 900;
        }

        .portal-view--karaoke .kara-quick-empty span {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 650;
        }

        .portal-view--karaoke .kara-quick-card {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr) 34px;
            align-items: stretch;
            gap: 10px;
            min-height: 118px;
            padding: 10px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            background: var(--quick-card);
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04), var(--quick-shadow);
            transition: background 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
        }

        .portal-view--karaoke .kara-quick-card.quick-entry--sent {
            border-color: rgba(245, 158, 11, .42);
            background: linear-gradient(135deg, #fff7ed, #ffffff 55%, #fffbeb);
            box-shadow: 0 12px 26px rgba(245, 158, 11, .16);
        }

        .portal-view--karaoke .kara-quick-card.quick-entry--completed {
            border-color: rgba(34, 197, 94, .42);
            background: linear-gradient(135deg, #ecfdf5, #ffffff 55%, #f0fdf4);
            box-shadow: 0 12px 26px rgba(34, 197, 94, .16);
        }

        .portal-view--karaoke .kara-quick-card__media {
            display: grid;
            justify-items: center;
            align-content: center;
            gap: 6px;
            min-width: 0;
        }

        .portal-view--karaoke .kara-quick-avatar {
            position: relative;
            display: grid;
            place-items: center;
            width: 68px;
            height: 76px;
            overflow: hidden;
            border: 1.5px dashed color-mix(in srgb, var(--quick-primary) 40%, #d8b4fe);
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff, #faf5ff);
            color: var(--quick-primary);
            cursor: pointer;
            box-shadow: 0 8px 16px rgba(15, 23, 42, .08);
        }

        .portal-view--karaoke .kara-quick-avatar img {
            z-index: 2;
            inset: 0;
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .portal-view--karaoke .kara-quick-avatar img[hidden] {
            display: none;
        }

        .portal-view--karaoke .kara-quick-avatar__placeholder {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
            color: var(--quick-primary);
        }

        .portal-view--karaoke .kara-quick-avatar:not(.is-avatar-missing) img:not([hidden])+.kara-quick-avatar__placeholder {
            display: none;
        }

        .portal-view--karaoke .kara-quick-avatar.is-avatar-missing .kara-quick-avatar__placeholder {
            display: grid;
        }

        .portal-view--karaoke .kara-quick-avatar svg {
            width: 25px;
            height: 25px;
            stroke-width: 2.4;
        }

        .portal-view--karaoke .kara-quick-avatar__input {
            position: absolute;
            z-index: 3;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .portal-view--karaoke .kara-quick-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            min-height: 22px;
            padding: 4px 8px;
            border-radius: 999px;
            background: var(--quick-primary);
            color: #ffffff;
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .06em;
            box-shadow: 0 8px 16px color-mix(in srgb, var(--quick-primary) 18%, transparent);
        }

        .portal-view--karaoke .kara-quick-card__body {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .portal-view--karaoke .kara-quick-name-input {
            width: 100%;
            min-width: 0;
            height: 38px;
            padding: 0 10px;
            border: 1px solid color-mix(in srgb, var(--quick-primary) 24%, #e5e7eb);
            border-radius: 8px;
            background: #ffffff;
            color: var(--quick-text);
            font-size: 14px;
            font-weight: 900;
            outline: none;
        }

        .portal-view--karaoke .kara-quick-name-input:focus {
            border-color: var(--quick-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--quick-primary) 12%, transparent);
        }

        .portal-view--karaoke .kara-quick-completed-name {
            display: flex;
            align-items: center;
            min-height: 38px;
            overflow: hidden;
            color: var(--quick-text);
            font-size: 14px;
            font-weight: 950;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .portal-view--karaoke .kara-quick-card__grid {
            display: grid;
            grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
            gap: 7px;
        }

        .portal-view--karaoke .kara-quick-mini-field {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .portal-view--karaoke .kara-quick-mini-field span {
            color: var(--quick-muted);
            font-size: 10px;
            font-weight: 900;
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .portal-view--karaoke .kara-quick-branch-pill {
            display: inline-flex;
            align-items: center;
            min-width: 0;
            height: 34px;
            padding: 0 9px;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, .28);
            border-radius: 8px;
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .portal-view--karaoke .kara-quick-mini-field select {
            width: 100%;
            min-width: 0;
            height: 34px;
            padding: 0 8px;
            border: 1px solid color-mix(in srgb, var(--quick-primary) 22%, #e5e7eb);
            border-radius: 8px;
            background: #f8fafc;
            color: var(--quick-text);
            font-size: 12px;
            font-weight: 750;
            outline: none;
        }

        .portal-view--karaoke .kara-quick-mini-field select:focus {
            border-color: var(--quick-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--quick-primary) 12%, transparent);
            background: #ffffff;
        }

        .portal-view--karaoke .kara-quick-card__actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            align-self: stretch;
            min-height: 98px;
            padding: 2px 0;
            border-radius: 999px;
        }

        .portal-view--karaoke .kara-quick-icon-btn {
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.96);
            color: #111827;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.14);
        }

        .portal-view--karaoke .kara-quick-icon-btn svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.4;
        }

        .portal-view--karaoke .kara-quick-icon-btn--share {
            color: var(--quick-primary);
        }

        .portal-view--karaoke .kara-quick-icon-btn--danger {
            color: #ef4444;
        }

        .portal-view--karaoke .kara-quick-icon-btn:disabled {
            cursor: not-allowed;
            color: #cbd5e1;
            opacity: .7;
        }

        body.kara-quick-modal-open {
            overflow: hidden;
        }

        .kara-quick-modal[hidden] {
            display: none;
        }

        .kara-quick-modal {
            --quick-primary: #8f00ff;
            position: fixed;
            inset: 0;
            z-index: 2200;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            width: 100vw;
            min-height: 100vh;
            min-height: 100dvh;
            padding: 0;
        }

        .kara-quick-modal__backdrop {
            position: absolute;
            inset: 0;
            opacity: 0;
            background: rgba(17, 24, 39, 0.34);
            transition: opacity 240ms ease;
        }

        .kara-quick-modal__sheet {
            position: relative;
            display: grid;
            width: min(430px, 100vw);
            max-height: calc(100vh - 12px);
            max-height: calc(100dvh - 12px);
            margin: 0;
            overflow: hidden auto;
            border-radius: 28px 28px 0 0;
            background:
                radial-gradient(circle at 10% 0%, rgba(245, 158, 11, 0.16), transparent 34%),
                radial-gradient(circle at 100% 4%, rgba(34, 197, 94, 0.16), transparent 34%),
                #ffffff;
            color: #111827;
            box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.22);
            opacity: 0;
            transform: translate3d(0, 100%, 0);
            transition: transform 240ms cubic-bezier(0.22, 1, 0.36, 1), opacity 240ms ease;
        }

        .kara-quick-modal.is-open .kara-quick-modal__backdrop {
            opacity: 1;
        }

        .kara-quick-modal.is-open .kara-quick-modal__sheet {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }

        .kara-quick-modal.is-closing {
            pointer-events: none;
        }

        .kara-quick-modal__grabber {
            justify-self: center;
            width: 42px;
            height: 5px;
            margin-top: 10px;
            border-radius: 999px;
            background: #111827;
            opacity: .86;
        }

        .kara-quick-modal__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 16px 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.22);
        }

        .kara-quick-modal__heading {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .kara-quick-modal__heading h3 {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 950;
            line-height: 1.15;
        }

        .kara-quick-modal__heading p {
            margin: 0;
            color: #6b7280;
            font-size: 12px;
            font-weight: 650;
            line-height: 1.3;
        }

        .kara-quick-modal__close {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: rgba(17, 24, 39, 0.06);
            color: #111827;
            cursor: pointer;
        }

        .kara-quick-modal__close svg {
            width: 20px;
            height: 20px;
            stroke-width: 2.4;
        }

        .kara-quick-modal__body {
            display: grid;
            gap: 12px;
            padding: 14px;
        }

        .kara-quick-share-card {
            display: grid;
            gap: 12px;
            padding: 12px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.1);
            backdrop-filter: blur(14px);
        }

        .kara-quick-share-hero {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 16px;
            background: linear-gradient(135deg, #fff7ed, #ffffff 50%, #f0fdf4);
            border: 1px solid rgba(245, 158, 11, 0.16);
        }

        .kara-quick-share-hero__avatar {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            overflow: hidden;
            border-radius: 16px;
            background: linear-gradient(135deg, #f59e0b, #22c55e);
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.2);
        }

        .kara-quick-share-hero__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kara-quick-share-hero__avatar svg {
            width: 22px;
            height: 22px;
            stroke-width: 2.4;
        }

        .kara-quick-share-hero__content {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .kara-quick-share-hero__content span {
            color: #64748b;
            font-size: 11px;
            font-weight: 850;
            line-height: 1;
        }

        .kara-quick-share-hero__content strong {
            overflow: hidden;
            color: #111827;
            font-size: 16px;
            font-weight: 950;
            line-height: 1.15;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .kara-quick-share-hero__content small {
            overflow: hidden;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .kara-quick-share-grid {
            display: grid;
            grid-template-columns: 148px minmax(0, 1fr);
            gap: 10px;
            align-items: stretch;
        }

        .kara-quick-share-qrbox {
            display: grid;
            place-items: center;
            gap: 8px;
            padding: 10px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.28);
            box-shadow: inset 0 0 0 5px #f8fafc;
        }

        .kara-quick-share-qrbox img {
            width: 124px;
            height: 124px;
            border: 0;
            border-radius: 14px;
            background: #ffffff;
            object-fit: contain;
        }

        .kara-quick-share-qrbox small {
            display: inline-flex;
            justify-content: center;
            min-width: 52px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #8f00ff;
            color: #fff;
            font-size: 11px;
            font-weight: 950;
            letter-spacing: .06em;
        }

        .kara-quick-share-tools {
            display: grid;
            gap: 8px;
        }

        .kara-quick-link-box {
            display: grid;
            gap: 6px;
            padding: 9px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 14px;
            background: #f8fafc;
        }

        .kara-quick-link-box span {
            color: #64748b;
            font-size: 10.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .kara-quick-link-box input {
            width: 100%;
            min-width: 0;
            border: 0;
            background: transparent;
            color: #111827;
            font-size: 12px;
            font-weight: 750;
            outline: none;
        }

        .kara-quick-share-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .kara-quick-mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 38px;
            padding: 9px 10px;
            border: 1px solid rgba(148, 163, 184, 0.32);
            border-radius: 12px;
            background: #ffffff;
            color: #334155;
            cursor: pointer;
            font-size: 12px;
            font-weight: 850;
            line-height: 1;
        }

        .kara-quick-mini-btn svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.4;
        }

        .qo-share-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .qo-share-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-width: 0;
            min-height: 52px;
            padding: 10px 12px;
            border: 1px solid rgba(143, 0, 255, 0.22);
            border-radius: 16px;
            background: #ffffff;
            color: #334155;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 850;
            line-height: 1;
            white-space: nowrap;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        .qo-share-action:active {
            transform: scale(0.985);
        }

        .qo-share-action__icon {
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            flex: 0 0 auto;
        }

        .qo-share-action__icon svg {
            width: 19px;
            height: 19px;
            stroke-width: 2.3;
        }

        .qo-share-action--primary {
            border-color: transparent;
            background: linear-gradient(135deg, #8f00ff 0%, #0066ff 100%);
            color: #ffffff;
            box-shadow: 0 10px 22px rgba(80, 70, 255, 0.24);
        }

        .kara-quick-toast {
            position: fixed;
            left: 50%;
            bottom: 24px;
            z-index: 2400;
            display: none;
            max-width: min(360px, calc(100vw - 32px));
            transform: translateX(-50%);
            padding: 12px 14px;
            border-radius: 16px;
            background: #111827;
            color: #ffffff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .28);
            font-size: 13px;
            font-weight: 850;
            line-height: 1.3;
            text-align: center;
        }

        .kara-quick-toast.is-show {
            display: block;
        }

        @media (min-width: 860px) {
            .demo-phone {
                width: min(520px, 100%);
            }

            .kara-quick-modal {
                align-items: center;
                padding: 24px;
            }

            .kara-quick-modal__sheet {
                width: min(520px, calc(100vw - 48px));
                max-height: calc(100vh - 48px);
                border-radius: 28px;
            }
        }

        @media (max-width: 420px) {
            .demo-shell {
                padding: 0;
            }

            .demo-phone {
                width: 100%;
                min-height: calc(100vh - 72px);
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .portal-view--karaoke .kara-quick-create-grid {
                grid-template-columns: 1fr;
            }

            .portal-view--karaoke .kara-quick-card {
                grid-template-columns: 72px minmax(0, 1fr) 32px;
                gap: 8px;
            }

            .portal-view--karaoke .kara-quick-card__grid {
                grid-template-columns: 1fr;
            }

            .kara-quick-share-hero {
                grid-template-columns: 42px minmax(0, 1fr);
            }

            .kara-quick-share-grid {
                grid-template-columns: 1fr;
            }

            .kara-quick-share-qrbox img {
                width: 154px;
                height: 154px;
            }

            .qo-share-actions {
                gap: 8px;
            }

            .qo-share-action {
                min-height: 48px;
                padding: 8px 9px;
                border-radius: 14px;
                font-size: 12px;
            }

            .qo-share-action__icon {
                width: 20px;
                height: 20px;
            }

            .qo-share-action__icon svg {
                width: 17px;
                height: 17px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .kara-quick-modal__backdrop,
            .kara-quick-modal__sheet {
                transition: none;
            }
        }

        @media (max-width: 767px) {
            .demo-shell {
                display: block;
                width: 100%;
                padding: 0;
            }

            .demo-phone.portal-view--karaoke {
                width: 100%;
                max-width: none;
                margin: 0 0 24px;
                padding: 14px;
                border-left: 0;
                border-right: 0;
                border-radius: 0 0 24px 24px;
                box-shadow: none;
            }
        }
    </style>

    <div class="quick-shell">
        <main class="demo-shell">
            <div class="demo-phone portal-view--karaoke">
                <header class="demo-topbar">
                    <div class="demo-title">
                        <span>MAXSIM</span>
                        <strong>Quick Onboarding</strong>
                    </div>
                    <div class="demo-counter">{{ $entries->count() }}</div>
                </header>

                @if (session('success'))
                <div class="mb-3 rounded-xl bg-emerald-50 p-3 text-sm font-bold text-emerald-700 ring-1 ring-emerald-100">
                    {{ session('success') }}
                </div>
                @endif

                @if ($errors->any())
                <div class="mb-3 rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700 ring-1 ring-red-100">
                    {{ $errors->first() }}
                </div>
                @endif

                <section class="kara-quick" data-avatar-cropper data-quick-onboarding-share-root data-sync-url="{{ route('quick-onboarding.sync') }}">
                    <!-- <div class="kara-quick-tabs" aria-label="Quick Onboarding">
                        <button class="kara-quick-tabs__item" type="button">Tạo</button>
                    </div> -->

                    <section class="kara-quick-panel" aria-label="Công cụ tạo lời mời">
                        <div class="kara-quick-panel__header">
                            <div>
                                <h3>Tạo</h3>
                                <p>Tạo thêm nhiều lời mời cho cùng một chi nhánh.</p>
                            </div>
                            <span class="kara-quick-panel__badge">Nhanh</span>
                        </div>

                        <form method="POST" action="{{ route('quick-onboarding.add-more') }}" class="kara-quick-create-form">
                            @csrf
                            <div class="kara-quick-create-grid">
                                <label class="kara-quick-field">
                                    <span>Chọn chi nhánh</span>
                                    <select name="branch_id" required @disabled($branches->isEmpty())>
                                        @forelse ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) $selectedBranchId===(string) $branch->id)>
                                            {{ $branch->name }}
                                        </option>
                                        @empty
                                        <option value="">Chưa có cơ sở khả dụng</option>
                                        @endforelse
                                    </select>
                                </label>
                                <label class="kara-quick-field">
                                    <span>Số lượng</span>
                                    <input type="number" name="quantity" min="1" max="1000" inputmode="numeric" value="{{ old('quantity', 10) }}" required @disabled($branches->isEmpty())>
                                </label>
                            </div>
                            <div class="kara-quick-create-form__actions">
                                <button class="kara-quick-btn kara-quick-btn--primary" type="submit" @disabled($branches->isEmpty())>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M12 5v14"></path>
                                        <path d="M5 12h14"></path>
                                    </svg>
                                    <span>Tạo thêm</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="kara-quick-list-section" aria-labelledby="kara-quick-list-title">
                        <div class="kara-quick-list-section__title">
                            <h3 id="kara-quick-list-title">Danh sách lời mời</h3>
                            <span data-quick-count-label>{{ $entries->count() }} lời mời</span>
                        </div>

                        <div class="kara-quick-list" data-entry-list>
                            @forelse ($entries as $entry)
                            @php($share = $shareOptions[$entry->id] ?? null)
                            @php($payload = app(\App\Services\QuickOnboardingService::class)->syncPayload($entry))
                            @php($displayName = $payload['employee_name'] ?: $entry->expected_name)
                            @php($avatarUrl = $payload['avatar_url'])
                            <article
                                class="kara-quick-card {{ $payload['color_class'] }}" data-avatar-item
                                data-entry-row="{{ $entry->id }}"
                                data-status="{{ $payload['status'] }}">
                                <div class="kara-quick-card__media">
                                    <label
                                        class="kara-quick-avatar {{ $avatarUrl ? '' : 'is-avatar-missing' }}"
                                        data-avatar-wrap
                                        aria-label="Chọn ảnh cho mã {{ $entry->demo_code }}">
                                        {{-- Ảnh hiện tại đã lưu trên server --}}
                                        @if ($avatarUrl)
                                        <img
                                            src="{{ $avatarUrl }}"
                                            alt="Ảnh nhân sự mã {{ $entry->demo_code }}"
                                            data-avatar-img
                                            data-avatar-current
                                            onerror="
                this.hidden = true;
                this.closest('[data-avatar-wrap]')
                    .classList.add('is-avatar-missing');
            ">

                                        <span
                                            class="kara-quick-avatar__placeholder"
                                            data-avatar-placeholder
                                            aria-hidden="true">
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor">
                                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"></path>
                                                <circle cx="12" cy="13" r="3"></circle>
                                            </svg>
                                        </span>
                                        @else
                                        {{-- Chưa có ảnh thì hiện biểu tượng camera --}}
                                        <span
                                            class="kara-quick-avatar__placeholder"
                                            data-avatar-placeholder
                                            aria-hidden="true">
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor">
                                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"></path>
                                                <circle cx="12" cy="13" r="3"></circle>
                                            </svg>
                                        </span>
                                        @endif

                                        {{--
        Ảnh tạm sau khi người dùng bấm “Xác nhận ảnh”.
        Dòng này phải nằm SAU @endif để hoạt động cho cả:
        - nhân sự đã có ảnh
        - nhân sự chưa có ảnh
    --}}
                                        <img
                                            src=""
                                            alt="Ảnh sau khi căn chỉnh"
                                            data-avatar-preview
                                            class="hidden">

                                        {{-- Nhân sự completed thì giữ nguyên quy tắc cũ: không cho đổi ảnh --}}
                                        @if ($payload['status'] !== 'completed')
                                        <input
                                            class="kara-quick-avatar__input"
                                            type="file"
                                            name="avatar"
                                            accept="image/jpeg,image/png,image/webp"
                                            data-avatar-input
                                            data-avatar-url="{{ route(
                'quick-onboarding.entries.avatar',
                $entry,
                absolute: false
            ) }}">
                                        @endif
                                    </label>
                                    <span class="kara-quick-code" data-demo-code>{{ $entry->demo_code ?? '---' }}</span>
                                </div>

                                <div class="kara-quick-card__body">
                                    @if ($payload['status'] === 'completed')
                                    <div class="kara-quick-completed-name" data-employee-name>
                                        {{ $payload['employee_name'] ?: 'Nhân sự mới' }}
                                    </div>
                                    @else
                                    <input
                                        class="kara-quick-name-input"
                                        type="text"
                                        value="{{ $entry->expected_name }}"
                                        maxlength="255"
                                        placeholder="Nhập tên dự kiến"
                                        data-expected-name-input
                                        data-expected-name-url="{{ route('quick-onboarding.entries.expected-name', $entry, absolute: false) }}"
                                        data-default-name="Nhân sự mã {{ $entry->demo_code }}"
                                        autocomplete="off">
                                    @endif

                                    <div class="kara-quick-card__grid">
                                        <label class="kara-quick-mini-field">
                                            <span>Chi nhánh</span>
                                            <span class="kara-quick-branch-pill" data-branch-name>{{ $payload['branch_name'] ?? 'Chưa có cơ sở' }}</span>
                                        </label>
                                        <label class="kara-quick-mini-field">
                                            <span>Chức vụ</span>
                                            <select
                                                data-role-select
                                                data-role-url="{{ route('quick-onboarding.entries.role', $entry, absolute: false) }}"
                                                data-original-role="{{ $entry->intended_role }}"
                                                @disabled($payload['status']==='completed' )
                                                aria-label="Chức vụ mã {{ $entry->demo_code }}">
                                                @foreach ($roleOptions as $roleValue => $roleLabel)
                                                <option value="{{ $roleValue }}" @selected($entry->intended_role === $roleValue)>
                                                    {{ $roleLabel }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                </div>

                                @if ($share)
                                <div class="kara-quick-card__actions" aria-label="Tác vụ lời mời">
                                    <button
                                        class="kara-quick-icon-btn kara-quick-icon-btn--danger"
                                        type="button"
                                        aria-label="Xóa lời mời"
                                        data-entry-delete
                                        data-delete-url="{{ route('quick-onboarding.entries.destroy', $entry, absolute: false) }}"
                                        @disabled($payload['status']==='completed' )>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                            <path d="M19 6l-1 14c-.1 1-1 2-2 2H8c-1 0-1.9-1-2-2L5 6"></path>
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                        </svg>
                                    </button>
                                    <button
                                        class="kara-quick-icon-btn kara-quick-icon-btn--share"
                                        type="button"
                                        aria-label="Chia sẻ lời mời"
                                        data-share-open
                                        data-entry-id="{{ $entry->id }}"
                                        data-name="{{ $displayName ?: 'Nhân sự' }}"
                                        data-code="{{ $entry->demo_code }}"
                                        data-link="{{ $share['url'] }}"
                                        data-qr-url="{{ $share['qr_url'] ?? route('quick-onboarding.entries.qr', $entry, absolute: false) }}"
                                        data-avatar-url="{{ $avatarUrl }}"
                                        data-branch="{{ $payload['branch_name'] ?? '' }}"
                                        data-role="{{ $payload['role_label'] ?? '' }}"
                                        data-mark-sent-url="{{ route('quick-onboarding.entries.sent', $entry, absolute: false) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <path d="m8.6 13.5 6.8 4"></path>
                                            <path d="m15.4 6.5-6.8 4"></path>
                                        </svg>
                                    </button>
                                </div>
                                @else
                                <span></span>
                                @endif
                            </article>
                            @empty
                            <div class="kara-quick-empty">
                                <div>
                                    <strong>Chưa có lời mời.</strong><br>
                                    <span>Chọn chi nhánh, nhập số lượng và bấm Tạo thêm để bắt đầu.</span>
                                </div>
                            </div>
                            @endforelse
                        </div>
                    </section>
                    <div
                        data-avatar-modal
                        aria-hidden="true"
                        class="fixed inset-0 z-[100] hidden">
                        <button
                            type="button"
                            data-avatar-modal-close
                            aria-label="Đóng chỉnh ảnh"
                            class="absolute inset-0 h-full w-full bg-slate-950/80 backdrop-blur-sm"></button>

                        <div class="relative flex min-h-full items-center justify-center p-3 sm:p-6">
                            <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                    <div>
                                        <h3 class="text-lg font-black text-slate-950">
                                            Căn chỉnh ảnh nhân sự
                                        </h3>

                                        <p class="mt-1 text-sm font-semibold text-slate-500">
                                            Kéo ảnh trong khung vuông để chọn phần muốn sử dụng.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        data-avatar-modal-close
                                        class="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-xl font-black text-slate-700">
                                        ×
                                    </button>
                                </div>

                                <div class="p-4 sm:p-5">
                                    <div
                                        data-avatar-error
                                        class="mb-3 hidden rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-700"></div>

                                    <div
                                        data-avatar-stage
                                        class="relative h-[55vh] min-h-[320px] max-h-[560px] w-full overflow-hidden rounded-3xl bg-slate-950"></div>
                                    <p class="mt-3 text-sm font-semibold text-slate-500">
                                        Mẹo: ảnh nên phủ kín khung vuông, tránh để hở lề caro ở các cạnh.
                                    </p>

                                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
                                        <button
                                            type="button"
                                            data-crop-action="zoom-out"
                                            class="min-h-11 rounded-2xl bg-slate-100 px-3 text-sm font-black text-slate-700">
                                            Thu nhỏ
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="zoom-in"
                                            class="min-h-11 rounded-2xl bg-slate-100 px-3 text-sm font-black text-slate-700">
                                            Phóng to
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="rotate-left"
                                            class="min-h-11 rounded-2xl bg-slate-100 px-3 text-sm font-black text-slate-700">
                                            Xoay trái
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="rotate-right"
                                            class="min-h-11 rounded-2xl bg-slate-100 px-3 text-sm font-black text-slate-700">
                                            Xoay phải
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="reset"
                                            class="min-h-11 rounded-2xl bg-slate-100 px-3 text-sm font-black text-slate-700">
                                            Đặt lại
                                        </button>

                                        <button
                                            type="button"
                                            data-crop-action="reselect"
                                            class="col-span-2 min-h-11 rounded-2xl bg-amber-50 px-3 text-sm font-black text-amber-700 sm:col-span-3 xl:col-span-1">
                                            Chọn lại ảnh
                                        </button>
                                    </div>
                                </div>

                                <div class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 sm:p-5">
                                    <button
                                        type="button"
                                        data-crop-action="cancel"
                                        class="min-h-12 rounded-2xl bg-slate-100 px-5 text-sm font-black text-slate-700">
                                        Hủy
                                    </button>

                                    <button
                                        type="button"
                                        data-crop-action="confirm"
                                        class="min-h-12 rounded-2xl bg-gradient-to-r from-indigo-600 to-sky-600 px-5 text-sm font-black text-white shadow-lg">
                                        Xác nhận ảnh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="kara-quick-modal" data-share-sheet hidden>
        <div class="kara-quick-modal__backdrop" data-share-close></div>
        <div class="kara-quick-modal__sheet" role="dialog" aria-modal="true" aria-labelledby="quick-share-title">
            <span class="kara-quick-modal__grabber" aria-hidden="true"></span>
            <header class="kara-quick-modal__header">
                <div class="kara-quick-modal__heading">
                    <h3 id="quick-share-title">Chia sẻ lời mời</h3>
                    <p>Gửi link hoặc QR cho nhân sự cập nhật hồ sơ.</p>
                </div>
                <button class="kara-quick-modal__close" type="button" aria-label="Đóng" data-share-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </header>
            <div class="kara-quick-modal__body">
                <section class="kara-quick-share-card">
                    <div class="kara-quick-share-hero">
                        <span class="kara-quick-share-hero__avatar" data-share-avatar>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M2 21a8 8 0 0 1 13.3-6"></path>
                                <circle cx="10" cy="8" r="5"></circle>
                                <path d="m16 19 2 2 4-4"></path>
                            </svg>
                        </span>
                        <div class="kara-quick-share-hero__content">
                            <span data-sheet-code>Mã 001</span>
                            <strong data-sheet-name>Nhân viên mới</strong>
                            <small data-sheet-meta>Chi nhánh · Nhân viên</small>
                        </div>
                    </div>

                    <div class="kara-quick-share-grid">
                        <div class="kara-quick-share-qrbox">
                            <img src="" alt="QR lời mời" data-sheet-qr>
                            <small data-sheet-qr-code>---</small>
                        </div>
                        <div class="kara-quick-share-tools">
                            <label class="kara-quick-link-box">
                                <span>Link cập nhật</span>
                                <input type="text" readonly data-sheet-link>
                            </label>
                            <div class="kara-quick-share-action-grid">
                                <button class="kara-quick-mini-btn" type="button" data-share-copy-link>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <rect width="14" height="14" x="8" y="8" rx="2"></rect>
                                        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                                    </svg>
                                    <span>Copy link</span>
                                </button>
                                <button class="kara-quick-mini-btn" type="button" data-share-download-qr>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <path d="M7 10l5 5 5-5"></path>
                                        <path d="M12 15V3"></path>
                                    </svg>
                                    <span>Tải QR</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
                <footer class="qo-share-actions">
                    <button class="qo-share-action qo-share-action--copy" type="button" data-share-copy-message>
                        <span class="qo-share-action__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <rect width="14" height="14" x="8" y="8" rx="2"></rect>
                                <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                            </svg>
                        </span>
                        <span>Copy tin nhắn</span>
                    </button>
                    <button class="qo-share-action qo-share-action--primary" type="button" data-share-quick>
                        <span class="qo-share-action__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="m22 2-7 20-4-9-9-4Z"></path>
                                <path d="M22 2 11 13"></path>
                            </svg>
                        </span>
                        <span>Chia sẻ nhanh</span>
                    </button>
                </footer>
            </div>
        </div>
    </div>
    <div class="kara-quick-toast" role="status" data-share-toast></div>

    @vite('resources/js/quick-onboarding.js')
</x-app-layout>