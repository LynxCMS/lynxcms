<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Forms;

use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormStep;

/** A quote request with steps, a conditional field, a repeating group and files, for tests. */
class QuoteForm extends Form
{
    public function key(): string
    {
        return 'quote';
    }

    public function label(): string|array
    {
        return ['default' => 'Quote request', 'pt' => 'Pedido de orçamento'];
    }

    public function fields(): array
    {
        return [
            FormField::select('type')->label(['default' => 'Project type', 'pt' => 'Tipo de projeto'])->required()
                ->options(['film' => ['default' => 'Film', 'pt' => 'Filme'], 'live' => ['default' => 'Livestream', 'pt' => 'Transmissão']]),
            FormField::number('cameras')->label(['default' => 'Cameras', 'pt' => 'Câmaras'])->required()->showIf('type', 'live')->rules(['integer', 'min:1', 'max:12']),
            FormField::group('deliverables')->label(['default' => 'Deliverables', 'pt' => 'Entregas'])->maxRows(5)->fields([
                FormField::text('format')->label(['default' => 'Format', 'pt' => 'Formato'])->required()->max(60),
                FormField::number('minutes')->label(['default' => 'Minutes', 'pt' => 'Minutos'])->rules(['integer', 'min:1']),
            ]),
            FormField::file('brief')->label(['default' => 'Brief', 'pt' => 'Briefing'])->extensions(['pdf', 'jpg'])->maxKb(2048)->maxFiles(2),
            FormField::text('name')->label(['default' => 'Name', 'pt' => 'Nome'])->required()->max(120),
            FormField::email('email')->label(['default' => 'Email', 'pt' => 'Email'])->required()->replyTo(),
            FormField::checkbox('consent')->label(['default' => 'I agree to be contacted', 'pt' => 'Aceito ser contactado'])->required()
                ->messages(['accepted' => ['default' => 'Please agree so we can reply.', 'pt' => 'Aceite para podermos responder.']]),
        ];
    }

    public function steps(): array
    {
        return [
            new FormStep('project', ['default' => 'Project', 'pt' => 'Projeto'], ['type', 'cameras', 'deliverables', 'brief']),
            new FormStep('contact', ['default' => 'Contact', 'pt' => 'Contacto'], ['name', 'email', 'consent']),
        ];
    }

    public function successMessage(): string|array
    {
        return ['default' => 'Thank you, we will reply soon.', 'pt' => 'Obrigado, respondemos em breve.'];
    }

    public function autoReply(): string|array|null
    {
        return ['default' => 'We received your request.', 'pt' => 'Recebemos o seu pedido.'];
    }
}
