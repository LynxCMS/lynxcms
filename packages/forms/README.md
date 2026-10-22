# lynxcms/forms

Forms for Lynx CMS. Define a form in PHP, place it on any page, and find what visitors sent in the admin. The people who handle submissions get an email.

- Fields, steps, conditional fields, repeating groups and file uploads, with validation in the visitor's language.
- Submissions keep a copy of the form's fields, so later changes never break old answers.
- Files stay on a private disk.
- Spam protection with [spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot) and a rate limit.
- Actions for every change (`forms.submit`, `forms.update_submission`, `forms.delete_submission`, `forms.purge`), so the MCP server and the AI agent can work with submissions.

See `docs/front-end/forms.md` for the guide.
