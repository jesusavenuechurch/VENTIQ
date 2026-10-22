<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AccountProvisioningService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AgentRegistrationController extends Controller
{
    public function __construct(protected AccountProvisioningService $provisioning) {}

    public function showForm(string $token = null)
    {
        $agent = $token ? $this->resolveAgent($token) : null;

        return \Inertia\Inertia::render('Auth/Register', [
            'agent'     => $agent ? ['name' => $agent->name] : null,
            'submitUrl' => $agent ? route('agent.registration.submit', $agent->referral_token) : route('org.register.submit'),
            'meta'      => ['title' => 'Create an account | VENTIQ', 'description' => 'Sell tickets and run registrations for your events in Lesotho. No monthly fee.'],
        ]);
    }

    public function submit(Request $request, string $token = null)
    {
        $agent = $token ? $this->resolveAgent($token) : null;

        $validated = $request->validate([
            'org_name'      => 'required|string|max:255',
            'org_phone'     => 'required|string|max:20',
            'org_district'  => 'nullable|string|max:255',
            'user_name'     => 'required|string|max:255',
            'user_email'    => 'required|email|max:255|unique:users,email',
            'user_password' => ['required', 'confirmed', Password::defaults()],
            'tagline'       => 'nullable|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'email'         => 'nullable|email|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        try {
            // Transaction + rollback-on-failure now lives inside the
            // service (DB::transaction handles it) — nothing left for
            // this controller to manage but the HTTP response.
            $user = $this->provisioning->provision($validated, $agent);

            event(new Registered($user));
            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();

            return response()->json([
                'status'   => 'success',
                'message'  => 'Welcome to VENTIQ! Please check your email to verify your account.',
                'redirect' => route('verification.notice'),
            ]);
        } catch (\Exception $e) {
            // The reason stays in the log: it can hold database details.
            report($e);

            return response()->json(['status' => 'error', 'message' => "We couldn't create your account just now. Please try again in a moment."], 422);
        }
    }

    private function resolveAgent(string $token): ?Agent
    {
        return Agent::where('referral_token', $token)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->first();
    }
}