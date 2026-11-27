<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\StoredInsight;

/**
 * Dismiss an open insight. It is not reported again for the quiet days, unless its weight doubles. Nothing on the site changes.
 *
 * @api
 */
final class DismissInsight extends Action
{
    use ResolvesInsights;

    public static function name(): string
    {
        return 'insights.dismiss';
    }

    public static function description(): string
    {
        return 'Dismiss an open insight. It is not reported again for the quiet days, unless its weight doubles. Nothing on the site changes.';
    }

    public static function permission(): string
    {
        return 'insights.review';
    }

    public static function rules(): array
    {
        return self::insightRules();
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['insight' => $schema->integer()->description('The id of the insight.')->required()];
    }

    public function handle(Input $input, ActionContext $context): StoredInsight
    {
        return $this->decide($this->openInsight($input->get('insight')), InsightState::Dismissed, $context);
    }
}
