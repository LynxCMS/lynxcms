---
title: Forms
description: Forms defined in PHP, with submissions in the admin and email to the team.
order: 3
---

# Forms

The `lynxcms/forms` module keeps what visitors send. It stores each submission with a copy of the form's fields and mails the people who handle it.

## Defining a form

A form is a PHP class. Texts are a string, or a map of [language codes](/basics/languages#adding-a-language) to text, read in the visitor's language with fallback. This quote request has two steps, a field that shows only for livestreams, a repeating group and file uploads:

<<< @/snippets/modules/QuoteForm.php#form{php}

Register it in a service provider:

```php
lynx()->forms()->register(QuoteForm::class);
```

Field types: `text`, `email`, `tel`, `textarea`, `select`, `radio`, `checkboxes`, `checkbox`, `number`, `date`, `file` and `group`. `showIf('subject', 'press', 'other')` asks a field only when another has one of some values; `showWhen('guests', '>=', 50)` compares (`=`, `!=`, `<`, `<=`, `>`, `>=`), and `showWhen('newsletter', '=', true)` follows a tick box (an untouched box counts as unticked). Hidden fields are neither checked nor stored, so a required field is only required while it shows. `rules()` adds Laravel rules, and `messages()` words a rule's message per language. A `file` field takes the types `extensions()` lists; without them, it takes documents, images, spreadsheets, presentations and zip files (`FormField::DEFAULT_EXTENSIONS`), and `extensions(['*'])` takes any file. Staff open what visitors upload, so keep the list short.

Forms can also say what visitors read after sending (`successMessage()`), when sending fails (`errorMessage()`), and a confirmation mailed to the `replyTo()` address (`autoReply()`). That address is whatever the visitor typed, so anyone can make your site send its confirmation to someone else; keep the confirmation short, with nothing in it you would not send a stranger.

A form that members answer once each, such as a course survey, returns `true` from `oncePerUser()`. Guests see "Sign in to answer this form" with a link to your `login` route instead of the form, and a member who already answered is told so. `forms.submit` refuses both, whoever calls it. In the builder, the same is the form's **Members only, one answer each** setting. To limit a survey to some members, such as those who booked a course, place it on a page only they can open (see [Page Access](/deeper/visibility)).

## Building forms in the admin

Editors make forms without code. **Forms › Forms** lists them (with the forms defined in code) and their submissions; **New form** asks for a title and a name, then opens the form in the page editor. **Preview** shows the form as visitors will see it, unpublished forms too, and sending there only checks the answers. Each form is a page under a hidden branch that the page tree leaves out; its **Fields** are blocks:

| Block | Field |
| --- | --- |
| Text, Long text, Email, Phone, Number, Date | Inputs, with a maximum for text and numbers |
| Dropdown, Choose one, Choose several | Choices from the block's options (a stable value and a label per language) |
| Tick box | One box, such as consent; "Required" means it must be ticked |
| Files | Uploads, with allowed types (empty takes documents, images and zip files; `*` takes any), the largest file and how many |
| Repeating group | Rows visitors add; indent the blocks that belong to it |
| New step | Starts a new step; blocks before the first step make step 1 |

The options of a Dropdown, Choose one or Choose several block are stored in an `options` field: a list that visitors pick from. It differs from a `choice` field, where an editor picks from a list set in the field's settings. Only the form builder creates `options` fields, so the field form doesn't offer the type; the API and MCP still can.

Each block has a label and help text in every language, a name (made from the label when empty), whether it is required, a width, and an optional condition: show only when field *subject* has one of *press, other*, compares as `>= 50` or `!= 3`, or for a tick box is *yes* or *no*. A required field is only required while it shows. Switch a block off to hide it without deleting it. Saving refuses a form that would not work, and marks the block to fix. A form is served once it is published.

The form's own settings say who receives submissions (emails, or else everyone who may see submissions), what visitors read after sending or when sending fails, and the confirmation mailed to them.

Templates let editors pick a form with the `form` field, and render it:

```blade
<x-lynx-form :form="$page->form" />
<x-lynx-form form="newsletter" />                          {{-- by key --}}
<x-lynx-form :form="$page->form" livewire="quote-request" /> {{-- with your own component --}}
```

On a page the [page cache](/modules/page-cache) stores, a form keeps the time it was first rendered, so the honeypot's "sent too fast" check never fires. Put forms that rely on it in an island, which renders them for each visitor:

```blade
<x-lynx-island livewire="lynx-form" :with="['form' => 'newsletter']" />
```

## Placing a form

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

### Adding behaviour

Extend the component for live extras, such as a price estimate, and show them beside the form:

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

### Texts

Labels, help, options, step titles and the success and error messages come from the form, in each language. The component's own words (Next, Send, "Too many attempts…") are translations in `lynx-forms::forms`; publish them with `--tag=lynx-forms-lang` to change them. Validation messages come from the app's `lang/{locale}/validation.php`, such as laravel-lang's, with field labels as attribute names.

### Spam

Spatie's [laravel-honeypot](https://github.com/spatie/laravel-honeypot) adds a hidden field and a timing check. A caught bot sees the usual thanks, and nothing is stored or mailed. Each visitor may send a form 5 times in 10 minutes (`LYNX_FORMS_RATE_ATTEMPTS`, `LYNX_FORMS_RATE_DECAY`).

## Who hears about submissions

Each submission is mailed (queued) to the form's `recipients()`. Without them, it goes to the users whose roles grant `forms.view`, superusers included. When nobody holds it, it goes to the addresses in `LYNX_FORMS_NOTIFY`. Files are never attached; they stay on the private disk (`LYNX_FORMS_DISK`, default `local`).

## Reacting to submissions

`LynxCms\Forms\Events\FormSubmitted` is dispatched once a submission is stored and committed, with the submission's id. Listen to it to send leads to a CRM, post to Slack or start your own workflow. Make the listener queued, so a slow service never slows the visitor, and let it fail when the service is down, so the queue tries again:

<<< @/snippets/modules/SendQuoteToCrm.php#listener{php}

Laravel finds listeners in `app/Listeners` by the event their `handle()` method takes, so the class above needs no registration. Elsewhere, register it with `Event::listen(FormSubmitted::class, SendQuoteToCrm::class)`.

`$submission->data` holds the answers by field name, `$submission->fields` the form's fields as they were when it was sent, and `$submission->files` the uploads, which stay on the private forms disk. A caught bot never gets this far: nothing is stored, so no event is dispatched.

When a signed-in visitor sends a form, such as a member answering a survey, the submission keeps their account: `$submission->user_id`, `$submission->user()` (the app's user, or `null` for a guest) and `$submission->account()` (*Name &lt;email&gt;*, as staff read it). The inbox, the CSV export and the agents' `get_submission` show the account too. Find someone's answers with `Submission::query()->fromUser($user->id)`.

## In the admin

**Forms** in the sidebar shows how many submissions are new. The inbox lists them newest first. You can filter by status (new and read, archived, spam) and form, search any answer, and mark several at once. Opening a submission marks it read. It shows the answers as the form asked them, with links to the files, the page and language it came from, and the team's notes. Each form exports to CSV, where answers that start like a formula (`=`, `+`, `-`, `@`) get a leading quote so spreadsheets show them as text.

**Results**, next to each form in **Forms › Forms**, sums the answers up per question, as a survey's results: how many chose each option, the average of numbers, the range of dates, and the latest written answers, with spam left out. A form placed on several pages, such as one survey per course, filters the results by the page it was sent from. In code, `app(\LynxCms\Forms\Results\FormResults::class)->summarize($form, $pageId)` returns the same.

## Agents

MCP clients and the built-in agent read submissions with `list_submissions` (filter by form, status and date) and `get_submission`, sum a form's answers up with `summarize_form` (optionally for one page), and sort submissions with `forms_update_submission`. Ask "summarise this week's quote requests", "how did the yoga course survey go?", or "mark the obvious spam". The read tools need `forms.view`.

## Permissions

| Permission | Allows |
| --- | --- |
| `forms.view` | Seeing submissions, marking them read, archived or spam, and notes |
| `forms.manage` | Deleting submissions |

## Housekeeping

`php artisan lynx:forms:purge` runs daily. It deletes spam and archived submissions older than `LYNX_FORMS_PURGE_DAYS` (180), with their files. New and read submissions are kept.
