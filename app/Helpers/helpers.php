<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('formatAmount')) {
    /**
     * Format amount dengan pemisah ribuan titik (.) dan desimal koma (,)
     * Format Indonesia: 1.234.567,89
     *
     * @param float|int|string|null $amount
     * @param int $decimals
     * @return string
     */
    function formatAmount($amount, int $decimals = 2): string
    {
        if ($amount === null) {
            return number_format(0, $decimals, ',', '.');
        }
        return number_format((float) $amount, $decimals, ',', '.');
    }
}

if (!function_exists('formatAmountNoDecimal')) {
    /**
     * Format amount tanpa desimal
     * Format: 1.234.567
     *
     * @param float|int|string|null $amount
     * @return string
     */
    function formatAmountNoDecimal($amount): string
    {
        if ($amount === null) {
            return number_format(0, 0, ',', '.');
        }
        return number_format((float) $amount, 0, ',', '.');
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * Format amount dengan prefix Rp dan pemisah ribuan titik
     * Format: Rp 1.234.567,89
     *
     * @param float|int|string|null $amount
     * @param int $decimals
     * @return string
     */
    function formatCurrency($amount, int $decimals = 2): string
    {
        return 'Rp ' . formatAmount($amount, $decimals);
    }
}

if (!function_exists('formatCurrencyNoDecimal')) {
    /**
     * Format currency tanpa desimal
     * Format: Rp 1.234.567
     *
     * @param float|int|string|null $amount
     * @return string
     */
    function formatCurrencyNoDecimal($amount): string
    {
        return 'Rp ' . formatAmountNoDecimal($amount);
    }
}

if (!function_exists('userHasPermission')) {
    /**
     * Check if the authenticated user has a specific permission
     * Type-safe wrapper for Spatie Laravel Permission hasPermissionTo method
     *
     * @param string $permission
     * @return bool
     */
    function userHasPermission(string $permission): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return $user && $user->hasPermissionTo($permission);
    }
}
