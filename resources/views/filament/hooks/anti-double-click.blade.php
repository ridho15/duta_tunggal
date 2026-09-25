<script>
/**
 * Global Anti-Double-Click & Debounce Guard for Filament Repeaters & Actions
 * Prevents rapid double clicks from spawning duplicate blank rows in repeaters.
 */
(function () {
    'use strict';

    const lockedButtons = new WeakSet();

    function isRepeaterAddButton(button) {
        if (!button || button.tagName !== 'BUTTON') {
            return false;
        }

        // Check if button is inside a Filament repeater
        const repeater = button.closest('.fi-fo-repeater, [data-repeater], .filament-forms-repeater-component');
        if (!repeater) {
            return false;
        }

        // Skip delete / reorder / collapse actions inside an individual item header
        if (button.closest('.fi-fo-repeater-item-header, .fi-fo-repeater-item-actions')) {
            return false;
        }

        const wireClick = button.getAttribute('wire:click') || '';
        const text = (button.innerText || button.textContent || '').trim().toLowerCase();

        return (
            wireClick.includes('mountFormComponentAction') ||
            wireClick.includes('callMountedFormComponentAction') ||
            wireClick.includes('createItem') ||
            wireClick.includes('add') ||
            text.includes('tambah') ||
            text.includes('add') ||
            button.hasAttribute('data-repeater-add-btn')
        );
    }

    function lockButton(button) {
        lockedButtons.add(button);
        button.dataset.repeaterLocked = 'true';
        button.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');

        // Safety fallback: auto-unlock after 1200ms
        setTimeout(() => {
            unlockButton(button);
        }, 1200);
    }

    function unlockButton(button) {
        if (!button) return;
        lockedButtons.delete(button);
        delete button.dataset.repeaterLocked;
        button.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    }

    function unlockAll() {
        document.querySelectorAll('button[data-repeater-locked="true"]').forEach(unlockButton);
    }

    // Capture phase event listener to intercept double clicks before Livewire/Alpine handles them
    document.addEventListener('click', function (e) {
        const button = e.target.closest('button');
        if (!button || !isRepeaterAddButton(button)) {
            return;
        }

        const now = Date.now();
        const lastClick = parseInt(button.dataset.lastClickTime || '0', 10);

        // If clicked within 750ms or already locked
        if (now - lastClick < 750 || lockedButtons.has(button) || button.dataset.repeaterLocked === 'true') {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }

        button.dataset.lastClickTime = now.toString();
        lockButton(button);
    }, true);

    // Livewire hooks integration
    function initLivewireHooks() {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
            return;
        }

        if (window.__repeaterAntiDoubleClickHooked) {
            return;
        }
        window.__repeaterAntiDoubleClickHooked = true;

        try {
            window.Livewire.hook('commit', ({ succeed, fail }) => {
                succeed(() => {
                    requestAnimationFrame(unlockAll);
                });
                fail(() => {
                    unlockAll();
                });
            });
        } catch (err) {}

        try {
            window.Livewire.hook('morph.updated', () => {
                unlockAll();
            });
        } catch (err) {}

        try {
            window.Livewire.hook('message.processed', () => {
                unlockAll();
            });
        } catch (err) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLivewireHooks);
    } else {
        initLivewireHooks();
    }

    document.addEventListener('livewire:initialized', initLivewireHooks);
    document.addEventListener('livewire:navigated', initLivewireHooks);
})();
</script>
