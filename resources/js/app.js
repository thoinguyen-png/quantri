import './bootstrap';
import './avatar-cropper';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

(() => {
    const editableSelector = 'input:not([type="hidden"]):not([readonly]):not([disabled]), textarea:not([readonly]):not([disabled]), select:not([disabled])';
    const mobileQuery = window.matchMedia('(max-width: 767px)');
    let blurTimer = null;

    function isEditableTarget(target) {
        return target instanceof Element && target.matches(editableSelector);
    }

    function setKeyboardOpen(isOpen) {
        document.body.classList.toggle('keyboard-open', isOpen && mobileQuery.matches);
    }

    document.addEventListener('focusin', (event) => {
        if (!isEditableTarget(event.target)) {
            return;
        }

        window.clearTimeout(blurTimer);
        setKeyboardOpen(true);
    });

    document.addEventListener('focusout', () => {
        window.clearTimeout(blurTimer);
        blurTimer = window.setTimeout(() => {
            const active = document.activeElement;
            setKeyboardOpen(isEditableTarget(active));
        }, 120);
    });

    mobileQuery.addEventListener?.('change', () => {
        setKeyboardOpen(isEditableTarget(document.activeElement));
    });
})();
