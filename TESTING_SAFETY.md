# Laravel Testing Safety

Automated tests must never run against the local application database.

## Required test database

Create and use a dedicated MySQL database for tests:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS duta_tunggal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

The main local database is `duta_tunggal`. Do not use it for automated tests.

## Safe commands

Use the safe Composer scripts so Laravel config cache is cleared before PHPUnit starts:

```bash
composer test:safe -- tests\Feature\PurchaseOrderTotalCalculationTest.php
composer test:unit-safe -- tests\Unit\PurchaseOrderItemNavigatorTest.php
```

`tests/TestCase.php` contains a hard guard: when `APP_ENV=testing`, tests abort unless the active database name ends with `_test`.

## Browser/manual testing

Manual browser checks may use the local application database `duta_tunggal`.
Automated Feature tests that use `RefreshDatabase` must use `duta_tunggal_test`.
