{{--
    The admin's notification bar: full width, floating at the bottom of the viewport. Green for
    success, yellow for warnings, red for errors; click one to close it before it times out.
    Livewire components send them with notify() (Livewire\Concerns\Notifies, event "lynx-notify");
    BlatUI's window.toast() events show here too.
--}}
<div
    x-data="{
        notices: [],
        durations: { success: 4000, warning: 6000, error: 9000 },
        add(message, type) {
            if (! message) return;
            type = this.durations[type] ? type : 'success';
            const id = Date.now() + Math.random();
            this.notices.push({ id, message, type });
            setTimeout(() => this.remove(id), this.durations[type]);
        },
        fromToast(t) {
            this.add(t.description || t.title || '', t.type === 'error' ? 'error' : (t.type === 'warning' ? 'warning' : 'success'));
        },
        remove(id) { this.notices = this.notices.filter(n => n.id !== id); },
    }"
    @lynx-notify.window="add($event.detail.message, $event.detail.type)"
    @toast.window="fromToast($event.detail)"
    role="region"
    aria-label="Notifications"
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
            <span class="sr-only">Close</span>
        </button>
    </template>
</div>
