<?php

namespace App\Services\Auth;

use App\Mail\{LoginCodeMail, NoAccountMail};
use App\Models\{LoginCode, User};
use Illuminate\Support\Facades\{DB, Hash, Log, Mail, RateLimiter};
use Illuminate\Support\Str;

/**
 * Sign in without a password: a 6-digit code, or a one-tap link, sent to
 * the person's email. The code and link work once, for ten minutes, with
 * five tries at the code. Sending is rate limited per email and per IP so
 * the form can't be used to flood someone's inbox.
 *
 * An email with no account gets a short "no account here" message instead
 * of a code, and the caller answers the same either way, so the form
 * doesn't reveal who has an account.
 */
class LoginCodeService
{
    public const TTL_MINUTES = 10;

    /** @return bool false when sending is rate limited */
    public function send(string $email, ?string $intent, ?string $ip): bool
    {
        $email = strtolower(trim($email));
        $keys = [
            'login-code:min:' . $email  => [1, 60],      // one a minute per email
            'login-code:hour:' . $email => [5, 3600],    // five an hour per email
            'login-code:ip:' . $ip      => [20, 3600],   // twenty an hour per IP
        ];

        foreach ($keys as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }
        foreach ($keys as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            Mail::to($email)->send(new NoAccountMail($email, $intent));
            Log::info('Sign-in code asked for an email with no account', ['ip' => $ip]);
            return true;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $link = Str::random(48);

        $login = DB::transaction(function () use ($user, $code, $link, $intent, $ip) {
            // Only the newest code works.
            LoginCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

            return LoginCode::create([
                'user_id'    => $user->id,
                'code_hash'  => Hash::make($code),
                'link_hash'  => hash('sha256', $link),
                'intent'     => $intent,
                'ip'         => $ip,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });

        Mail::to($user->email)->send(new LoginCodeMail($user, $code, route('login.link.show', [$login->id, $link])));
        Log::info("Sign-in code sent to user {$user->id}", ['ip' => $ip]);

        return true;
    }

    /** The user the code signs in, or null if it's wrong, used, expired or out of tries. */
    public function verifyCode(string $email, string $code): ?User
    {
        $user = User::where('email', strtolower(trim($email)))->first();
        if (!$user) {
            return null;
        }

        return DB::transaction(function () use ($user, $code) {
            $login = LoginCode::where('user_id', $user->id)->whereNull('used_at')->latest('id')->lockForUpdate()->first();

            if (!$login || !$login->isUsable()) {
                return null;
            }

            if (!Hash::check(preg_replace('/\D/', '', $code), $login->code_hash)) {
                $login->increment('attempts');
                return null;
            }

            return $this->consume($login);
        });
    }

    /** The code row a link points at, if the link is still good. */
    public function findLink(int $id, string $token): ?LoginCode
    {
        $login = LoginCode::with('user')->find($id);

        return $login && $login->isUsable() && hash_equals($login->link_hash, hash('sha256', $token)) ? $login : null;
    }

    public function consumeLink(int $id, string $token): ?User
    {
        return DB::transaction(function () use ($id, $token) {
            $login = LoginCode::whereKey($id)->lockForUpdate()->first();

            return $login && $login->isUsable() && hash_equals($login->link_hash, hash('sha256', $token))
                ? $this->consume($login)
                : null;
        });
    }

    private function consume(LoginCode $login): User
    {
        $login->update(['used_at' => now()]);
        $user = $login->user;

        // They've just shown they can read this inbox.
        if (!$user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
