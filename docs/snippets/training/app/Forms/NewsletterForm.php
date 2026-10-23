<?php

declare(strict_types=1);

namespace App\Forms;

use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;

// #region form
/** The footer's newsletter sign-up: an address and consent, nothing more. */
final class NewsletterForm extends Form
{
    public function key(): string
    {
        return 'newsletter';
    }

    public function label(): array
    {
        return ['en' => 'Newsletter', 'pt' => 'Newsletter'];
    }

    public function fields(): array
    {
        return [
            FormField::email('email')->label('Email')->required(),
            FormField::checkbox('consent')->label(['en' => 'Send me news and new dates, about once a month.', 'pt' => 'Enviem-me novidades e novas datas, cerca de uma vez por mês.'])->required(),
        ];
    }

    public function successMessage(): array
    {
        return ['en' => 'You are on the list. See you soon!', 'pt' => 'Está na lista. Até breve!'];
    }

    /** Sign-ups go to the mailing service, not to anyone's inbox. */
    public function recipients(): array
    {
        return [];
    }
}
// #endregion form
