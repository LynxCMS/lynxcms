<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\ArchiveInsight;
use LynxCms\Insights\Actions\DismissInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * insights.approve runs an insight's plan as the person approving (ADR-0155, ADR-0157): their
 * permissions apply to every step, a page changed since the report stops it, and its review (a
 * dry run) refuses a plan that deletes anything. ApprovalQueueTest covers the steps' jobs.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha] = createCourses('alpha');
    $this->source = new FixedSource('fixed', [
        new Insight('Price alpha', 'Popular, and free.', pages: [$this->alpha->id], plan: [
            ['action' => 'pages.save', 'arguments' => ['id' => $this->alpha->id, 'values' => ['price' => 40]]],
        ]),
    ]);
    // Only the test's source: the module's own would report the course pages too.
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add($this->source);
});

function reportedInsight(): StoredInsight
{
    return lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->firstOrFail();
}

it('runs the plan and marks the insight approved', function (): void {
    $insight = reportedInsight();
    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    expect(lynx()->pages->get($this->alpha->id)?->price)->toBe(40)
        ->and($insight->fresh()?->state)->toBe(InsightState::Approved)
        ->and($insight->fresh()?->decided_at)->not->toBeNull();
});

it('accepts an action by its class', function (): void {
    $this->source->insights = [new Insight('Price alpha', 'Popular.', pages: [$this->alpha->id], plan: [
        ['action' => SavePage::class, 'arguments' => ['id' => $this->alpha->id, 'values' => ['price' => 25]]],
    ])];
    lynx()->run(ApproveInsight::class, ['insight' => reportedInsight()->id]);

    expect(lynx()->pages->get($this->alpha->id)?->price)->toBe(25);
});

it('changes nothing on a dry run', function (): void {
    $insight = reportedInsight();
    $result = lynx()->run(ApproveInsight::class, ['insight' => $insight->id], dryRun: true);

    expect(lynx()->pages->get($this->alpha->id)?->price)->toBeNull()
        ->and($insight->fresh()?->state)->toBe(InsightState::Open)
        ->and($result->changes->count())->toBeGreaterThan(0);
});

it('refuses when a page changed since the report', function (): void {
    $insight = reportedInsight();
    $this->travel(1)->minutes();
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['summary' => 'New summary']]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);
})->throws(ValidationException::class);

it('refuses an insight that is no longer open', function (): void {
    $insight = reportedInsight();
    lynx()->run(DismissInsight::class, ['insight' => $insight->id]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);
})->throws(ValidationException::class);

it('refuses an insight without a plan', function (): void {
    $this->source->insights = [new Insight('Read alpha', 'Just so you know.', pages: [$this->alpha->id])];

    lynx()->run(ApproveInsight::class, ['insight' => reportedInsight()->id]);
})->throws(ValidationException::class);

it('runs each step with the approver\'s permissions', function (): void {
    $insight = reportedInsight();
    lynx()->run(SaveRole::class, ['name' => 'reviewer', 'grants' => [['permission' => 'insights.review'], ['permission' => 'insights.view'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['reviewer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    expect(fn (): ActionResult => lynx()->run(ApproveInsight::class, ['insight' => $insight->id]))->toThrow(AuthorizationException::class)
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBeNull()
        ->and($insight->fresh()?->state)->toBe(InsightState::Open);
});

it('refuses a plan that deletes when reviewed', function (): void {
    $this->source->insights = [new Insight('Remove alpha', 'Nobody reads it.', pages: [$this->alpha->id], plan: [
        ['action' => 'pages.delete', 'arguments' => ['page' => $this->alpha->id]],
    ])];
    $insight = reportedInsight();

    expect(fn (): ActionResult => lynx()->run(ApproveInsight::class, ['insight' => $insight->id], dryRun: true))->toThrow(ValidationException::class)
        ->and(lynx()->pages->get($this->alpha->id))->not->toBeNull();
});

it('dismisses and archives open insights', function (string $action, InsightState $state): void {
    $insight = reportedInsight();
    lynx()->run($action, ['insight' => $insight->id]);

    expect($insight->fresh()?->state)->toBe($state);
})->with([
    'dismiss' => [DismissInsight::class, InsightState::Dismissed],
    'archive' => [ArchiveInsight::class, InsightState::Archived],
]);
