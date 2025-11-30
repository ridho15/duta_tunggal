@props([
    'badge' => null,
    'icon' => 'heroicon-o-bell',
    'iconColor' => 'gray',
    'tooltip' => null,
])

@php
    $notificationsCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp

<x-filament::dropdown
    :badge="$notificationsCount"
    placement="bottom-end"
    teleport
    wire:poll.30s="refreshNotifications"
>
    <x-slot name="trigger">
        <x-filament::icon-button
            :color="$iconColor"
            :icon="$icon"
            :tooltip="$tooltip"
            outlined
        />
    </x-slot>

    <x-filament::dropdown.list>
        @if($notificationsCount > 0)
            @foreach(auth()->user()->unreadNotifications()->latest()->take(10)->get() as $notification)
                <x-filament::dropdown.list.item
                    :href="$notification->data['actions'][0]['url'] ?? null"
                    wire:click="markAsRead('{{ $notification->id }}')"
                >
                    <div class="flex items-start space-x-3">
                        @if(isset($notification->data['icon']))
                            <div class="flex-shrink-0">
                                <x-dynamic-component
                                    :component="'heroicon-' . ($notification->data['icon'] ?? 'o-bell')"
                                    class="w-5 h-5 text-gray-400"
                                />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $notification->data['title'] ?? 'Notification' }}
                            </p>
                            <p class="text-sm text-gray-500 truncate">
                                {{ $notification->data['body'] ?? '' }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </x-filament::dropdown.list.item>
            @endforeach

            <x-filament::dropdown.list.item
                href="{{ route('filament.admin.resources.notifications.index') }}"
                class="text-center"
            >
                View all notifications
            </x-filament::dropdown.list.item>
        @else
            <x-filament::dropdown.list.item class="text-center text-gray-500">
                No new notifications
            </x-filament::dropdown.list.item>
        @endif
    </x-filament::dropdown.list>
</x-filament::dropdown>