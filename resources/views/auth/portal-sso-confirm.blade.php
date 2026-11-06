<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Switch account · {{ config('app.name', 'Pterodactyl') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0d0f15; color: #e7e9ef;
            font-family: "Inter", "Segoe UI", system-ui, sans-serif; }
        main { width: min(420px, calc(100% - 32px)); padding: 28px; background: #161a24; border: 1px solid #272d3d; border-radius: 12px; }
        h1 { margin: 0 0 12px; font-size: 1.3rem; }
        p { color: #b4bac7; line-height: 1.5; }
        button { width: 100%; padding: 11px 16px; margin-top: 8px; border: 0; border-radius: 8px; background: #7c3aed; color: #fff;
            font: inherit; font-weight: 600; cursor: pointer; }
        button:focus-visible, a:focus-visible { outline: 3px solid #c4b5fd; outline-offset: 2px; }
        a { display: block; margin-top: 14px; text-align: center; color: #c4b5fd; }
    </style>
</head>
<body>
<main>
    <h1>Switch account?</h1>
    <p>This browser is signed in to the panel as <strong>{{ $current->username }}</strong>.
        The link from the portal signs in as <strong>{{ $target->username }}</strong> ({{ $target->email }}).</p>
    <p>Only continue if you opened this link yourself from the portal.</p>
    <form method="POST" action="{{ route('auth.portal-sso.confirm') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button type="submit">Continue as {{ $target->username }}</button>
    </form>
    <a href="/">Stay signed in as {{ $current->username }}</a>
</main>
</body>
</html>
