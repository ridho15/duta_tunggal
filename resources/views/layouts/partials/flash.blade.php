@if (session('success'))
    <div class="mb-4 p-4 rounded-xl bg-[#f0fdf4] border border-[#86efac] text-[#14532d] text-sm flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 flex-shrink-0 text-[#15803d] mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M5 13l4 4L19 7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
        <div class="flex-1 font-medium">
            {{ session('success') }}
        </div>
    </div>
@endif

@if (session('error'))
    <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 flex-shrink-0 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
            <path d="M12 8v4M12 16h.01" stroke-width="2" stroke-linecap="round"></path>
        </svg>
        <div class="flex-1 font-medium">
            {{ session('error') }}
        </div>
    </div>
@endif

@if (session('warning'))
    <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 flex-shrink-0 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
        <div class="flex-1 font-medium">
            {{ session('warning') }}
        </div>
    </div>
@endif
