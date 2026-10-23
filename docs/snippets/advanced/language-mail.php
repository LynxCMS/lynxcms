<?php

declare(strict_types=1);

use App\Mail\NewPostMail;
use Illuminate\Support\Facades\Mail;

// $subscriber has an email and a language code, such as "pt".

// #region send
lynx()->language($subscriber->language, function () use ($subscriber, $post) {
    Mail::to($subscriber->email)->send(new NewPostMail($post));
});
// #endregion send
