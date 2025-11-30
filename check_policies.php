<?php
require 'vendor/autoload.php';

use App\Http\Controllers\HelperController;

// Get permissions
$permissions = HelperController::listPermission();

// Get policy files
$policyFiles = glob('app/Policies/*.php');
$policies = [];
foreach ($policyFiles as $file) {
    $policies[] = basename($file, 'Policy.php');
}

echo "Total permissions: " . count($permissions) . "\n";
echo "Total policies: " . count($policies) . "\n\n";

$missing = [];
$extra = [];

foreach (array_keys($permissions) as $key) {
    $model = str_replace(' ', '', ucwords($key));
    $policyName = $model . 'Policy';
    
    if (!in_array($policyName, $policies)) {
        $missing[] = $key;
    }
}

foreach ($policies as $policy) {
    $modelName = str_replace('Policy', '', $policy);
    $permissionKey = strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $modelName));
    
    if (!isset($permissions[$permissionKey])) {
        $extra[] = $policy;
    }
}

echo "Missing policies for permissions:\n";
foreach ($missing as $item) {
    echo "- $item\n";
}

echo "\nExtra policies without permissions:\n";
foreach ($extra as $item) {
    echo "- $item\n";
}
