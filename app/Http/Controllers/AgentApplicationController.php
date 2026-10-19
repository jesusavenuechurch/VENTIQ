<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Mail\AgentApplicationReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AgentApplicationController extends Controller
{
    public function showForm()
    {
        return view('agent.apply');
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
            'motivation.min' => 'Our vetting core requires a more detailed motivation (min 50 characters).',
            'email.unique' => 'This email protocol is already registered in our system.',
            'phone.unique' => 'This phone line is already linked to an existing application.',
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
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'User protocol not found.']);
        }

        // Secure the account
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Authorize immediately
        Auth::login($user);

        return redirect()->intended(route('agent.home'));
    }

}