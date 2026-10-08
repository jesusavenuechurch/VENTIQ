<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\IntentRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.login', [
            // No intent: IntentRedirect works out where this person belongs.
            'intent' => in_array($request->query('intent'), ['session', 'host'], true) ? $request->query('intent') : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'intent'   => 'nullable|string|in:session,host',
        ]);

        if (!Auth::guard('web')->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => "Those credentials don't match an account.",
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'status'   => 'success',
            'redirect' => IntentRedirect::resolve($validated['intent'] ?? null),
        ]);
    }
}