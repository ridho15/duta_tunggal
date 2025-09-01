<?php

require_once 'vendor/autoload.php';

use App\Filament\pages\ArApManagementPage;

echo "Testing ArApManagementPage class:\n";

try {
    echo "1. Class exists: " . (class_exists(ArApManagementPage::class) ? 'YES' : 'NO') . "\n";
    echo "2. Can access navigation: " . (ArApManagementPage::canAccess() ? 'YES' : 'NO') . "\n";
    echo "3. Should register navigation: " . (ArApManagementPage::shouldRegisterNavigation() ? 'YES' : 'NO') . "\n";
    echo "4. Navigation label: " . ArApManagementPage::getNavigationLabel() . "\n";
    echo "5. Navigation group: " . ArApManagementPage::getNavigationGroup() . "\n";
    echo "6. Navigation sort: " . ArApManagementPage::getNavigationSort() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
