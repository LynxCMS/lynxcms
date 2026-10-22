<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use LynxCms\Core\Models\Template;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoSchema;

/**
 * Templates that had SEO turned on before the SEO tab get it: their SEO fields still in Content
 * move there, through seo.enable_template. Fields an editor placed elsewhere stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (lynx()->templates->all() as $template) {
            if ($this->hasSeoFields($template) && $template->hasUrl()) {
                lynx()->run(EnableSeo::class, ['template' => $template->name]);
            }
        }
    }

    public function down(): void
    {
        // Moving fields back to Content would undo editors' work too; the tab stays.
    }

    private function hasSeoFields(Template $template): bool
    {
        return array_intersect(SeoSchema::pageFields(), lynx()->templates->fields($template)->keys()->all()) !== [];
    }
};
