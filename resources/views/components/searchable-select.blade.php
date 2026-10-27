@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => '-- Pilih Opsi --',
    'required' => false,
    'disabled' => false,
    'id' => null,
    'error' => null,
    'hint' => null,
    'autoSubmit' => false,
    'size' => 'md',
])

@php
    $elementId = $id ?? 'searchable_select_' . str_replace(['[', ']'], ['_', ''], $name);
    $initialValue = old($name, $value);

    // Normalisasi struktur opsi menjadi array seragam: id, label, subtext, badge
    $normalizedOptions = collect($options)->map(function ($item) {
        if (is_array($item)) {
            return [
                'id' => (string)($item['id'] ?? $item['value'] ?? ''),
                'label' => (string)($item['label'] ?? $item['name'] ?? $item['title'] ?? ''),
                'subtext' => (string)($item['subtext'] ?? $item['code'] ?? $item['sku'] ?? $item['description'] ?? ''),
                'badge' => (string)($item['badge'] ?? ''),
            ];
        } elseif (is_object($item)) {
            return [
                'id' => (string)($item->id ?? $item->value ?? ''),
                'label' => (string)($item->name ?? $item->label ?? $item->title ?? ''),
                'subtext' => (string)($item->code ?? $item->sku ?? $item->subtext ?? $item->description ?? ''),
                'badge' => (string)($item->badge ?? ''),
            ];
        } else {
            return [
                'id' => (string)$item,
                'label' => (string)$item,
                'subtext' => '',
                'badge' => '',
            ];
        }
    })->values()->all();

    $errorField = $error ?? $name;

    $sizeClasses = [
        'xs' => 'py-1 px-2.5 text-xs',
        'sm' => 'py-2 px-3 text-xs',
        'md' => 'py-2.5 px-3.5 text-sm',
    ];
    $buttonSizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
@endphp

<div
    {{ $attributes->merge(['class' => 'w-full relative']) }}
    x-data="searchableSelect({
        name: '{{ $name }}',
        options: {{ Js::from($normalizedOptions) }},
        selected: '{{ (string)$initialValue }}',
        placeholder: '{{ $placeholder }}',
        disabled: {{ $disabled ? 'true' : 'false' }},
        autoSubmit: {{ $autoSubmit ? 'true' : 'false' }},
    })"
    x-id="['searchable-select']"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    @if ($label)
        <label for="{{ $elementId }}" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    {{-- Native Hidden Input untuk Form POST/PUT/GET --}}
    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $elementId }}"
        value="{{ (string) $initialValue }}"
        x-model="selected"
        @if ($required) required @endif
    >

    {{-- Trigger Button --}}
    <div class="relative">
        <button
            type="button"
            @click="toggleDropdown()"
            :disabled="disabled"
            class="w-full text-left bg-white rounded-lg border {{ $buttonSizeClass }} flex items-center justify-between gap-2 shadow-xs transition focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
            :class="{
                'border-red-400 bg-red-50/20': {{ $errors->has($errorField) ? 'true' : 'false' }},
                'border-gray-300 hover:border-gray-400': !{{ $errors->has($errorField) ? 'true' : 'false' }} && !open,
                'border-[#15803d] ring-2 ring-[#15803d]/20': open,
                'opacity-60 cursor-not-allowed bg-gray-50': disabled
            }"
            aria-haspopup="listbox"
            :aria-expanded="open"
        >
            <div class="flex-1 truncate">
                <template x-if="selectedOption">
                    <div class="flex items-center gap-2 truncate">
                        <span class="font-semibold text-gray-900 truncate" x-text="selectedOption.label"></span>
                        <template x-if="selectedOption.subtext">
                            <span class="text-xs text-gray-500 truncate" x-text="'(' + selectedOption.subtext + ')'"></span>
                        </template>
                        <template x-if="selectedOption.badge">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#f0fdf4] text-[#15803d] border border-[#bbf7d0]" x-text="selectedOption.badge"></span>
                        </template>
                    </div>
                </template>
                <template x-if="!selectedOption">
                    <span class="text-gray-400 font-normal" x-text="placeholder"></span>
                </template>
            </div>

            <div class="flex items-center gap-1 flex-shrink-0 text-gray-400">
                {{-- Clear Selection Button --}}
                <template x-if="selected && !disabled && !{{ $required ? 'true' : 'false' }}">
                    <span
                        role="button"
                        @click.stop="clearSelection()"
                        class="p-1 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                        title="Hapus Pilihan"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                </template>
                {{-- Dropdown Chevron --}}
                <span class="p-0.5 pointer-events-none text-gray-400">
                    <svg
                        class="w-4 h-4 transition-transform duration-200"
                        :class="{ 'rotate-180 text-[#15803d]': open }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </span>
            </div>
        </button>

        {{-- Dropdown Panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-95"
            class="absolute z-50 mt-1 w-full bg-white rounded-xl shadow-xl border border-gray-200 py-2 overflow-hidden"
            style="display: none;"
        >
            {{-- Search Input Box --}}
            <div class="px-2.5 pb-2 border-b border-gray-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        x-ref="searchInput"
                        x-model="searchQuery"
                        @keydown.arrow-down.prevent="highlightNext()"
                        @keydown.arrow-up.prevent="highlightPrev()"
                        @keydown.enter.prevent="selectHighlighted()"
                        class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition placeholder-gray-400 font-medium"
                        placeholder="Ketik untuk mencari..."
                    >
                    <template x-if="searchQuery">
                        <button
                            type="button"
                            @click="searchQuery = ''; $refs.searchInput.focus()"
                            class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Options List Container --}}
            <ul
                class="max-h-60 overflow-y-auto px-1 pt-1 space-y-0.5 custom-scrollbar text-xs"
                role="listbox"
            >
                <template x-for="(opt, idx) in filteredOptions" :key="opt.id">
                    <li
                        @click="selectOption(opt)"
                        @mouseenter="highlightIndex = idx"
                        class="px-2.5 py-2 rounded-lg cursor-pointer flex items-center justify-between gap-2 transition"
                        :class="{
                            'bg-[#f0fdf4] text-[#14532d] font-bold': selected === opt.id,
                            'bg-gray-100 text-gray-900': highlightIndex === idx && selected !== opt.id,
                            'text-gray-700': selected !== opt.id && highlightIndex !== idx
                        }"
                        role="option"
                        :aria-selected="selected === opt.id"
                    >
                        <div class="flex-1 truncate">
                            <div class="truncate" x-text="opt.label"></div>
                            <template x-if="opt.subtext">
                                <div class="text-[11px] text-gray-400 truncate mt-0.5" x-text="opt.subtext"></div>
                            </template>
                        </div>

                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <template x-if="opt.badge">
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-gray-100 text-gray-600" x-text="opt.badge"></span>
                            </template>
                            <template x-if="selected === opt.id">
                                <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </template>
                        </div>
                    </li>
                </template>

                {{-- Empty Search Results --}}
                <li
                    x-show="filteredOptions.length === 0"
                    class="py-6 px-3 text-center text-gray-400 select-none"
                >
                    <svg class="w-6 h-6 mx-auto text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <div class="font-semibold text-gray-600 text-xs">Tidak Ada Hasil Ditemukan</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">Coba kata kunci pencarian yang lain.</div>
                </li>
            </ul>
        </div>
    </div>

    @if ($hint)
        <p class="text-[11px] text-gray-400 mt-1">{{ $hint }}</p>
    @endif

    @error($errorField)
        <p class="text-xs text-red-600 mt-1 font-medium">{{ $message }}</p>
    @enderror
