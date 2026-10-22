<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Log};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/**
 * Every account on VENTIQ, for super admins: who they are, which
 * organization they belong to, and what role they have. Organizations
 * manage their own teams from their organizer area; this is for fixing
 * things, and for VENTIQ's own staff and agents.
 */
class UsersController extends Controller
{
    /** The roles, in the order they matter, with plain names. */
    private const ROLES = [
        'org_admin'   => 'Admin',
        'staff'       => 'Staff',
        'scanner'     => 'Door',
        'viewer'      => 'Viewer',
        'sales_agent' => 'Sales agent',
        'super_admin' => 'VENTIQ super admin',
    ];

    public function index(Request $request)
    {
        $table = AdminTable::for(User::query()->with(['roles', 'organization']), $request)
            ->search(fn ($q, $term) => $q->where('name', 'like', AdminTable::like($term))->orWhere('email', 'like', AdminTable::like($term)))
            ->sorts(['name' => 'name', 'created' => 'created_at', 'active' => 'last_active_at'], 'created')
            ->filters([
                'role'         => fn ($q, $v) => $v === 'none' ? $q->doesntHave('roles') : $q->role($v),
                'organization' => fn ($q, $v) => $v === 'none' ? $q->whereNull('organization_id') : $q->where('organization_id', (int) $v),
            ]);

        return Inertia::render('Ventiq/Users/Index', [
            'rows'  => $table->paginate()->through(fn (User $u) => $this->row($u)),
            'state' => $table->state(),
            'roles' => $this->roleOptions(),
            'meta'  => ['title' => 'Users | VENTIQ'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Ventiq/Users/Form', [
            'user'          => null,
            'roles'         => $this->roleOptions(),
            'organizations' => $this->organizations(),
            'organizationId' => $request->integer('organization') ?: null,
            'meta'          => ['title' => 'New user | VENTIQ'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'organization_id' => $data['organization_id'],
            'password' => Hash::make($data['password']), 'email_verified_at' => now(),
        ]);
        $user->syncRoles(array_filter([$data['role']]));
        Log::info("User {$user->id} created by super admin {$request->user()->id}", ['role' => $data['role']]);

        return redirect()->route('ventiq.users.edit', $user)->with('status', "{$user->name}'s account is ready. They can sign in with that email.");
    }

    public function edit(User $user)
    {
        return Inertia::render('Ventiq/Users/Form', [
            'user'          => $this->row($user->load(['roles', 'organization'])),
            'roles'         => $this->roleOptions(),
            'organizations' => $this->organizations(),
            'organizationId' => $user->organization_id,
            'meta'          => ['title' => "{$user->name} | VENTIQ"],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $self = $user->is($request->user());

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (!$self) {
            // Nobody changes their own access: a super admin can't lock themselves out.
            $user->organization_id = $data['organization_id'];
        }
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (!$self) {
            $user->syncRoles(array_filter([$data['role']]));
        }
        Log::info("User {$user->id} updated by super admin {$request->user()->id}", ['role' => $data['role']]);

        return back()->with('status', "{$user->name} is saved." . ($self && $data['role'] !== $user->roles->first()?->name ? " Your own role and organization weren't changed." : ''));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, "You can't delete your own account here.");
        $name = $user->name;
        $user->delete();
        Log::info("User {$user->id} deleted by super admin {$request->user()->id}");

        return redirect()->route('ventiq.users.index')->with('status', "{$name}'s account has been deleted.");
    }

    /** @return array<string, mixed> */
    private function row(User $u): array
    {
        $role = $u->roles->first()?->name;

        return [
            'id'           => $u->id,
            'name'         => $u->name,
            'email'        => $u->email,
            'avatar'       => \App\Support\Avatar::url($u->email),
            'role'         => $role,
            'roleLabel'    => $role ? (self::ROLES[$role] ?? $role) : null,
            'organization' => $u->organization ? ['id' => $u->organization->id, 'name' => $u->organization->name] : null,
            'verified'     => (bool) $u->email_verified_at,
            'google'       => (bool) $u->google_id,
            'lastActive'   => $u->last_active_at?->diffForHumans(),
            'created'      => $u->created_at?->format('j M Y'),
            'isYou'        => $u->is(request()->user()),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password'        => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'role'            => ['nullable', Rule::in(array_keys(self::ROLES))],
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        $existing = Role::pluck('name')->all();

        return collect(self::ROLES)->filter(fn ($label, $name) => in_array($name, $existing, true))
            ->map(fn ($label, $value) => compact('value', 'label'))->values()->all();
    }

    /** @return list<array{value: int, label: string}> */
    private function organizations(): array
    {
        return Organization::orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->name])->all();
    }
}
