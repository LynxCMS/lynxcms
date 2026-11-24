---
title: Forms
description: Forms defined in PHP, with submissions in the admin and email to the team.
order: 3
---

# Forms

With `lynxcms/forms`, visitors can send you messages, requests and files from your pages. Lynx checks each submission, keeps it in the admin's inbox with a copy of the form's fields, and mails the people who handle it.

## Defining a form

A form is a PHP class. Texts are a string, or a map of [language codes](/basics/languages#adding-a-language) to text, read in the visitor's language with fallback. This quote request, in `app/Forms/QuoteForm.php`, has two steps, a field that shows only for livestreams, a repeating group and file uploads:

<<< @/snippets/modules/QuoteForm.php#form{php}

Register it in the `boot()` method of a service provider such as `app/Providers/AppServiceProvider.php`. A service provider is a Laravel class whose `boot()` method runs on every request, before your pages.

```php
lynx()->forms()->register(QuoteForm::class);
```

Field types: `text`, `email`, `tel`, `textarea`, `select`, `radio`, `checkboxes`, `checkbox`, `number`, `date`, `file` and `group`. `showIf('subject', 'press', 'other')` asks a field only when another has one of some values; `showWhen('guests', '>=', 50)` compares (`=`, `!=`, `<`, `<=`, `>`, `>=`), and `showWhen('newsletter', '=', true)` follows a tick box (an untouched box counts as unticked). Hidden fields are neither checked nor stored, so a required field is only required while it shows. `rules()` adds Laravel rules, and `messages()` words a rule's message per language. A `file` field takes the types `extensions()` lists; without them, it takes documents, images, spreadsheets, presentations and zip files (`FormField::DEFAULT_EXTENSIONS`), and `extensions(['*'])` takes any file. Staff open what visitors upload, so keep the list short.

Forms can also say what visitors read after sending (`successMessage()`), when sending fails (`errorMessage()`), and a confirmation mailed to the `replyTo()` address (`autoReply()`). That address is whatever the visitor typed, so anyone can make your site send its confirmation to someone else; keep the confirmation short, with nothing in it you would not send a stranger.

A form that members answer once each, such as a course survey, returns `true` from `oncePerUser()`. Guests see "Sign in to answer this form" with a link to your `login` route instead of the form, and a member who already answered is told so. `forms.submit` refuses both, whoever calls it. In the builder, the same is the form's **Signed-in visitors only, one answer each** setting. It needs visitor accounts: a sign-in of your own for visitors, or users your app already has. On a site without one, only people who sign in to the admin could answer. To limit a survey to some members, such as those who booked a course, place it on a page only they can open (see [Page Access](/deeper/visibility)).

## Building forms in the admin

Editors make forms without code. **Forms › Forms** lists them (with the forms defined in code) and their submissions; **New form** asks for a title and a name, then opens the form in the page editor. The name is the form's key, which templates and submissions use: lowercase letters, digits and dashes, starting with a letter, such as `contact-us`. The admin lowercases what you type, so `Contact` becomes `contact`. **Preview** shows the form as visitors will see it, unpublished forms too, and sending there only checks the answers. Each form is a page under a hidden branch that the page tree leaves out; its **Fields** are blocks:

| Block | Field |
| --- | --- |
| Text, Long text, Email, Phone, Number, Date | Inputs, with a maximum for text and numbers |
| Dropdown, Choose one, Choose several | Choices from the block's options (a stable value and a label per language) |
| Tick box | One box, such as consent; "Required" means it must be ticked |
| Files | Uploads, with allowed types (empty takes documents, images and zip files; `*` takes any), the largest file and how many |
| Repeating group | Rows visitors add; indent the blocks that belong to it |
| New step | Starts a new step; blocks before the first step make step 1 |

The options of a Dropdown, Choose one or Choose several block are stored in an `options` field: a list that visitors pick from. It differs from a `choice` field, where an editor picks from a list set in the field's settings. Only the form builder creates `options` fields, so the field form doesn't offer the type; the API and MCP still can.

Each block has a label and help text in every language, a name (made from the label when empty), whether it is required, a width, and an optional condition, in its own box with examples: show only when the field *subject* has the answer *press, other* (one of them), a comparison such as `>= 50` or `!= 3`, or for a tick box *yes* or *no*. A required field is only required while it shows. Switch a block off to hide it without deleting it. Saving refuses a form that would not work, and marks the block to fix. A form is served once it is published.

The form's own settings say who receives submissions (emails, or else everyone who may see submissions), what visitors read after sending or when sending fails, and the confirmation mailed to them.

Templates let editors pick a form with the `form` field, and render it in the template's view, such as `resources/views/templates/contact.blade.php`:

```blade
<x-lynx-form :form="$page->form" />
<x-lynx-form form="newsletter" />                          {{-- by key --}}
<x-lynx-form :form="$page->form" livewire="quote-request" /> {{-- with your own component --}}
```

A form near the bottom of a page can wait until the visitor scrolls to it, so rendering the page doesn't pay for it:

```blade
<x-lynx-form :form="$page->form" lazy />
```

Until it loads, an empty `<div data-lynx-form-placeholder>` holds its place. Give the element around the form a minimum height in CSS, so the page doesn't jump when the form arrives. The form loads in the page's language, like any other.

The key is the form's name in the default language, the one on its **Settings** tab. A form has no URL, so it shows in every language wherever a template puts it, under that same key.

On a page the [page cache](/modules/page-cache) stores, a form keeps the time it was first rendered, so the honeypot's "sent too fast" check never fires. Put forms that rely on it in an island, which renders them for each visitor:

```blade
<x-lynx-island livewire="lynx-form" :with="['form' => 'newsletter']" />
```

**Duplicate**, next to each form in **Forms › Forms**, copies it within the site: its fields, messages and settings, recipients included, as an unpublished form named `contact-copy`. The `forms.duplicate` action does the same.

### Copying a form to another site

You can copy a form built in the admin to another site as a JSON file. **Export**, next to each form in **Forms › Forms**, downloads `contact.form.json`. On the other site, open **Import form** above the list and pick that file. It says what the import would create (the form's name, how many fields and steps, and the languages it leaves out) and changes nothing until you choose **Create form**, which opens the new form in the page editor. On the command line:

```bash
php artisan lynx:forms:export contact contact.form.json   # without a path, the JSON is printed
php artisan lynx:forms:import contact.form.json --dry-run # checks the file, and changes nothing
php artisan lynx:forms:import contact.form.json --name=contact-2
```

The file holds the form's name, its title, its messages and every block with its settings, conditions, groups and steps. Recipients, the one answer per account setting and submissions belong to the site they were made on, so they stay behind. The imported form is unpublished: add its recipients, check it in **Preview**, then publish it.

Texts are keyed by language code. Texts in the other site's default language become this site's default language texts, whatever the two codes are, so a form from an `en` site imports into a site whose default is `default`. Texts in a language this site lacks are left out, and the import names those languages. An import is refused when this site has a form with that name: give the copy another with `--name`, or in the **Form name** box. A form defined in code travels with the app's code, so only forms built in the admin export.

## Placing a form

A template's view can also place the form's Livewire component itself. A Livewire component is a PHP class with a Blade view that updates in the browser without reloading the page.

```blade
<livewire:lynx-form form="quote" :page-id="$page->id" />
```

The component shows one step at a time and checks it before moving on. Fields appear as the answers ask for them, each field is checked when the visitor leaves it, group rows can be added, removed and dragged, and uploads show their progress. The last step sums up the earlier answers. After sending, the form's success message replaces it, without a page reload. The page and language stay with the component, so labels and messages follow the visitor's language.

### Looks

The views use `lynx-form-*` classes. Import the default stylesheet and set its variables to match the site:

```css
@import "../../vendor/lynxcms/forms/resources/css/forms.css";

.lynx-form {
    --lynx-form-accent: #e8a33d;
    --lynx-form-accent-text: #111;
    --lynx-form-line: rgb(255 255 255 / 0.15);
}
```

Or publish the views (`php artisan vendor:publish --tag=lynx-forms-views`) and style them yourself.

### Adding behavior

Extend the component for live extras, such as a price estimate, and show them beside the form. The class goes in `app/Livewire/QuoteRequest.php`:

```php
use Livewire\Attributes\Computed;
use LynxCms\Forms\Livewire\FormComponent;

final class QuoteRequest extends FormComponent
{
    #[Computed]
    public function estimate(): int
    {
        return 1500 + count($this->data['deliverables'] ?? []) * 400;
    }

    public function asideView(): ?string
    {
        return 'forms.quote-estimate';
    }
}
```

Two more methods are there for subclasses to override:

- `fieldOptions(FormField $field)` gives a choice's options on this component. Return fewer than the form has to offer only some of them on this page, such as the languages a document exists in. The component shows and checks the options it gives, so an answer outside them is refused before anything is stored.
- `afterSent(Submission $submission)` runs once the submission is stored, in the same request, so the component can answer the visitor with something made for them, such as a [temporary link](/basics/images-and-files#temporary-links). It doesn't run for spam, which is never stored.

This component, in `app/Livewire/ReportRequest.php`, uses both:

```php
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;

final class ReportRequest extends FormComponent
{
    public ?string $downloadUrl = null;

    protected function fieldOptions(FormField $field): array
    {
        if ($field->name !== 'language') {
            return $field->options;
        }

        return array_intersect_key($field->options, array_flip($this->report()->languages()));
    }

    protected function afterSent(Submission $submission): void
    {
        $this->downloadUrl = $this->report()->file($this->data['language'])->temporaryUrl(now()->addDay());
    }
}
```

Use `afterSent()` for what the visitor waits for. Mail and other work that can wait belong in a listener for `FormSubmitted`, which runs on the queue.

### Texts

Labels, help, options, step titles and the success and error messages come from the form, in each language. The component's own words (Next, Send, "Too many attempts…"), and the success and error messages of a form that doesn't define its own, are translations in `lynx-forms::forms`, shipped in English, French, Portuguese and Spanish; publish them with `--tag=lynx-forms-lang` to change them. Validation messages come from the app's `lang/{locale}/validation.php`, such as laravel-lang's, with field labels as attribute names.

### Spam

Spatie's [laravel-honeypot](https://github.com/spatie/laravel-honeypot) adds a hidden field and a timing check. A caught bot sees the usual thanks, and nothing is stored or mailed. Each visitor may send a form 5 times in 10 minutes (`LYNX_FORMS_RATE_ATTEMPTS`, `LYNX_FORMS_RATE_DECAY`).

[Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/) adds a check that a visitor is a person, which most people pass without a puzzle. It is off until the site has a site key and a secret key, from a free Cloudflare account. Put them in `.env`:

```ini
LYNX_FORMS_TURNSTILE_SITE_KEY=0x4AAAAAAA...
LYNX_FORMS_TURNSTILE_SECRET=0x4AAAAAAA...
```

Or a superuser sets them in **Forms › Forms › Spam protection**, where the secret is stored encrypted with the app key. Keys in `.env` win. Every form then shows the widget above its Send button, and a submission is sent only once Cloudflare confirms it; when Cloudflare can't be reached, the submission is refused. Turnstile loads a script from `challenges.cloudflare.com` on pages with forms, so mention it in the site's privacy notice, and allow that host in a Content Security Policy. Cloudflare's [test keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/) always pass or always fail, for local sites and tests. The timing check above doesn't survive the [page cache](/modules/page-cache); Turnstile does, as its token is made in the visitor's browser.

## Who hears about submissions

Each submission is mailed (queued) to the form's `recipients()`. A busy form can send one summary a day instead: the builder's **Mail a daily summary instead of each submission**, or `digest()` returning `true` in code. `lynx:forms:digest` runs daily at `LYNX_FORMS_DIGEST_AT` (08:00, in the app's time zone) and mails how many arrived since the last summary, the first answers of the latest 20, and a link to them in the inbox. Spam is left out. Without them, it goes to the users whose roles grant `forms.view`, superusers included. When nobody holds it, it goes to the addresses in `LYNX_FORMS_NOTIFY`. Files are never attached; they stay on the private disk (`LYNX_FORMS_DISK`, default `local`).

## Reacting to submissions

`LynxCms\Forms\Events\FormSubmitted` is dispatched once a submission is stored and committed, with the submission's id. Listen to it to send leads to a CRM, post to Slack or start your own workflow. Make the listener queued, so a slow service never slows the visitor, and let it fail when the service is down, so the queue tries again:

<<< @/snippets/modules/SendQuoteToCrm.php#listener{php}

Laravel finds listeners in `app/Listeners` by the event their `handle()` method takes, so the class above needs no registration. Elsewhere, register it with `Event::listen(FormSubmitted::class, SendQuoteToCrm::class)`.

`$submission->data` holds the answers by field name, `$submission->fields` the form's fields as they were when it was sent, and `$submission->files` the uploads, which stay on the private forms disk. A caught bot never gets this far: nothing is stored, so no event is dispatched.

When a signed-in visitor sends a form, such as a member answering a survey, the submission keeps their account: `$submission->user_id`, `$submission->user()` (the app's user, or `null` for a guest) and `$submission->account()` (*Name &lt;email&gt;*, as staff read it). The inbox, the CSV export and the agents' `get_submission` show the account too. Find someone's answers with `Submission::query()->fromUser($user->id)`.

## In the admin

**Forms** in the sidebar shows how many submissions are new. The inbox lists them newest first, 25, 50 or 100 to a page. You can filter by status (new and read, archived, spam), form and when they arrived (the last 7 or 30 days, this month, last month, or between two dates), and search any answer. Opening a submission marks it read. It shows the answers as the form asked them, with links to the files, the page and language it came from, and the team's notes.

Tick submissions to mark them read, archived or spam, or to delete them. The box in the table's header ticks the whole page; then **Select all** takes every submission the filters match, on every page. Submissions that arrive after that stay out of it. **Delete** first says how many submissions and files would go, and needs `forms.manage`. A deleted submission can't be brought back.

Each form exports to CSV, with the inbox's filters, where answers that start like a formula (`=`, `+`, `-`, `@`) get a leading quote so spreadsheets show them as text.

**Results**, next to each form in **Forms › Forms**, sums the answers up per question, as a survey's results: how many chose each option, the average of numbers, the range of dates, and the latest written answers, with spam left out. A form placed on several pages, such as one survey per course, filters the results by the page it was sent from. In code, `app(\LynxCms\Forms\Results\FormResults::class)->summarize($form, $pageId)` returns the same.

## Personal data

People may ask what a site holds about them, or for it to be deleted, as the GDPR allows in the EU. **Forms › Personal data** answers that for the forms. Type an email address, and it lists every submission where that address is a whole answer, in any form and status: `ana@example.com` doesn't match `joana@example.com`, and capitals don't matter. **Export (JSON)** downloads them to hand the person: each submission's form, date, status, language, answers as the form asked them, and the names of its files. **Delete** says how many submissions and files would go, then deletes them for good. The tab needs `forms.manage`.

In code, `app(\LynxCms\Forms\PersonalData::class)->export('ana@example.com')` returns the same data, and `forms.delete_submissions` with `filter: {show: all, email: ana@example.com}` deletes it.

## Agents

MCP clients and the built-in agent can import a form export with `forms_import`, which needs `forms.manage`. They read submissions with `list_submissions` (filter by form, status and date) and `get_submission`, sum a form's answers up with `summarize_form` (optionally for one page), and sort submissions with `forms_update_submission`, or many at once with `forms_update_submissions` (a list, or every submission a filter matches). `forms_delete_submissions` deletes many, after a dry run; it needs `forms.manage`. Ask "summarize this week's quote requests", "how did the yoga course survey go?", or "mark the obvious spam". The read tools need `forms.view`.

## Permissions

| Permission | Allows |
| --- | --- |
| `forms.view` | Seeing submissions, marking them read, archived or spam, and notes |
| `forms.manage` | Building forms, exporting and importing them, and deleting submissions |

## Housekeeping

`php artisan lynx:forms:purge` runs daily. It deletes spam and archived submissions older than `LYNX_FORMS_PURGE_DAYS` (180), with their files.

New and read submissions are kept, unless their form keeps submissions for a set time. In the builder, that is the form's **Delete submissions after (days)** setting: 365 keeps a year, and empty keeps them. A form in code returns the days from `retentionDays()`. `LYNX_FORMS_RETENTION_DAYS` sets it for every form that sets none of its own. The purge then deletes that form's submissions older than the setting, in any status, with their files. Job applications and contact forms that hold personal data are the usual candidates.
