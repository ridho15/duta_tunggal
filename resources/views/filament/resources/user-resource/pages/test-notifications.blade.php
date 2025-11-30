<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-6 bg-white rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Test Notifications</h2>
            <p class="text-gray-600 mb-4">
                Klik tombol "Test Notification" untuk membuat notification dengan icon.
            </p>
            <p class="text-sm text-gray-500">
                Setelah klik, periksa icon notification di top-right corner admin panel.
            </p>
        </div>

        <div class="p-6 bg-white rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-4">Current Notifications</h3>
            @if(auth()->check())
                @php
                    $notifications = auth()->user()->notifications()->latest()->take(5)->get();
                @endphp

                @if($notifications->count() > 0)
                    <div class="space-y-2">
                        @foreach($notifications as $notification)
                            <div class="p-4 border rounded-lg {{ $notification->read_at ? 'bg-gray-50' : 'bg-blue-50' }}">
                                <div class="flex items-start space-x-3">
                                    @if(isset($notification->data['icon']))
                                        <div class="flex-shrink-0">
                                            <x-dynamic-component
                                                :component="'heroicon-' . ($notification->data['icon'] ?? 'o-bell')"
                                                class="w-5 h-5 {{ $notification->read_at ? 'text-gray-400' : 'text-blue-500' }}"
                                            />
                                        </div>
                                    @endif

                                    <div class="flex-1">
                                        <h4 class="font-medium {{ $notification->read_at ? 'text-gray-600' : 'text-gray-900' }}">
                                            {{ $notification->data['title'] ?? 'No Title' }}
                                        </h4>
                                        <p class="text-sm {{ $notification->read_at ? 'text-gray-500' : 'text-gray-700' }}">
                                            {{ $notification->data['body'] ?? 'No Body' }}
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500">Tidak ada notifications.</p>
                @endif
            @else
                <p class="text-red-500">User tidak login.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>