<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Mail\AgentApplicationReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AgentApplicationController extends Controller
{
    public function showForm()
    {
        return \Inertia\Inertia::render('Agent/Apply', ['meta' => ['title' => 'Become an agent | VENTIQ', 'description' => 'Introduce organizations to VENTIQ and earn for it.']]);
    }

    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:agents,email',
            'phone' => 'required|string|unique:agents,phone',
            'city_district' => 'required|string|max:255',
            'access_types' => 'required|array|min:1',
            'access_types.*' => 'in:Churches,Schools,Businesses,Event Planners',
            'motivation' => 'required|string|min:50|max:500',
        ], [
            'motivation.min' => 'Tell us a little more: at least 50 characters.',
            'email.unique' => 'Someone has already applied with this email.',
            'phone.unique' => 'Someone has already applied with this number.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            // Mapping frontend labels to DB-friendly slugs
            $typeMap = [
                'Churches' => 'churches',
                'Schools' => 'schools',
                'Businesses' => 'businesses',
                'Event Planners' => 'event_planners'
            ];

            $normalizedAccessTypes = array_map(fn($type) => $typeMap[$type], $request->access_types);

            // Create the record
            $agent = Agent::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'city_district' => $request->city_district,
                'access_types' => $normalizedAccessTypes,
                'motivation' => $request->motivation,
                'status' => 'pending',
                'is_active' => false,
            ]);

            // Attempt to send the receipt email immediately
            try {
                Mail::to($agent->email)->send(new AgentApplicationReceived($agent));
            } catch (\Exception $e) {
                // We log the error but allow the submission to complete 
                // Alternatively, throw an error here to rollback the DB if email is critical
                \Log::error("Failed to send agent receipt: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'PROTOCOL_SYNC_COMPLETE'
            ]);
        });
    }

    public function showResetForm(Request $request, $token)
    {
        return \Inertia\Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'meta'  => ['title' => 'Choose a password | VENTIQ'],
        ])->withViewData(['noindex' => true]);
    }

    /**
     * Set a password from an emailed reset link. The broker checks the
     * token belongs to this email and hasn't expired or been used; nothing
     * changes otherwise.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $signedIn = null;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$signedIn) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $signedIn = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'That link has expired or was already used. Ask for a new one from the sign-in page.']);
        }

        Auth::login($signedIn);
        $request->session()->regenerate();

        return redirect()->to(\App\Support\IntentRedirect::resolve(null));
    }

}