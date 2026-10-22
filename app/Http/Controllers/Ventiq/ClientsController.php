<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Every attendee contact across organizations, for super admins looking someone up. */
class ClientsController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(Client::query()->with('organization')->withCount('tickets'), $request)
            ->search(function ($q, $term) {
                $digits = preg_replace('/\D/', '', $term);
                $q->where('full_name', 'like', AdminTable::like($term))->orWhere('email', 'like', AdminTable::like($term))
                    ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('phone', 'like', '%' . $digits . '%'));
            })
            ->sorts(['name' => 'full_name', 'created' => 'created_at', 'tickets' => 'tickets_count'], 'created')
            ->filters([
                'organization' => fn ($q, $v) => $q->where('organization_id', (int) $v),
            ]);

        return Inertia::render('Ventiq/Clients', [
            'rows'  => $table->paginate()->through(fn (Client $c) => [
                'id'           => $c->id,
                'name'         => $c->full_name,
                'phone'        => $c->phone,
                'email'        => $c->email,
                'organization' => ['id' => $c->organization_id, 'name' => $c->organization?->name],
                'tickets'      => $c->tickets_count,
                'created'      => $c->created_at?->format('j M Y'),
            ]),
            'state' => $table->state(),
            'meta'  => ['title' => 'Clients | VENTIQ'],
        ]);
    }
}
