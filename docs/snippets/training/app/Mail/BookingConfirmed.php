<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// #region mail
/**
 * The booking confirmation, in the language the buyer booked in. Everything it shows is read in
 * that language when the mail is built, so it stays right when a queue worker sends it later.
 */
final class BookingConfirmed extends Mailable implements ShouldQueue
{
    use Queueable;

    public string $course;

    public string $when;

    public string $where;

    public function __construct(public Booking $booking)
    {
        $this->locale($booking->language);

        lynx()->language($booking->language, function () use ($booking): void {
            $session = $booking->session();
            $this->course = (string) $session?->parent()?->title;
            $this->when = (string) $session?->starts?->locale($booking->language)->isoFormat('LLLL');
            $this->where = (string) $session?->location?->title;
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your place on :course', ['course' => $this->course]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-confirmed');
    }
}
// #endregion mail
