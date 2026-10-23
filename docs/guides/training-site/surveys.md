---
title: Surveys
description: Chapter 9 of the training site guide. A course feedback survey staff build without code, answered once by each member who booked, with results per course.
---

# Surveys

After each course, the school wants to know how it went. This chapter adds a feedback survey that the staff build themselves, that only the people who took the course can answer (once each), and whose results the owner reads per course without exporting a spreadsheet.

## Built in the admin

Staff build forms in **Forms › Builder**: each question is a block (choose one, choose several, long text and so on), with its label in both languages. The survey's setting **Members only, one answer each** asks guests to sign in and refuses a second answer from the same account.

The starter seeds the survey so it has one to show. This is what the builder stores:

<<< @/snippets/training/database/seeders/AtlasSurveySeeder.php#survey{php}

A form built here is used like a form written in PHP: by its name, in the visitor's language, through the same validation, spam protection and inbox. A code form gets the same behaviour from `oncePerUser()` (see [Forms](/modules/forms#defining-a-form)).

## Only for the people who took the course

"Signed in" isn't enough: only people who booked should answer. The survey goes on the course's **materials** page, which only they can open (chapter 8):

```blade
<livewire:lynx-form form="course-feedback" :page-id="$page->id" />
```

So the rule that guards the materials also guards the survey. The `page-id` keeps which course each answer is about, which the results use below. A member opens the materials, answers, and on their next visit is told they already answered.

## The results

**Forms › Forms** has a **Results** button next to each form. It sums the answers up per question: how many chose each rating, how the pace felt, what worked best, and the latest comments. Spam is left out. Because the survey sits on every course's materials page, the results filter by course.

The owner can ask an AI assistant too, through the MCP server: "how did the yoga teacher training survey go?" uses the `summarize_form` tool with the course's page. In code, the same numbers come from `FormResults`:

```php
use LynxCms\Forms\Results\FormResults;

$results = app(FormResults::class)->summarize(lynx()->forms()->get('course-feedback'), $materials->id);
```

Answers keep the member's account (the inbox shows it), so the school can thank people, or follow up on a poor rating.

## What you get

- **The client:** surveys they build and change themselves, answered by real customers, once each, with results per course on one screen.
- **You:** no survey code at all: the form builder, the access rules from chapter 8, and Lynx's results.
