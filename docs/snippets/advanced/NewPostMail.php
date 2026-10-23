<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use LynxCms\Core\Pages\Page;

// #region mailable
/** Tells a subscriber about a new post, in the language they read. */
final class NewPostMail extends Mailable
{
    public string $title;

    public string $url;

    /**
     * Reads the post when the mail is made, in the language active then: a queued mail is sent
     * later by a worker, outside that language.
     */
    public function __construct(Page $post)
    {
        $this->title = (string) $post->title;
        $this->url = $post->localUrl(lynx()->languages->current());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<a href="'.e($this->url).'">'.e($this->title).'</a>');
    }
}
// #endregion mailable
