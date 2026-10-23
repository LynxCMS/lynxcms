---
title: Leads
description: Chapter 6 of the training site guide. An enquiry form on every course, a newsletter sign-up, the team's inbox, and leads sent to a CRM and a mailing service.
---

# Leads

Most people who look at a 2,400 euro course have a question before they book. This chapter puts an enquiry form on every course page and a newsletter sign-up in the footer. It lands every answer in the school's inbox, and passes leads on to the CRM and the mailing service the school already uses.

## The forms

Forms are PHP classes. Texts are maps of language codes, read in the visitor's language. The enquiry asks for a name, an email, a question and consent:

<<< @/snippets/training/app/Forms/EnquiryForm.php#form{php}

`recipients()` names who gets the email: here the front desk, from your config. Return `null` to mail everyone whose role may see submissions, or `[]` to mail nobody, as the newsletter does:

<<< @/snippets/training/app/Forms/NewsletterForm.php#form{php}

Register both in the service provider:

<<< @/snippets/training/app/Providers/AtlasServiceProvider.php#forms{php}

## Placing them

The enquiry goes on the course page, under the dates, and the newsletter goes in the layout's footer:

```blade
<livewire:lynx-form form="enquiry" :page-id="$page->id" />
<livewire:lynx-form form="newsletter" :page-id="$page->id" />
```

`page-id` is the page the visitor is on. It is stored with the submission, so the enquiry form needs no "which course?" field: staff see the course in the inbox, and your code reads it from `$submission->page_id`. The form checks each field as the visitor leaves it, shows the thank-you message without a reload, and speaks the visitor's language.

The forms module's stylesheet takes CSS variables, so the forms match Bloom with a few lines in `atlas.css`:

```css
.lynx-form {
    --lynx-form-accent: var(--atlas-ink);
    --lynx-form-accent-text: #fff;
    --lynx-form-line: rgb(58 51 64 / 0.15);
}
```

Spam is handled for you. A hidden honeypot field and a timing check catch bots, which see the usual thanks while nothing is stored. Each visitor may send a form 5 times in 10 minutes.

## The team's inbox

**Forms** in the admin's sidebar shows how many enquiries are new. The inbox lists them newest first, with a filter by status and form, a search across every answer, notes for the team, and a CSV export. Give the front desk a role with `forms.view`. Agents can read and sort submissions too: ask "summarise this week's enquiries" (see [Forms](/modules/forms#agents)).

## Leads to the CRM

`FormSubmitted` fires once a submission is stored and committed. A queued listener sends each enquiry to the CRM, with the course it came from:

<<< @/snippets/training/app/Listeners/SendEnquiryToCrm.php#listener{php}

The listener is queued, so a slow CRM never slows the visitor. It throws when the CRM answers with an error, so the queue tries again with growing pauses, and the submission's uuid lets the CRM ignore a lead it already has. Laravel finds listeners in `app/Listeners` by the event their `handle()` takes, so it needs no registration. The CRM's address and token come from `config/atlas.php`, which reads them from `.env` (`ATLAS_CRM_URL`, `ATLAS_CRM_TOKEN`).

## Sign-ups to the mailing list

The newsletter's listener tags each address with its language, so Portuguese readers get the Portuguese edition:

<<< @/snippets/training/app/Listeners/SubscribeToNewsletter.php#listener{php}

Run a queue worker in production (`php artisan queue:work`, kept alive by Supervisor or your host), or both listeners wait forever.

## What you get

- **The client:** an inbox of enquiries that says which course each is about, leads in their CRM within seconds, and a growing mailing list split by language.
- **You:** forms as classes in Git, validation and spam protection built in, and one event to hang every integration on.
