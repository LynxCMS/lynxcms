<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;

/*
 * A date question takes a date as a date input sends it (2026-12-01): results compare dates as
 * text, so "tomorrow" or "12/01/2026" would sort wrongly.
 */

final class VisitForm extends Form
{
    public function key(): string
    {
        return 'visit';
    }

    public function label(): string
    {
        return 'Visit';
    }

    public function fields(): array
    {
        return [FormField::date('day')->label('Day')->required()];
    }
}

beforeEach(function (): void {
    courseSite();
    lynx()->forms()->register(VisitForm::class);
});

function visitOn(string $day): ActionResult
{
    return lynx()->run(SubmitForm::class, ['form' => 'visit', 'language' => 'default', 'data' => ['day' => $day]]);
}

it('takes a date as a date input sends it', function (): void {
    expect(visitOn('2026-12-01')->value)->not->toBeNull();
});

it('refuses other ways of writing a date', function (string $day): void {
    expect(fn (): ActionResult => visitOn($day))->toThrow(ValidationException::class);
})->with(['tomorrow', '12/01/2026', '2026-02-30']);
