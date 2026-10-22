{{-- The password form of a locked page. Publish it (lynx-page-password-views) to match the site. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('lynx-page-password::form.title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.5 system-ui, sans-serif; background: #f7f7f5; color: #1c1c1a; }
        form { display: grid; gap: .75rem; width: min(22rem, calc(100vw - 2rem)); }
        h1 { margin: 0; font-size: 1.4rem; }
        p { margin: 0; color: #55554f; }
        input { font: inherit; padding: .6rem .75rem; border: 1px solid #c9c9c2; border-radius: .4rem; }
        button { font: inherit; padding: .6rem .75rem; border: 0; border-radius: .4rem; background: #1c1c1a; color: #fff; cursor: pointer; }
        [role=alert] { color: #b42318; }
        @media (prefers-color-scheme: dark) { body { background: #161615; color: #ededea; } p { color: #a6a6a0; } input { background: #1f1f1d; color: inherit; border-color: #3a3a36; } button { background: #ededea; color: #161615; } [role=alert] { color: #f97066; } }
    </style>
</head>
<body>
    <form method="POST" action="{{ route('lynx.page-password.unlock') }}">
        @csrf
        <input type="hidden" name="page" value="{{ $page->id }}">
        <input type="hidden" name="language" value="{{ $language }}">
        <h1>{{ __('lynx-page-password::form.title') }}</h1>
        <p>{{ __('lynx-page-password::form.intro') }}</p>
        <label for="lynx-page-password">{{ __('lynx-page-password::form.password') }}</label>
        <input id="lynx-page-password" type="password" name="password" autocomplete="current-password" required autofocus @error('password') aria-invalid="true" aria-describedby="lynx-page-password-error" @enderror>
        @error('password')<p id="lynx-page-password-error" role="alert">{{ $message }}</p>@enderror
        <button type="submit">{{ __('lynx-page-password::form.open') }}</button>
    </form>
</body>
</html>
