<!DOCTYPE html>
<html>
    <head>
        <title>{{ ($title ?? '') . ' - ' . config('app.name') }}</title>
    </head>
    <body>
        {{ $slot }}
    </body>
</html>
