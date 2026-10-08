<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginCodeService;
use App\Support\IntentRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Email-first sign in. The person types their email; accounts that sign
 * in with a password are asked for it, everyone else (Google sign-ups, and
 * emails with no account) is sent a code. A code can always be asked for
 * instead of the password, which is also how someone who forgot their
 * password gets back in.
 */
class EmailLoginController extends Controller
{
    public function __construct(private LoginCodeService $codes) {}

    public function start(Request $request)
    {
        $data = $request->validate([
            'email'  => ['required', 'email', 'max:255'],
            'intent' => ['nullable', 'string', 'in:session,host'],
        ]);

        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if ($user && !$user->google_id) {
            return response()->json(['next' => 'password']);
        }

        return $this->sendCode($request, $data);
    }

    public function send(Request $request)
    {
        return $this->sendCode($request, $request->validate([
            'email'  => ['required', 'email', 'max:255'],
            'intent' => ['nullable', 'string', 'in:session,host'],
        ]));
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email'  => ['required', 'email'],
            'code'   => ['required', 'string', 'max:12'],
            'intent' => ['nullable', 'string', 'in:session,host'],
        ]);

        $user = $this->codes->verifyCode($data['email'], $data['code']);

        if (!$user) {
            return response()->json(['message' => 'That code isn\'t right, or it has expired. Check the latest email, or send a new code.'], 422);
        }

        return response()->json(['redirect' => $this->signIn($request, $user, $data['intent'] ?? null)]);
    }

    /**
     * The emailed button lands here. It asks for a tap rather than signing
     * in on the GET, because mail scanners open links before people do and
     * would use up the one-time link.
     */
    public function showLink(int $id, string $token)
    {
        $login = $this->codes->findLink($id, $token);

        return view('auth.login-link', ['login' => $login, 'id' => $id, 'token' => $token]);
    }

    public function useLink(Request $request, int $id, string $token)
    {
        $intent = $this->codes->findLink($id, $token)?->intent;
        $user = $this->codes->consumeLink($id, $token);

        if (!$user) {
            return redirect()->route('login')->with('status', 'That sign-in link has expired or was already used. Enter your email to get a new one.');
        }

        return redirect()->to($this->signIn($request, $user, $intent));
    }

    private function sendCode(Request $request, array $data)
    {
        if (!$this->codes->send($data['email'], $data['intent'] ?? null, $request->ip())) {
            return response()->json(['message' => 'We just sent a code. Wait a minute before asking for another, and check your spam folder.'], 429);
        }

        return response()->json(['next' => 'code']);
    }

    private function signIn(Request $request, User $user, ?string $intent): string
    {
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        // Proof they own the inbox: for a while they can set a new
        // password without the old one (AccountController).
        $request->session()->put('signed_in_with_code_at', now()->timestamp);

        return IntentRedirect::resolve($intent);
    }
}
