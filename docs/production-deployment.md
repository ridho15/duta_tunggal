# Production Deployment

Production must use the following application settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

After updating the production environment, rebuild Laravel's cached
configuration so workers and web requests use the same values:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Restart PHP-FPM, queue workers, and long-running application processes after
the cache is rebuilt. Verify the deployment by opening a controlled failing
route or request and confirming that the response does not expose SQL, local
paths, environment values, or a stack trace. Full exception details must only
be available in the server logs.
