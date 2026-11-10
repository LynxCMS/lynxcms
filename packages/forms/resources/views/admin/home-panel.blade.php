{{-- The admin home screen's panel of new form submissions (FormsHomePanel). --}}
<x-lynx-admin::home.section id="home-forms" data-home-panel="forms" icon="inbox" :href="route('lynx.admin.forms')"
    :title="__('lynx-forms::admin.home.title')" :count="$total"
    :see-all-url="$total > \LynxCms\Forms\Admin\FormsHomePanel::LIMIT ? route('lynx.admin.forms') : null"
    :see-all-label="__('lynx-forms::admin.home.see_all', ['count' => $total])"
    :is-empty="$submissions === []" empty-icon="inbox"
    :empty-title="__('lynx-forms::admin.home.empty')" :empty-help="__('lynx-forms::admin.home.empty_help')">
    @foreach ($submissions as $submission)
        <x-lynx-admin::home.row :url="$submission['url']" :title="$submission['summary']" data-home-submission>
            {{ $submission['form'] }}
            <x-slot:aside><span title="{{ $submission['at'] }}">{{ $submission['date'] }}</span></x-slot:aside>
        </x-lynx-admin::home.row>
    @endforeach
</x-lynx-admin::home.section>
