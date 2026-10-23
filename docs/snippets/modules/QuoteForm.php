<?php

declare(strict_types=1);

namespace App\Forms;

use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormStep;

// #region form
final class QuoteForm extends Form
{
    public function key(): string
    {
        return 'quote';
    }

    public function label(): string|array
    {
        return ['en' => 'Quote request', 'pt' => 'Pedido de orçamento'];
    }

    public function fields(): array
    {
        return [
            FormField::select('type')->label(['en' => 'Type', 'pt' => 'Tipo'])->required()
                ->options(['film' => ['en' => 'Film', 'pt' => 'Filme'], 'live' => ['en' => 'Livestream', 'pt' => 'Direto']]),
            FormField::number('cameras')->label(['en' => 'Cameras', 'pt' => 'Câmaras'])->required()->showIf('type', 'live')
                ->rules(['integer', 'min:1', 'max:12']),
            FormField::group('deliverables')->label(['en' => 'Deliverables', 'pt' => 'Entregas'])->fields([
                FormField::text('format')->label(['en' => 'Format', 'pt' => 'Formato'])->required(),
            ]),
            FormField::file('brief')->label(['en' => 'Brief', 'pt' => 'Briefing'])->extensions(['pdf', 'jpg'])->maxKb(10240)->maxFiles(3),
            FormField::email('email')->label('Email')->required()->replyTo(),
            FormField::checkbox('consent')->label(['en' => 'I agree to be contacted', 'pt' => 'Aceito ser contactado'])->required(),
        ];
    }

    public function steps(): array
    {
        return [
            new FormStep('project', ['en' => 'Project', 'pt' => 'Projeto'], ['type', 'cameras', 'deliverables', 'brief']),
            new FormStep('contact', ['en' => 'Contact', 'pt' => 'Contacto'], ['email', 'consent']),
        ];
    }
}
// #endregion form
