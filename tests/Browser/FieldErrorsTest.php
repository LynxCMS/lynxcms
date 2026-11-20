<?php

declare(strict_types=1);

/*
 * Error messages tied to their fields (accessibility review, WCAG 1.3.1 and 3.3.1): a screen
 * reader on the field reads the message, through aria-describedby, after Livewire's updates too.
 */

beforeEach(function (): void {
    signInSite();
    $this->ana = browserUser('ana@example.com');
});

it('ties a Livewire form\'s errors to their fields, and keeps them tied after the next update', function (): void {
    $this->actingAs($this->ana);

    $page = visit('/lynx/languages/create')
        ->press('Create language')
        ->assertSee('The code field is required.')
        ->assertAttribute('#language-code', 'aria-describedby', 'lynx-error-code')
        ->assertAttribute('#language-code', 'aria-invalid', 'true')
        ->assertAttribute('[data-error-for="code"]', 'id', 'lynx-error-code');

    $page->fill('#language-name', 'Español')->press('Create language')
        ->assertAttribute('#language-code', 'aria-describedby', 'lynx-error-code');
});

it('ties the sign-in form\'s error to the email field', function (): void {
    visit('/lynx/login')
        ->fill('email', 'ana@example.com')
        ->fill('password', 'not the password')
        ->click('button[type=submit]')
        ->assertAttribute('#email', 'aria-describedby', 'lynx-error-email')
        ->assertAttribute('#email', 'aria-invalid', 'true');
});

it('ties a page field\'s error to the input of each language', function (): void {
    $this->actingAs($this->ana);
    $courses = (int) lynx()->pages->get('/courses/')?->id;

    visit("/lynx/pages/{$courses}/edit")
        ->fill('#input-title-default', '')
        ->press('Save')
        ->assertAttributeContains('#input-title-default', 'aria-describedby', 'lynx-error-values-title')
        ->assertAttribute('#input-title-default', 'aria-invalid', 'true');
});
