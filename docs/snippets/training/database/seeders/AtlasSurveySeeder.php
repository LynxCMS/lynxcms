<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\FormSchema;

/**
 * The course feedback survey, as staff would build it in Forms › Builder: a form page whose
 * blocks are its questions. Seeded so the starter has one; in practice staff make their own.
 */
final class AtlasSurveySeeder extends Seeder
{
    public function run(): void
    {
        // #region survey
        $survey = lynx()->run(CreateForm::class, ['name' => 'course-feedback', 'label' => ['en' => 'Course feedback', 'pt' => 'Avaliação do curso']])->value;

        $choice = fn (string $value, string $en, string $pt): array => ['value' => $value, 'label' => ['en' => $en, 'pt' => $pt]];
        $block = fn (string $type, array $values): array => ['type' => $type, 'values' => $values];

        lynx()->run(SavePage::class, ['id' => $survey->id, 'status' => [], 'values' => [
            FormSchema::ONCE_PER_USER => true,
            FormSchema::RECIPIENTS => '',
            FormSchema::SUCCESS => ['en' => 'Thank you! Your answers help us plan the next course.', 'pt' => 'Obrigado! As suas respostas ajudam-nos a preparar o próximo curso.'],
            FormSchema::FIELDS => [
                $block('radio', ['form_label' => ['en' => 'How would you rate the course?', 'pt' => 'Como avalia o curso?'], 'form_name' => 'rating', 'form_required' => true, 'form_options' => [
                    $choice('5', 'Excellent', 'Excelente'), $choice('4', 'Good', 'Bom'), $choice('3', 'Fair', 'Razoável'), $choice('2', 'Poor', 'Fraco'),
                ]]),
                $block('radio', ['form_label' => ['en' => 'The pace was', 'pt' => 'O ritmo foi'], 'form_name' => 'pace', 'form_options' => [
                    $choice('slow', 'Too slow', 'Demasiado lento'), $choice('right', 'Just right', 'Adequado'), $choice('fast', 'Too fast', 'Demasiado rápido'),
                ]]),
                $block('checkboxes', ['form_label' => ['en' => 'What worked best?', 'pt' => 'O que resultou melhor?'], 'form_name' => 'best', 'form_options' => [
                    $choice('teacher', 'The teaching', 'O ensino'), $choice('materials', 'The materials', 'Os materiais'), $choice('studio', 'The studio', 'O estúdio'),
                ]]),
                $block('textarea', ['form_label' => ['en' => 'Anything we should change?', 'pt' => 'Algo que devamos mudar?'], 'form_name' => 'comments', 'form_max' => 2000]),
            ],
        ]]);
        // #endregion survey
    }
}
