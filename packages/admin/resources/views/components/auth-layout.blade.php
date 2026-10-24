@props(['title', 'description' => null])
{{-- The sign-in screens: one centred card, no sidebar, on the site's backdrop with its logo. --}}
@php($branding = \LynxCms\Admin\Branding\Branding::current())
<x-lynx-admin::document :title="$title">
<body class="bg-muted/40 flex min-h-screen items-center justify-center p-4 font-sans antialiased" @if ($branding->backgroundStyle() !== '') style="{{ $branding->backgroundStyle() }}" @endif>
    <main class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-2">
            <x-lynx-admin::brand :branding="$branding" size="lg" />
        </div>
        <x-lynx-admin::ui.card class="grid gap-6">
            <div class="grid gap-1 text-center">
                <h1 class="text-xl font-semibold">{{ $title }}</h1>
                @if ($description)
                    <p class="text-muted-foreground text-sm">{{ $description }}</p>
                @endif
            </div>
            @if (session('status'))
                <p role="status" class="text-center text-sm font-medium">{{ session('status') }}</p>
            @endif
            {{ $slot }}
        </x-lynx-admin::ui.card>
    </main>
    <script src="{{ route('lynx.admin.assets.js', ['v' => \LynxCms\Admin\Http\AssetController::scriptVersion()], false) }}"></script>
    @livewireScripts
</body>
</x-lynx-admin::document>
