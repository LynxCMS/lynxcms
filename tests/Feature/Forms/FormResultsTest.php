<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Agents\SummarizeFormTool;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Results\FormResults;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ModuleReadTool;

/*
 * A form's answers summed up per question: the survey results staff read in the admin,
 * and agents with summarize_form.
 */

final class ResultsSurvey extends Form
{
    public function key(): string
    {
        return 'results-survey';
    }

    public function label(): array
    {
        return ['default' => 'Course survey', 'pt' => 'Inquérito'];
    }

    public function fields(): array
    {
        return [
            FormField::radio('pace')->label(['default' => 'Pace', 'pt' => 'Ritmo'])->options(['slow' => ['default' => 'Too slow', 'pt' => 'Lento'], 'right' => ['default' => 'Just right', 'pt' => 'Certo'], 'fast' => ['default' => 'Too fast', 'pt' => 'Rápido']]),
            FormField::checkboxes('liked')->label('Liked')->options(['teacher' => 'Teacher', 'studio' => 'Studio']),
            FormField::number('score')->label('Score'),
            FormField::textarea('comments')->label('Comments'),
        ];
    }
}

beforeEach(function (): void {
    courseSite();
    lynx()->forms()->register(ResultsSurvey::class);

    foreach ([['right', ['teacher'], 9, 'Loved it'], ['right', ['teacher', 'studio'], 8, null], ['fast', [], 5, 'Too much at once']] as [$pace, $liked, $score, $comments]) {
        lynx()->run(SubmitForm::class, ['form' => 'results-survey', 'data' => array_filter(['pace' => $pace, 'liked' => $liked, 'score' => $score, 'comments' => $comments])]);
    }
});

/** @return array<string, array<string, mixed>> */
function resultsByQuestion(): array
{
    return collect(app(FormResults::class)->summarize(lynx()->forms()->get('results-survey'))['questions'])->keyBy('name')->all();
}

it('counts each option, in the form\'s order, and how many answered', function (): void {
    $pace = resultsByQuestion()['pace'];

    expect($pace['answered'])->toBe(3)
        ->and(array_column($pace['options'], 'count', 'value'))->toBe(['slow' => 0, 'right' => 2, 'fast' => 1])
        ->and(array_column(resultsByQuestion()['liked']['options'], 'count', 'value'))->toBe(['teacher' => 2, 'studio' => 1]);
});

it('averages numbers and lists the latest written answers first', function (): void {
    expect(resultsByQuestion()['score'])->toMatchArray(['average' => 7.3, 'lowest' => 5.0, 'highest' => 9.0])
        ->and(resultsByQuestion()['comments']['latest'])->toBe(['Too much at once', 'Loved it']);
});

it('leaves spam out', function (): void {
    lynx()->run(UpdateSubmission::class, ['submission' => Submission::query()->where('data->pace', 'fast')->value('uuid'), 'status' => 'spam']);

    expect(app(FormResults::class)->summarize(lynx()->forms()->get('results-survey'))['responses'])->toBe(2);
});

it('reads labels in the current language', function (): void {
    expect(lynx()->language('pt', fn (): array => array_column(resultsByQuestion()['pace']['options'], 'label')))->toBe(['Lento', 'Certo', 'Rápido']);
});

it('shows the results in the admin, from the forms list', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get(route('lynx.admin.forms.results', 'results-survey'))->assertOk()
        ->assertSee('3 responses, spam left out')->assertSeeInOrder(['Pace', 'Just right', '2 · 67%', 'Score', 'Average', '7.3']);
    $this->get(route('lynx.admin.forms.results', 'nope'))->assertNotFound();
});

it('gives agents the results with summarize_form', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    LynxServer::tool(new ModuleReadTool(SummarizeFormTool::class), ['form' => 'results-survey'])->assertOk()->assertSee('Just right')->assertSee('7.3');
});

it('sums up the answers sent from one page, such as one course\'s survey', function (): void {
    $course = lynx()->pages->get('/courses/');
    lynx()->run(SubmitForm::class, ['form' => 'results-survey', 'page' => (string) $course->id, 'data' => ['pace' => 'slow']]);

    $results = app(FormResults::class)->summarize(lynx()->forms()->get('results-survey'), $course->id);

    expect($results['responses'])->toBe(1)
        ->and(array_column($results['questions'][0]['options'], 'count', 'value'))->toBe(['slow' => 1, 'right' => 0, 'fast' => 0])
        ->and(app(FormResults::class)->pages(lynx()->forms()->get('results-survey')))->toBe([$course->id => 1]);
});
