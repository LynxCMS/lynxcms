---
draft: true
title: A Contact Form and Search
description: Chapter 6 of the bilingual website guide. A consultation request in two steps and two languages, kept in the admin's inbox, and a search page over the firm's pages.
---

# A Contact Form and Search

Visitors who need a lawyer should be able to say so without writing an email, and visitors looking for one topic should find it without clicking through every section. This chapter adds a consultation request to the contact page, and a search page.

## The consultation request

A form is a PHP class that lists its fields. The forms module shows it, checks the answers, keeps every submission in the admin, and emails the people who handle them. The starter's form is `app/Forms/ConsultationForm.php`, shortened here:

```php
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormStep;

final class ConsultationForm extends Form
{
    public function key(): string
    {
        return 'consultation';
    }

    public function label(): array
    {
        return ['en' => 'Consultation request', 'fr' => 'Demande de consultation'];
    }

    public function fields(): array
    {
        return [
            FormField::select('office')->label(['en' => 'Nearest office', 'fr' => 'Bureau le plus proche'])->required()->options([
                'vancouver' => 'Vancouver',
                'montreal' => 'Montréal',
            ]),
            FormField::textarea('message')->label(['en' => 'What is happening on your shore?', 'fr' => 'Que se passe-t-il sur votre rive?'])->required()->max(4000),
            FormField::text('name')->label(['en' => 'Your name', 'fr' => 'Votre nom'])->required()->width(50),
            FormField::email('email')->label(['en' => 'Email', 'fr' => 'Courriel'])->required()->replyTo(),
        ];
    }

    public function steps(): array
    {
        return [
            new FormStep('matter', ['en' => 'Your matter', 'fr' => 'Votre dossier'], ['office', 'message']),
            new FormStep('contact', ['en' => 'How to reach you', 'fr' => 'Pour vous joindre'], ['name', 'email']),
        ];
    }

    public function successMessage(): array
    {
        return [
            'en' => 'Thank you. Someone from the right office will reply within two business days.',
            'fr' => 'Merci. Quelqu’un du bon bureau vous répondra d’ici deux jours ouvrables.',
        ];
    }
}
```

- `key()` is the name you place the form by. `label()` is how the admin names it.
- Every text is a list of languages, and visitors read the one of their language.
- `steps()` splits the form in two: visitors describe their matter first, then say how to reach them. Each step is checked before the next one shows.
- `width(50)` puts two fields side by side on wide screens.
- `replyTo()` marks the visitor's email address. When the form has an `autoReply()` text, as the starter's does, Lynx mails that confirmation to this address, in the visitor's language. Anyone can type any address there, so keep the confirmation short and free of anything you wouldn't send a stranger.

Register the form in a **service provider**, a Laravel class whose `boot()` method runs on every request, before your pages. The app's own is `app/Providers/AppServiceProvider.php`:

```php
public function boot(): void
{
    lynx()->forms()->register(ConsultationForm::class);
}
```

## Placing the form

The contact page's view places the form with one tag:

```blade
{{-- resources/views/templates/contact.blade.php, shortened --}}
<section aria-labelledby="consultation">
    <h2 id="consultation">@string('consultation.title', ['en' => 'Request a consultation', 'fr' => 'Demander une consultation'], label: 'Consultation form title')</h2>
    <livewire:lynx-form form="consultation" :page-id="$page->id" />
</section>
```

`<livewire:lynx-form>` is a **Livewire** component: a part of the page that talks to the server without reloading it. Livewire comes with Lynx's admin. Visitors see each field checked as they leave it, move between the steps, and read the success message in place. The `page-id` keeps which page the request came from.

The forms module's stylesheet gives the form a clean default look. The starter imports it in `resources/css/app.css`:

```css
@import '../../vendor/lynxcms/forms/resources/css/forms.css';
```

## Where requests go

Each request lands in the admin's **Forms** screen, newest first, where staff mark them read, add notes, and export them as CSV. Lynx also emails each one to the users whose role lets them see submissions (the `forms.view` permission), or to the addresses a form's `recipients()` method returns. The emails go through Laravel's queue, which sends them in the background so visitors don't wait for the mail server. In production, keep a queue worker running (`php artisan queue:work`) to send them.

Spam is filtered for you: a hidden field and a timing check stop most bots, and each visitor may send the form five times in ten minutes. [Forms](/modules/forms) covers file uploads, conditions and forms editors build in the admin.

## A search page

Chapter 2 marked some fields `searchable`. Lynx keeps a **search index**: for every page and language, the words of its title and its searchable fields, updated each time a page is saved. A selector reads the index with `search*=`.

The search page is a page like any other, created by the seeder at `/search/` (`/fr/recherche/`). Its view reads the visitor's words from the address, `?q=dams`, and finds the pages that hold them:

```blade
{{-- resources/views/templates/search.blade.php, shortened --}}
@php
    $query = request()->query('q');
    $query = is_string($query) ? trim(Str::limit($query, 100, '')) : '';
    $words = trim(preg_replace('/["\\\\]+/', ' ', $query) ?? '');
    $results = $words === '' ? null : lynx()->pages->find('search*="'.$words.'", limit=30');
@endphp
<x-layout :page="$page" robots="noindex, follow">
    <form method="GET" action="{{ $page->url() }}" role="search">
        <label for="q">{{ __('site.search.label') }}</label>
        <input id="q" type="search" name="q" value="{{ $query }}">
        <button type="submit">@string('search.go', ['en' => 'Search', 'fr' => 'Chercher'], label: 'Search button')</button>
    </form>

    @if ($results !== null)
        <p role="status">{{ trans_choice('site.search.found', $results->count(), ['count' => $results->count(), 'query' => $query]) }}</p>
        @foreach ($results as $result)
            <h2><a href="{{ $result->url() }}">{{ $result->title }}</a></h2>
            <p>{{ $result->summary }}</p>
        @endforeach
    @endif
</x-layout>
```

- The visitor's words go inside the selector's quotes, so the view first removes double quotes, which would end the quoted value early, and backslashes. Never put a visitor's text into a selector without that step. `is_string()` comes first because a visitor can send an array (`?q[]=dams`), which `(string)` would turn into an error. [Visitor Input](/basics/visitor-input) covers the rest.
- Search reads the visitor's language: French visitors search the French texts, and pages fall back to English where French is missing.
- Words match as the start of a word, in any order, ignoring case and accents: *barr* finds *barrage*, and *montreal* finds *Montréal*.
- `trans_choice()` picks the sentence for the number of results from `lang/en/site.php` and `lang/fr/site.php`.
- `robots="noindex, follow"` asks search engines not to index results pages, but to follow their links.

## What you have now

- **The firm:** consultation requests in their inbox and their email, in the visitor's language, and a site visitors can search in both languages.
- **You:** a form as one class, and a search page in one view, with no search server to run.
