<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

// #region account
/** The members area: the member's bookings, with a link to each paid course's materials. */
final class AccountController
{
    public function __invoke(Request $request): View
    {
        $bookings = Booking::query()->where('user_id', $request->user()->getAuthIdentifier())->latest()->get();

        return view('account.show', ['bookings' => $bookings, 'page' => lynx()->pages->get('/')]);
    }
}
// #endregion account
