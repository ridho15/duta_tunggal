<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Dashboard Title -->
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="text-gray-600 dark:text-gray-400">Welcome to Duta Tunggal ERP System</p>
        </div>

        <!-- Widgets Container -->
        <div class="filament-dashboard-widgets">
            <x-filament-widgets::widgets
                :widgets="$this->getVisibleWidgets()"
                :columns="$this->getColumns()"
            />
        </div>
    </div>
</x-filament-panels::page>