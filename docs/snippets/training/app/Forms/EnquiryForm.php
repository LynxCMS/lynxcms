<?php

declare(strict_types=1);

namespace App\Forms;

use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;

// #region form
/**
 * Questions about a course, from the course's page or the contact page. The page it was sent from
 * is stored with the submission, so staff (and the CRM) know which course it is about.
 */
final class EnquiryForm extends Form
{
    public function key(): string
    {
        return 'enquiry';
    }

    public function label(): array
    {
        return ['en' => 'Course enquiry', 'pt' => 'Pedido de informação'];
    }

    public function fields(): array
    {
        return [
            FormField::text('name')->label(['en' => 'Your name', 'pt' => 'O seu nome'])->required()->max(120)->width(50),
            FormField::email('email')->label('Email')->required()->replyTo()->width(50),
            FormField::tel('phone')->label(['en' => 'Phone (optional)', 'pt' => 'Telefone (opcional)'])->width(50),
            FormField::select('goal')->label(['en' => 'You want to', 'pt' => 'Quer'])->width(50)->options([
                'teach' => ['en' => 'Teach professionally', 'pt' => 'Ensinar profissionalmente'],
                'learn' => ['en' => 'Deepen my own practice', 'pt' => 'Aprofundar a minha prática'],
            ]),
            FormField::textarea('message')->label(['en' => 'Your question', 'pt' => 'A sua pergunta'])->required()->max(2000),
            FormField::checkbox('consent')->label(['en' => 'Atlas Training may contact me about this enquiry.', 'pt' => 'A Atlas Training pode contactar-me sobre este pedido.'])->required(),
        ];
    }

    public function successMessage(): array
    {
        return ['en' => 'Thank you! We reply within one working day.', 'pt' => 'Obrigado! Respondemos num dia útil.'];
    }

    /** The front desk, rather than everyone who may see submissions. */
    public function recipients(): array
    {
        return [(string) config('atlas.front_desk', 'hello@atlas-training.example')];
    }
}
// #endregion form
