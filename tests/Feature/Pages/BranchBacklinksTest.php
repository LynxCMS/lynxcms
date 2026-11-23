<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    $this->courses = createCourses('alpha', 'beta', 'gamma', 'delta');
    $lesson = lynx()->pages->new('course', '/courses/alpha/');
    $lesson->name = 'lesson';
    $lesson->title = 'Lesson';
    $lesson->save();
    $this->lesson = $lesson;
});

function linkTo(Page $from, Page ...$targets): void
{
    $from->related = array_map(fn (Page $page): int => (int) $page->id, $targets);
    $from->save();
}

/**
 * @return list<int>
 */
function referencedBy(ActionResult $result): array
{
    foreach ($result->changes->all() as $change) {
        if (isset($change->data['referenced_by'])) {
            return $change->data['referenced_by'];
        }
    }

    return [];
}

it('lists the pages outside the branch that reference a page in it when trashing', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    linkTo($beta, $alpha);
    linkTo($gamma, $this->lesson);

    $result = lynx()->pages->trash($alpha, dryRun: true);

    expect(referencedBy($result))->toBe([(int) $beta->id, (int) $gamma->id]);
});

it('leaves out references from inside the branch and from the trash', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    linkTo($this->lesson, $alpha);
    linkTo($beta, $alpha);
    lynx()->pages->trash($beta);

    expect(referencedBy(lynx()->pages->trash($alpha, dryRun: true)))->toBe([]);
});

it('records the references in the change once the page is trashed', function (): void {
    ['alpha' => $alpha, 'delta' => $delta] = $this->courses;
    linkTo($delta, $alpha);

    $result = lynx()->run(TrashPage::class, ['page' => (int) $alpha->id]);

    expect(referencedBy($result))->toBe([(int) $delta->id]);
});

it('lists the references when deleting a page for good', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;
    linkTo($beta, $this->lesson);

    expect(referencedBy(lynx()->pages->delete($alpha, dryRun: true)))->toBe([(int) $beta->id]);
});

it('finds the references of a large branch', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = $this->courses;

    foreach (range(1, 120) as $number) {
        $page = lynx()->pages->new('course', '/courses/alpha/');
        $page->name = 'part-'.$number;
        $page->title = 'Part '.$number;
        $page->save();
    }

    linkTo($beta, lynx()->pages->get('/courses/alpha/part-120/'));

    expect(referencedBy(lynx()->pages->trash($alpha, dryRun: true)))->toBe([(int) $beta->id]);
});

it('finds the pages that reference any of several pages', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma, 'delta' => $delta] = $this->courses;
    linkTo($gamma, $alpha);
    linkTo($delta, $beta);

    expect(lynx()->pages->backlinks([$alpha, $beta->id])->ids())->toBe([(int) $gamma->id, (int) $delta->id]);
});

it('treats each page as a branch when asked', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = $this->courses;
    linkTo($beta, $this->lesson);
    linkTo($this->lesson, $alpha);
    linkTo($gamma, $alpha);
    lynx()->pages->trash($gamma);

    expect(lynx()->pages->backlinks($alpha)->ids())->toBe([(int) $gamma->id, (int) $this->lesson->id])
        ->and(lynx()->pages->backlinks($alpha, branch: true)->ids())->toBe([(int) $beta->id]);
});

it('counts every referencing page past the limit', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma, 'delta' => $delta] = $this->courses;
    linkTo($beta, $alpha);
    linkTo($gamma, $alpha);
    linkTo($delta, $alpha);

    $linking = lynx()->pages->backlinks($alpha, limit: 2, branch: true);

    expect($linking->count())->toBe(2)->and($linking->total())->toBe(3);
});
