@props([
    'name',
    'title' => 'Konfirmasi Aksi',
    'variant' => 'danger',
    'confirmText' => 'Ya, Lanjutkan',
    'cancelText' => 'Batal',
    'action' => null,
    'method' => 'POST',
    'hasForm' => true,
])

@php
    $isJsVar = preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name);
    $iconConfig = match ($variant) {
        'success' => [
            'bg' => 'bg-[#dcfce7]',
            'text' => 'text-[#15803d]',
            'button' => 'bg-[#15803d] hover:bg-[#14532d] text-white focus:ring-[#15803d]/30',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>',
        ],
        'warning' => [
            'bg' => 'bg-amber-100',
            'text' => 'text-amber-600',
            'button' => 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500/30',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
        ],
        default => [
            'bg' => 'bg-red-100',
            'text' => 'text-red-600',
            'button' => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500/30',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>',
        ],
    };
@endphp

<div
    x-data="{
        open: false,
        data: {},
        init() {
            @if ($isJsVar)
                try {
                    this.$watch('{{ $name }}', val => { this.open = Boolean(val); });
                } catch (e) {}
            @endif
        },
        closeModal() {
            this.open = false;
            @if ($isJsVar)
                try {
                    if (typeof {{ $name }} !== 'undefined') { {{ $name }} = false; }
                } catch (e) {}
            @endif
        }
    }"
    x-on:open-modal-{{ $name }}.window="open = true; data = $event.detail || {}"
    x-on:close-modal-{{ $name }}.window="closeModal()"
    x-show="open"
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;"
    role="dialog"
    aria-modal="true"
>
    {{-- Backdrop Overlay --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
        @click="closeModal()"
    ></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        {{-- Modal Dialog Panel --}}
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            @click.outside="closeModal()"
            @keydown.escape.window="closeModal()"
            class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-gray-100"
        >
            @if ($hasForm)
                <form :action="data.action || '{{ $action ?? '' }}'" method="POST">
                    @csrf
                    <input type="hidden" name="_method" :value="data.method || '{{ strtoupper($method) }}'">
            @endif

            <div class="bg-white p-6 pb-4">
                <div class="sm:flex sm:items-start gap-4">
                    {{-- Icon Container --}}
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full {{ $iconConfig['bg'] }} sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-5 w-5 {{ $iconConfig['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            {!! $iconConfig['icon'] !!}
                        </svg>
                    </div>

                    {{-- Content Area --}}
                    <div class="mt-3 text-center sm:mt-0 sm:text-left flex-1">
                        <h3 class="text-base font-bold text-gray-900 tracking-tight" id="modal-title" x-text="data.title || '{{ $title }}'">
                            {{ $title }}
                        </h3>
                        <div class="mt-2 text-xs text-gray-600 leading-relaxed space-y-2">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer Buttons --}}
            <div class="bg-gray-50 px-6 py-3.5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-gray-100">
                <button
                    type="button"
                    @click="closeModal()"
                    class="w-full sm:w-auto inline-flex justify-center rounded-xl px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-300 shadow-xs hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"
                    x-text="data.cancelText || '{{ $cancelText }}'"
                >
                    {{ $cancelText }}
                </button>

                @if ($hasForm)
                    <button
                        type="submit"
                        class="w-full sm:w-auto inline-flex justify-center rounded-xl px-4 py-2 text-xs font-bold shadow-sm focus:outline-none focus:ring-2 transition {{ $iconConfig['button'] }}"
                        x-text="data.confirmText || '{{ $confirmText }}'"
                    >
                        {{ $confirmText }}
                    </button>
                @else
                    {{ $footer ?? '' }}
                @endif
            </div>

            @if ($hasForm)
                </form>
            @endif
        </div>
    </div>
</div>