</div>

@once
<script>
function searchableSelect(config) {
    return {
        name: config.name,
        options: config.options || [],
        selected: config.selected || '',
        placeholder: config.placeholder || '-- Pilih Opsi --',
        disabled: config.disabled || false,
        autoSubmit: config.autoSubmit || false,
        open: false,
        searchQuery: '',
        highlightIndex: 0,

        get selectedOption() {
            return this.options.find(opt => String(opt.id) === String(this.selected)) || null;
        },

        get filteredOptions() {
            if (!this.searchQuery.trim()) {
                return this.options;
            }
            const q = this.searchQuery.toLowerCase();
            return this.options.filter(opt => {
                const labelMatch = (opt.label || '').toLowerCase().includes(q);
                const subMatch = (opt.subtext || '').toLowerCase().includes(q);
                return labelMatch || subMatch;
            });
        },

        toggleDropdown() {
            if (this.disabled) return;
            this.open = !this.open;
            if (this.open) {
                this.searchQuery = '';
                this.highlightIndex = 0;
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
            }
        },

        selectOption(opt) {
            this.selected = String(opt.id);
            this.open = false;
            this.searchQuery = '';
            this.$dispatch('change', this.selected);
            this.$dispatch('input', this.selected);

            this.$nextTick(() => {
                const hiddenInput = this.$root.querySelector('input[type="hidden"]');
                if (hiddenInput) {
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (this.autoSubmit) {
                    const form = this.$root.closest('form');
                    if (form) form.submit();
                }
            });
        },

        clearSelection() {
            this.selected = '';
            this.searchQuery = '';
            this.$dispatch('change', '');
            this.$dispatch('input', '');

            this.$nextTick(() => {
                const hiddenInput = this.$root.querySelector('input[type="hidden"]');
                if (hiddenInput) {
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (this.autoSubmit) {
                    const form = this.$root.closest('form');
                    if (form) form.submit();
                }
            });
        },

        highlightNext() {
            if (this.highlightIndex < this.filteredOptions.length - 1) {
                this.highlightIndex++;
            }
        },

        highlightPrev() {
            if (this.highlightIndex > 0) {
                this.highlightIndex--;
            }
        },

        selectHighlighted() {
            if (this.filteredOptions[this.highlightIndex]) {
                this.selectOption(this.filteredOptions[this.highlightIndex]);
            }
        }
    };
}
</script>
@endonce
