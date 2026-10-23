<?php

declare(strict_types=1);

use App\Events\CommentPosted;
use App\Forms\QuoteForm;
use App\Listeners\ClearPostOnComment;
use App\Listeners\SendQuoteToCrm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Models\Submission;

/*
 * The module examples of the documentation: the quote form and its CRM listener (Forms) and the
 * navigation bar view (Menus), from docs/snippets/modules, on the blog of "Your First Site".
 */

const MODULES = __DIR__.'/../../../docs/snippets/modules';

require_once MODULES.'/QuoteForm.php';
require_once MODULES.'/SendQuoteToCrm.php';
require_once MODULES.'/CommentPosted.php';
require_once MODULES.'/ClearPostOnComment.php';

beforeEach(function (): void {
    installLynx();
    lynx()->languages->update('default', ['code' => 'en', 'name' => 'English', 'locale' => 'en']);
    lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'en');
    require __DIR__.'/../../../docs/snippets/first-site/schema.php';
    require __DIR__.'/../../../docs/snippets/first-site/pages.php';
});

/** A quote request with valid answers, as a visitor in $language sends it. */
function sendExampleQuote(string $type = 'live', string $language = 'en'): int
{
    $data = ['type' => $type, 'deliverables' => [['format' => 'Teaser']], 'email' => 'ana@example.com', 'consent' => true];

    return lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => $type === 'live' ? [...$data, 'cameras' => 3] : $data, 'language' => $language])
        ->valueAs(Submission::class)->id;
}

function examplePostIsStored(): bool
{
    return PageCacheEntry::query()->where('page_id', lynx()->pages->get('/blog/hello-world')?->id)->exists();
}

describe('the quote form', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        RateLimiter::clear('lynx-forms');
        lynx()->forms()->register(QuoteForm::class);
    });

    it('asks for cameras only for a livestream', function (): void {
        expect(Submission::query()->findOrFail(sendExampleQuote('film'))->data)->not->toHaveKey('cameras');

        expect(fn (): ActionResult => lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => ['type' => 'live', 'email' => 'ana@example.com', 'consent' => true]]))
            ->toThrow(ValidationException::class);
    });

    it('queues the CRM listener after the submission is stored', function (): void {
        Queue::fake();
        Event::listen(FormSubmitted::class, SendQuoteToCrm::class);

        sendExampleQuote();

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendQuoteToCrm::class);
    });

    it('sends a quote request to the CRM as a lead', function (): void {
        config(['services.crm' => ['url' => 'https://crm.test/api', 'token' => 'secret']]);
        Http::fake(['crm.test/*' => Http::response(['id' => 7], 201)]);

        new SendQuoteToCrm()->handle(new FormSubmitted(sendExampleQuote('live', 'pt')));

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://crm.test/api/leads'
            && $request->hasHeader('Authorization', 'Bearer secret')
            && $request['type'] === 'live' && $request['cameras'] === 3 && $request['language'] === 'pt'
            && $request['reference'] === Submission::query()->sole()->uuid);
    });

    it('fails the job while the CRM is down, so the queue tries again', function (): void {
        config(['services.crm' => ['url' => 'https://crm.test/api', 'token' => 'secret']]);
        Http::fake(['crm.test/*' => Http::response('Unavailable', 503)]);

        expect(fn () => new SendQuoteToCrm()->handle(new FormSubmitted(sendExampleQuote())))->toThrow(RequestException::class)
            ->and(new SendQuoteToCrm()->tries)->toBe(5);
    });

    it('does nothing when the submission is gone by the time the job runs', function (): void {
        Http::fake();

        new SendQuoteToCrm()->handle(new FormSubmitted(999_999));

        Http::assertNothingSent();
    });
});

describe('clearing a post when a comment arrives', function (): void {
    beforeEach(function (): void {
        config(['lynx-cache.pages.enabled' => true, 'lynx-cache.pages.path' => testingPath('lynx-cache')]);
        File::deleteDirectory(testingPath('lynx-cache'));
        View::prependLocation(__DIR__.'/../../../docs/snippets/first-site/views');
        View::getFinder()->flush();
        lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
        Event::listen(CommentPosted::class, ClearPostOnComment::class);

        $this->get('/blog/hello-world')->assertOk();
        $this->actingAs(new GenericUser(['id' => 9]));
    });

    it('clears the post\'s stored copies, as the system', function (): void {
        expect(examplePostIsStored())->toBeTrue();

        event(new CommentPosted((int) lynx()->pages->get('/blog/hello-world')?->id));

        expect(examplePostIsStored())->toBeFalse();
    });

    it('could not clear them as the commenter, who holds no role', function (): void {
        expect(fn (): ActionResult => lynx()->run(ClearPageCache::class, ['pages' => [(int) lynx()->pages->get('/blog/hello-world')?->id]]))
            ->toThrow(AuthorizationException::class)
            ->and(examplePostIsStored())->toBeTrue();
    });
});

describe('the navigation bar', function (): void {
    beforeEach(function (): void {
        lynx()->menus()->create('top', ['en' => 'Main navigation']);
        lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/blog/', 'children' => true]);
        lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'GitHub', 'url' => 'https://github.com', 'target' => '_blank']);
        View::prependLocation(MODULES.'/views');
        View::getFinder()->flush();
    });

    it('lists the blog\'s posts in a submenu and marks the current one', function (): void {
        app()->instance('request', Request::create('/blog/hello-world'));

        $html = Blade::render('<x-lynx-menu name="top" view="menus.navbar" />');

        expect($html)->toContain('aria-label="Main navigation"')
            ->toContain('<summary class="cursor-pointer list-none py-3">Blog</summary>')
            ->toContain('All of Blog')
            ->toMatch('#<a href="[^"]*/blog/hello-world"[^>]*aria-current="page"#')
            ->not->toMatch('#<a href="[^"]*/blog/second-post"[^>]*aria-current#')
            ->toContain('<li class="relative font-semibold">')
            ->toContain('target="_blank"')->toContain('rel="noopener"');
    });
});
