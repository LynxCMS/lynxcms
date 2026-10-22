<?php

declare(strict_types=1);

namespace LynxCms\Cache\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Cache\Fragments\FragmentClearer;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\PageCacheClearer;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Drops stored pages so the next visits render them again: every page, some pages, or the pages
 * of one template; or stored fragments, all or by name. Content changes clear on their own; this is for changes Lynx cannot see, such
 * as a deploy that changed Blade files.
 */
final class ClearPageCache extends Action
{
    public function __construct(private readonly PageCacheClearer $clearer, private readonly FragmentClearer $fragments) {}

    public static function name(): string
    {
        return 'page_cache.clear';
    }

    public static function description(): string
    {
        return 'Drop stored pages from the page cache: every page, the given page ids, or the pages of one template; or stored fragments: all, or one name. Content changes already clear what they change; use it after a deploy or a config change.';
    }

    public static function permission(): string
    {
        return 'page_cache.manage';
    }

    public static function rules(): array
    {
        return [
            'pages' => ['nullable', 'array', 'prohibits:template'],
            'pages.*' => ['integer'],
            'template' => ['nullable', 'string', Rule::exists(Tables::TEMPLATES, 'name')],
            'fragments' => ['nullable', 'boolean', 'prohibits:pages,template,fragment'],
            'fragment' => ['nullable', 'string', 'prohibits:pages,template'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'pages' => $schema->array()->items($schema->integer())->description('Page ids to clear. Leave out pages and template to clear every page.'),
            'template' => $schema->string()->description('Clear the pages of this template.'),
            'fragments' => $schema->boolean()->description('Clear every stored fragment (lynx()->cache(), @lynxCache) instead of pages.'),
            'fragment' => $schema->string()->description('Clear the stored fragments with this name, in every language, instead of pages.'),
        ];
    }

    /** @return int how many stored pages or fragments were (or would be) cleared */
    public function handle(Input $input, ActionContext $context): int
    {
        $fragment = Value::nullableString($input->get('fragment'));

        if (Value::bool($input->get('fragments')) || $fragment !== null) {
            return $this->clearFragments($fragment, $context);
        }

        $pages = Value::ints($input->get('pages'));
        $template = Value::nullableString($input->get('template'));
        $entries = PageCacheEntry::query()
            ->when($pages !== [], fn ($query) => $query->whereIn('page_id', $pages))
            ->when($template !== null, fn ($query) => $query->where('template', $template));
        $count = $entries->count();
        $context->changes->updated('page_cache', null, $this->summary($count, $pages, $template));

        if (! $context->dryRun) {
            match (true) {
                $pages !== [] => $this->clearer->clearPages($pages),
                $template !== null => $this->clearer->clearTemplate($template),
                default => $this->clearer->clearAll(),
            };
        }

        return $count;
    }

    private function clearFragments(?string $name, ActionContext $context): int
    {
        $count = FragmentEntry::query()->when($name !== null, fn ($query) => $query->where('name', $name))->count();
        $what = $name === null ? 'in all' : "named {$name}";
        $context->changes->updated('fragment_cache', null, "Fragment cache cleared: {$count} ".($count === 1 ? 'fragment' : 'fragments')." {$what}");

        if (! $context->dryRun) {
            $name === null ? $this->fragments->clearAll() : $this->fragments->clearName($name);
        }

        return $count;
    }

    /** @param list<int> $pages */
    private function summary(int $count, array $pages, ?string $template): string
    {
        $what = match (true) {
            $pages !== [] => 'of '.count($pages).' '.(count($pages) === 1 ? 'page' : 'pages'),
            $template !== null => "of template {$template}",
            default => 'in all',
        };

        return "Page cache cleared: {$count} stored ".($count === 1 ? 'page' : 'pages')." {$what}";
    }
}
