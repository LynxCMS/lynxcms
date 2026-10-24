@props(['title' => 'Lynx', 'crumbs' => [], 'viewUrl' => null])
{{-- crumbs: screens above this one, label => URL, shown before the title in the top bar.
     viewUrl: the front-end page this screen edits; the top bar's view link opens it instead of the site. --}}
<x-lynx-admin::document :title="$title">
<body class="min-h-screen font-sans antialiased">
    {{-- Open or collapsed as the person left it, from the first paint: no flash between pages. --}}
    <x-lynx-admin::ui.sidebar-provider :default-open="request()->cookie(\LynxCms\Admin\AdminServiceProvider::SIDEBAR_COOKIE) !== 'collapsed'" :cookie="\LynxCms\Admin\AdminServiceProvider::SIDEBAR_COOKIE">
        <x-lynx-admin::ui.sidebar collapsible="icon">
            <x-lynx-admin::ui.sidebar-header>
                <a href="{{ route('lynx.admin.pages') }}" class="flex items-center gap-2 rounded-md p-1.5 font-semibold tracking-tight outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring">
                    <span class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-md text-sm" aria-hidden="true">L</span>
                    <span class="truncate group-data-[collapsible=icon]:hidden">Lynx</span>
                </a>
            </x-lynx-admin::ui.sidebar-header>
            <x-lynx-admin::ui.sidebar-content>
                <x-lynx-admin::ui.sidebar-group>
                    <x-lynx-admin::ui.sidebar-menu>
                        <x-lynx-admin::navigation />
                    </x-lynx-admin::ui.sidebar-menu>
                </x-lynx-admin::ui.sidebar-group>
            </x-lynx-admin::ui.sidebar-content>
            @auth
                <x-lynx-admin::ui.sidebar-footer>
                    <x-lynx-admin::ui.sidebar-menu>
                        <x-lynx-admin::ui.sidebar-menu-item>
                            <x-lynx-admin::user-menu />
                        </x-lynx-admin::ui.sidebar-menu-item>
                        @if (Route::has('logout'))
                            <x-lynx-admin::ui.sidebar-menu-item>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-lynx-admin::ui.sidebar-menu-button type="submit" tooltip="Sign out">
                                        <x-lynx-admin::ui.icon name="log-out" aria-hidden="true" />
                                        <span>Sign out</span>
                                    </x-lynx-admin::ui.sidebar-menu-button>
                                </form>
                            </x-lynx-admin::ui.sidebar-menu-item>
                        @endif
                    </x-lynx-admin::ui.sidebar-menu>
                </x-lynx-admin::ui.sidebar-footer>
            @endauth
            <x-lynx-admin::ui.sidebar-rail />
        </x-lynx-admin::ui.sidebar>
        <x-lynx-admin::ui.sidebar-inset>
            <header class="bg-background/95 sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b px-4 backdrop-blur">
                <x-lynx-admin::ui.sidebar-trigger />
                <x-lynx-admin::ui.separator orientation="vertical" class="mx-1 h-4" />
                <x-lynx-admin::ui.breadcrumb>
                    <x-lynx-admin::ui.breadcrumb-list>
                        @foreach ($crumbs as $label => $url)
                            <x-lynx-admin::ui.breadcrumb-item>
                                <x-lynx-admin::ui.breadcrumb-link :href="$url">{{ $label }}</x-lynx-admin::ui.breadcrumb-link>
                            </x-lynx-admin::ui.breadcrumb-item>
                            <x-lynx-admin::ui.breadcrumb-separator />
                        @endforeach
                        <x-lynx-admin::ui.breadcrumb-item>
                            <x-lynx-admin::ui.breadcrumb-page>{{ $title }}</x-lynx-admin::ui.breadcrumb-page>
                        </x-lynx-admin::ui.breadcrumb-item>
                    </x-lynx-admin::ui.breadcrumb-list>
                </x-lynx-admin::ui.breadcrumb>
                @auth
                    <div class="ms-4 flex min-w-0 flex-1 justify-end">
                        <livewire:lynx-admin.search />
                    </div>
                    @php($viewLabel = $viewUrl === null ? 'View site' : 'View page')
                    <x-lynx-admin::ui.button variant="ghost" size="icon-sm" :href="$viewUrl ?? url('/')" target="_blank" rel="noopener" :aria-label="$viewLabel" :title="$viewLabel">
                        <x-lucide-external-link aria-hidden="true" />
                    </x-lynx-admin::ui.button>
                @endauth
            </header>
            <main class="mx-auto w-full max-w-7xl px-4 py-6 md:px-8 md:py-8">
                {{ $slot }}
            </main>
        </x-lynx-admin::ui.sidebar-inset>
    </x-lynx-admin::ui.sidebar-provider>
    <x-lynx-admin::notifications />
    @auth
        {{-- One link dialog for every rich text field on the page (LinkDialog). --}}
        <livewire:lynx-admin.link-dialog />
    @endauth
    {{-- Before Livewire: registers BlatUI and the admin's Alpine components on alpine:init. --}}
    <script src="{{ route('lynx.admin.assets.js', ['v' => \LynxCms\Admin\Http\AssetController::scriptVersion()], false) }}"></script>
    @livewireScripts
</body>
</x-lynx-admin::document>
