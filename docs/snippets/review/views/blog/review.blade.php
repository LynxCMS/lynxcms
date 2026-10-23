{{-- The Review screen: every post with something to fix, linked to its editor. --}}
<x-lynx-admin::layout title="Review" :crumbs="['Pages' => route('lynx.admin.pages')]">
    <x-lynx-admin::ui.page-header title="Review" description="Posts with something to fix before they go out." />

    @forelse (\App\Blog\PostChecks::queue() as ['post' => $post, 'problems' => $problems])
        <section class="bg-card grid gap-2 rounded-xl border p-4">
            <a href="{{ route('lynx.admin.pages.edit', [$post->id, 'tab' => 'checklist']) }}" class="font-medium">{{ $post->title }}</a>
            <ul class="text-muted-foreground list-disc pl-5 text-sm">
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-muted-foreground text-sm">Every post is ready.</p>
    @endforelse
</x-lynx-admin::layout>
