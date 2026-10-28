<?php

use App\Console\Commands\SendActionPointReminders;
use App\Mail\ActionPointsReminderMail;
use App\Models\{ActionPoint, Client, Organization, Session, User};
use App\Services\{SessionQuotaService, WhatsAppCloudService};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->user = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Thabo Lebona', 'email' => 'thabo@example.com']);
    $this->user->assignRole('staff');
    $this->session = Session::create(['organization_id' => $this->org->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => '2026-10-20', 'status' => 'reported']);
    $this->point = fn (array $owner, string $task = 'Send the quotes') => ActionPoint::create($owner + ['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => $task, 'status' => 'open', 'due_date' => '2026-10-21']);
    $this->quota = function (bool $left = true) {
        $this->mock(SessionQuotaService::class, function ($m) use ($left) {
            $m->shouldReceive('hasWhatsappQuota')->andReturn($left);
            $m->shouldReceive('consumeWhatsapp')->zeroOrMoreTimes();
        });
    };
});

it('lets a team member choose WhatsApp, with a number, on their account page', function () {
    $this->actingAs($this->user)->get(route('account.edit'))->assertOk();
    $this->put(route('account.reminders'), ['via' => 'whatsapp'])->assertSessionHasErrors('phone');
    $this->put(route('account.reminders'), ['via' => 'whatsapp', 'phone' => '123'])->assertSessionHasErrors('phone');
    $this->put(route('account.reminders'), ['via' => 'whatsapp', 'phone' => '5949 4756'])->assertRedirect();

    expect($this->user->fresh())->reminders_via->toBe('whatsapp')->phone->toBe('+26659494756');

    // Back to email keeps the number for next time.
    $this->put(route('account.reminders'), ['via' => 'email']);
    expect($this->user->fresh())->reminders_via->toBe('email')->phone->toBe('+26659494756');
});

it('sends reminders on WhatsApp to those who chose it, and to attendees with a phone, using the plan\'s messages', function () {
    $this->user->update(['reminders_via' => 'whatsapp', 'phone' => '+26659494756']);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Mpho Nkosi', 'phone' => '+26658111222', 'email' => 'mpho@example.com']);
    ($this->point)(['owner_user_id' => $this->user->id]);
    ($this->point)(['owner_user_id' => $this->user->id], 'Book the hall');
    ($this->point)(['owner_client_id' => $client->id], 'Bring the register');
    ($this->quota)();
    $this->mock(WhatsAppCloudService::class, function ($m) {
        $m->shouldReceive('sendActionReminder')->once()->with('+26659494756', 'Thabo Lebona', 'Maloti Water', 'Send the quotes (due today); Book the hall (due today)')->andReturn(true);
        $m->shouldReceive('sendActionReminder')->once()->with('+26658111222', 'Mpho Nkosi', 'Maloti Water', 'Bring the register (due today)')->andReturn(true);
    });

    $this->artisan('sessions:action-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
    expect(ActionPoint::whereNull('reminded_at')->count())->toBe(0);
});

it('falls back to email when WhatsApp cannot deliver: not approved yet, no messages left, or no number', function () {
    $this->user->update(['reminders_via' => 'whatsapp', 'phone' => '+26659494756']);
    ($this->point)(['owner_user_id' => $this->user->id]);
    ($this->quota)(false);
    $this->mock(WhatsAppCloudService::class, fn ($m) => $m->shouldNotReceive('sendActionReminder'));
    $this->artisan('sessions:action-reminders');
    Mail::assertQueued(ActionPointsReminderMail::class, fn ($m) => $m->hasTo('thabo@example.com'));

    // The template isn't approved: the send fails, so email.
    $this->travelTo(Carbon::parse('2026-10-23 08:00'));
    ($this->point)(['owner_user_id' => $this->user->id], 'Due on the 23rd')->update(['due_date' => '2026-10-23']);
    ($this->quota)();
    $this->mock(WhatsAppCloudService::class, fn ($m) => $m->shouldReceive('sendActionReminder')->andReturn(false));
    $this->artisan('sessions:action-reminders');
    Mail::assertQueued(ActionPointsReminderMail::class, 2);
});

it('keeps the WhatsApp list on one line, and short', function () {
    $items = [['task' => "Send\nthe   quotes", 'when' => 'due 12 Oct', 'overdue' => true]];
    expect(SendActionPointReminders::oneLine($items))->toBe('Send the quotes (overdue, due 12 Oct)');

    $many = array_fill(0, 40, ['task' => str_repeat('a', 30), 'when' => 'due today', 'overdue' => false]);
    $line = SendActionPointReminders::oneLine($many);
    expect(mb_strlen($line))->toBeLessThan(760)->and($line)->toEndWith('more');

    $huge = [['task' => str_repeat('b', 900), 'when' => 'due today', 'overdue' => false]];
    expect(mb_strlen(SendActionPointReminders::oneLine($huge)))->toBeLessThan(700);
});
