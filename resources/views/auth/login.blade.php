<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to admin login</title>
    {{-- Instant client-side redirect as a fallback. The canonical redirect is handled
         by the /login route which forwards to Filament's admin login. --}}
    <meta http-equiv="refresh" content="0;url={{ route('filament.admin.auth.login') }}">
</head>
<body>
    <p>If you are not redirected automatically, <a href="{{ route('filament.admin.auth.login') }}">click here to open the admin login</a>.</p>
</body>
</html>
        </div>
    </form>

    <p class="muted" style="margin-top:1rem">This is a minimal login page placeholder used during development. If you want the real Filament admin login, we can remove this file and let Filament register its native routes.</p>
</div>
</body>
</html>
