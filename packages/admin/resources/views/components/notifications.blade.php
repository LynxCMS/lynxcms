{{--
    The admin's notification bar: full width, floating at the bottom of the viewport. Green for
    success, yellow for warnings, red for errors; click one to close it before it times out. They
    wait while the pointer or the focus is on the bar (js/notices.js).
    Livewire components send them with notify() (Livewire\Concerns\Notifies, event "lynx-notify");
    BlatUI's window.toast() events show here too. A notice flashed to the session before a redirect
    (session key "lynx-notify", as the publish button's menu does) shows on the next screen.
--}}
@php($flashed = session('lynx-notify'))
<div
    x-data="lynxNotices"
    @mouseenter="hold()" @mouseleave="release()" @focusin="hold()" @focusout="$nextTick(() => release())"
    @lynx-notify.window="add($event.detail.message, $event.detail.type)"
    @toast.window="fromToast($event.detail)"
    @if (is_array($flashed) && is_string($flashed['message'] ?? null))
        x-init="add(@js($flashed['message']), @js($flashed['type'] ?? 'success'))"
        data-flashed-notice="{{ $flashed['type'] ?? 'success' }}"
    @endif
    role="region"
    aria-label="{{ __('lynx-admin::shell.ui.notifications') }}"
    {{-- In the page from the start, so screen readers announce the notices added to it. --}}
    aria-live="polite"
    class="lynx-notices"
>
    <template x-for="notice in notices" :key="notice.id">
        <button
            type="button"
            class="lynx-notice"
            :data-type="notice.type"
            :role="notice.type === 'success' ? 'status' : 'alert'"
            @click="remove(notice.id)"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <span x-text="notice.message"></span>
            <span class="lynx-notice-close" aria-hidden="true">×</span>
            <span class="sr-only">{{ __('lynx-admin::shell.ui.close') }}</span>
        </button>
    </template>
</div>
