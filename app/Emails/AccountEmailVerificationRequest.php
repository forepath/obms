<?php

declare(strict_types=1);

namespace App\Emails;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest as Request;

class AccountEmailVerificationRequest extends Request
{
    /**
     * Fulfill the email verification request.
     */
    public function fulfill()
    {
        if (! $this->user()->hasVerifiedEmail()) {
            $this->user()->markEmailAsVerified();

            event(new Verified($this->user()));
        }
    }
}
