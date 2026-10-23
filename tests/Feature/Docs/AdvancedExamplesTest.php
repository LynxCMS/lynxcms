<?php

declare(strict_types=1);

use App\Http\Controllers\PostIndexController;
use App\Lynx\Actions\ArchivePost;
use App\Mail\NewPostMail;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/*
 * The advanced examples in the documentation are the files in docs/snippets/advanced, shown with
 * VitePress includes. They run here on the blog of "Your First Site".
 */

const ADVANCED = __DIR__.'/../../../docs/snippets/advanced';
const BLOG = __DIR__.'/../../../docs/snippets/first-site';

require_once ADVANCED.'/ArchivePost.php';
require_once ADVANCED.'/PostIndexController.php';
require_once ADVANCED.'/NewPostMail.php';
require_once ADVANCED.'/MembersOnly.php';
require_once ADVANCED.'/RatingType.php';

beforeEach(function (): void {
    installLynx();
    require BLOG.'/schema.php';
    require BLOG.'/pages.php';
});

function blogPost(string $path): Page
{
    $post = lynx()->pages->get($path);
    assert($post instanceof Page);

    return $post;
}

it('archives a post with a custom action, also as a dry run', function (): void {
    lynx()->actions()->register(ArchivePost::class);

    $dryRun = lynx()->run(ArchivePost::class, ['post' => '/blog/hello-world'], dryRun: true);
    expect($dryRun)->toBeInstanceOf(ActionResult::class)
        ->and(blogPost('/blog/hello-world')->hasStatus(PageStatus::HIDDEN))->toBeFalse();

    lynx()->run(ArchivePost::class, ['post' => '/blog/hello-world']);

    expect(blogPost('/blog/hello-world')->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and(lynx()->pages->find('template=post')->ids())->not->toContain(blogPost('/blog/hello-world')->id);
});

it('refuses to archive a page that is not a post', function (): void {
    lynx()->actions()->register(ArchivePost::class);

    expect(fn (): ActionResult => lynx()->run(ArchivePost::class, ['post' => '/blog']))->toThrow(InvalidArgumentException::class);
});

describe('hooks and events', function (): void {
    beforeEach(function (): void {
        Mail::fake();
        require ADVANCED.'/hooks.php';
    });

    function newPost(?string $summary): Page
    {
        $post = lynx()->pages->new('post', '/blog');
        $post->name = 'news';
        $post->title = 'News';
        $post->summary = $summary;

        return $post;
    }

    it('refuses a published post without a summary, and writes nothing', function (): void {
        expect(fn (): mixed => newPost(null)->save())->toThrow(ValidationException::class, 'A published post needs a summary.')
            ->and(lynx()->pages->get('/blog/news'))->toBeNull();

        Mail::assertNothingSent();
    });

    it('shows the rule on the field in the page editor', function (): void {
        $this->actingAs(new GenericUser(['id' => 1]));

        Livewire::test(PageEditor::class, ['pageId' => blogPost('/blog/hello-world')->id])
            ->set('values.summary.default', '')
            ->call('save')
            ->assertHasErrors('values.summary');

        expect((string) blogPost('/blog/hello-world')->summary)->toBe('Our first post.');
    });

    it('tells editors about a new post once it is saved, but not for a dry run', function (): void {
        newPost('Fresh news.')->save(dryRun: true);
        Mail::assertNothingSent();

        newPost('Fresh news.')->save();
        Mail::assertSent(NewPostMail::class, fn (NewPostMail $mail): bool => $mail->title === 'News' && $mail->hasTo('editors@example.com'));
    });
});

describe('a paginated list with filters', function (): void {
    beforeEach(function (): void {
        foreach (range(1, 23) as $n) {
            $post = lynx()->pages->new('post', '/blog');
            $post->name = "post-{$n}";
            $post->title = "Post number {$n}";
            $post->published_on = now()->subDays($n)->toDateString();
            $post->save();
        }

        View::prependLocation(ADVANCED.'/views');
        View::getFinder()->flush();
        Route::get('/posts', PostIndexController::class);
    });

    it('lists posts page by page', function (): void {
        $this->get('/posts')->assertOk()->assertSee('Page 1 of 3, 25 posts.');
        $this->get('/posts?page=3')->assertOk()->assertSee('Page 3 of 3, 25 posts.');
    });

    it('searches and sorts', function (): void {
        $this->get('/posts?q=hello')->assertOk()->assertSee('Hello, world')->assertSee('1 posts.');
        $this->get('/posts?sort=title')->assertOk()->assertSeeInOrder(['A second post', 'Hello, world', 'Post number 1']);
    });

    it('keeps visitors from breaking the selector', function (): void {
        $this->get('/posts?q='.urlencode('hello", template=page, limit=999 | x'))->assertOk();

        expect(fn () => $this->withoutExceptionHandling()->get('/posts?sort=-id'))->toThrow(ValidationException::class);
    });
});

it('sends a mail in the language the subscriber reads', function (): void {
    require BLOG.'/languages.php';
    Mail::fake();
    $subscriber = (object) ['email' => 'ana@example.com', 'language' => 'pt'];
    $post = blogPost('/blog/hello-world');

    require ADVANCED.'/language-mail.php';

    Mail::assertSent(NewPostMail::class, fn (NewPostMail $mail): bool => $mail->title === 'Olá, mundo' && $mail->url === '/pt/blogue/ola-mundo');
});

describe('members-only pages', function (): void {
    beforeEach(function (): void {
        require ADVANCED.'/members-only.php';
        View::prependLocation(BLOG.'/views');
        View::getFinder()->flush();
        $post = blogPost('/blog/second-post');
        $post->members_only = true;
        $post->save();
    });

    it('hides members-only posts from guests, and sends them to sign in at the URL', function (): void {
        $this->get('/blog')->assertOk()->assertSee('Hello, world')->assertDontSee('A second post');
        $this->get('/')->assertOk()->assertDontSee('A second post');
        $this->get('/blog/second-post')->assertRedirect('/login');
    });

    it('shows them to signed in users', function (): void {
        $this->actingAs(new GenericUser(['id' => 1]));

        $this->get('/blog')->assertOk()->assertSee('A second post');
        $this->get('/blog/second-post')->assertOk();
    });
});

describe('a custom field type', function (): void {
    beforeEach(function (): void {
        require ADVANCED.'/rating-register.php';
        lynx()->fields->add('rating', 'rating', settings: ['max' => 10]);
        lynx()->templates->update('post', ['add' => ['rating']]);
    });

    it('stores, reads and finds ratings', function (): void {
        $post = blogPost('/blog/hello-world');
        $post->rating = '8';
        $post->save();

        expect(blogPost('/blog/hello-world')->rating)->toBe(8)
            ->and(lynx()->pages->find('template=post, rating>=7')->ids())->toBe([blogPost('/blog/hello-world')->id])
            ->and(lynx()->fields->types()['rating']['settings'])->toHaveKey('max');
    });

    it('gives editors a number input for it', function (): void {
        $this->actingAs(new GenericUser(['id' => 1]));

        Livewire::test(PageEditor::class, ['pageId' => blogPost('/blog/hello-world')->id])
            ->assertSeeHtml('id="input-rating-default"')
            ->set('values.rating.default', '9')
            ->call('save')
            ->assertHasNoErrors();

        expect(blogPost('/blog/hello-world')->rating)->toBe(9);
    });

    it('refuses a rating above the field\'s maximum', function (): void {
        $post = blogPost('/blog/hello-world');
        $post->rating = 11;

        expect(fn (): mixed => $post->save())->toThrow(ValidationException::class);
    });
});
