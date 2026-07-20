<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\OrganizationPaymentMethod;
use App\Filament\Resources\TicketResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;
use App\Jobs\SendTicketApprovedEmail;

/**
 * ============================================================
 * TICKETS, SCOPED TO ONE EVENT — not a global list anymore.
 *
 * This replaces TicketResource's tab in the cluster. Every action
 * here that used to ask "which event?" in a Select field no longer
 * needs to, because $this->getOwnerRecord() *is* the event — that's
 * the whole point of a relation manager. This simplifies the forms
 * compared to the original TicketResource header actions, it isn't
 * just a copy-paste of the same complexity into a new location.
 * ============================================================
 */
class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';
    protected static ?string $title = 'Tickets';

    public function form(Form $form): Form
    {
        // Kept intentionally minimal — most ticket editing here is
        // status/payment management via row actions below, not a
        // full recreate-the-ticket form. Client and tier are shown
        // read-only once a ticket exists; they're set at creation.
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\ToggleButtons::make('payment_status')
                    ->options(config('constants.payment_statuses'))
                    ->inline()
                    ->colors(['pending' => 'warning', 'partial' => 'info', 'completed' => 'success', 'failed' => 'danger']),

                Forms\Components\ToggleButtons::make('status')
                    ->options(['active' => 'Active', 'checked_in' => 'Checked In', 'refunded' => 'Refunded', 'void' => 'Void'])
                    ->inline()
                    ->colors(['active' => 'info', 'checked_in' => 'success', 'refunded' => 'danger']),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $event = $this->getOwnerRecord(); // the one event we're scoped to

        return $table
            ->recordTitleAttribute('ticket_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Guest')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->description(fn ($record) =>
                        $record->ticket_number . " • " . $record->tier->tier_name .
                        ($record->is_complimentary ? " 🎁 COMP" : "")
                    )->wrap(),

                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Entry Code')
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->money(config('constants.currency.code'))
                    ->weight(FontWeight::Black)
                    ->alignment(Alignment::Right)
                    ->visible(fn ($record) => !$record?->is_complimentary),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->is_complimentary ? 'Complimentary' : ucfirst($state))
                    ->color(fn ($state, $record) => $record->is_complimentary ? 'gray' : match ($state) {
                        'pending' => 'warning', 'completed' => 'success', 'failed' => 'danger', default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Access')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'info', 'checked_in' => 'success', 'refunded' => 'danger', default => 'gray',
                    }),
            ])
            ->headerActions([

                // Same feature as before, just no event picker — the
                // event is already fixed to whichever record we're on.
                Tables\Actions\Action::make('issue_complimentary')
                    ->label('Issue Comp Ticket')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->form(function () use ($event, $isSuperAdmin) {
                        $package = $isSuperAdmin ? null : TicketResource::packageForEvent($event->id);

                        return [
                            Forms\Components\Placeholder::make('comp_status')
                                ->label('')
                                ->content(function () use ($package, $isSuperAdmin) {
                                    if ($isSuperAdmin) return null;
                                    if (!$package) return null;
                                    $remaining = $package->remaining_comp_tickets;
                                    $color = $remaining <= 0 ? 'red' : ($remaining <= 3 ? 'orange' : 'green');
                                    return new HtmlString("
                                        <div class='p-3 bg-{$color}-50 border border-{$color}-200 rounded-lg text-sm text-{$color}-700'>
                                            🎁 <strong>{$remaining}</strong> comp ticket(s) remaining on this event's package.
                                        </div>
                                    ");
                                })
                                ->columnSpanFull(),

                            Forms\Components\Select::make('tier_id')
                                ->label('Ticket Tier')
                                ->options($event->tiers()->pluck('tier_name', 'id'))
                                ->required(),

                            Forms\Components\Section::make('Guest Info')
                                ->schema([
                                    Forms\Components\TextInput::make('full_name')->required(),
                                    Forms\Components\TextInput::make('email')->email(),
                                    Forms\Components\TextInput::make('phone')->tel()->prefix('+266'),
                                    Forms\Components\Toggle::make('has_whatsapp')->label('Send via WhatsApp')->default(true),
                                ])->columns(2),

                            Forms\Components\Textarea::make('reason')->label('Reason for Comp')->rows(2),
                        ];
                    })
                    ->action(function (array $data) use ($event, $isSuperAdmin, $user) {
                        if (!$isSuperAdmin) {
                            $package = TicketResource::packageForEvent($event->id);
                            if ($package && $package->remaining_comp_tickets <= 0) {
                                Notification::make()->title('No Comp Tickets Remaining')->danger()->send();
                                return;
                            }
                        }

                        DB::beginTransaction();
                        try {
                            $client = null;
                            if (!empty($data['phone'])) {
                                $client = Client::where('phone', $data['phone'])
                                    ->where('organization_id', $event->organization_id)->first();
                            }
                            if (!$client && !empty($data['email'])) {
                                $client = Client::where('email', $data['email'])
                                    ->where('organization_id', $event->organization_id)->first();
                            }
                            if (!$client) {
                                $client = Client::create([
                                    'full_name' => $data['full_name'],
                                    'phone' => $data['phone'] ?? null,
                                    'email' => $data['email'] ?? null,
                                    'organization_id' => $event->organization_id,
                                    'status' => 'active',
                                ]);
                            }

                            $ticket = Ticket::create([
                                'event_id' => $event->id,
                                'client_id' => $client->id,
                                'event_tier_id' => $data['tier_id'],
                                'created_by' => $user->id,
                                'is_complimentary' => true,
                                'amount' => 0,
                                'has_whatsapp' => $data['has_whatsapp'] ?? false,
                                'preferred_delivery' => $data['has_whatsapp'] ? 'both' : 'email',
                            ]);

                            $ticket->markAsComplimentary($user->id, $data['reason'] ?? 'Admin Issued');
                            $ticket->generateQrCode();

                            if (!$isSuperAdmin) {
                                TicketResource::packageForEvent($event->id)?->incrementCompTicketsUsed();
                            }

                            dispatch(fn () => $ticket->autoDeliverTicket())->afterResponse();

                            DB::commit();
                            Notification::make()->title('Comp Ticket Issued')->success()->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_payment')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->button()
                    ->visible(fn ($record) =>
                        in_array($record->payment_status, ['pending', 'partial'])
                        && auth()->user()?->can('approve_payment')
                        && $record->hasPendingPayments()
                    )
                    ->form(function ($record) {
                        $pendingPayment = $record->payments()->pending()->latest()->first();
                        if (!$pendingPayment) return [];

                        $paymentOptions = OrganizationPaymentMethod::where('organization_id', $record->event->organization_id)
                            ->where('is_active', true)->get()
                            ->mapWithKeys(fn ($m) => [$m->payment_method => config('constants.payment_methods.' . $m->payment_method . '.label', $m->payment_method)])
                            ->toArray();

                        return [
                            Forms\Components\Placeholder::make('pay')
                                ->label('To Approve')
                                ->content('M ' . number_format($pendingPayment->amount, 2)),
                            Forms\Components\Select::make('payment_method')
                                ->options($paymentOptions)
                                ->default($pendingPayment->payment_method)
                                ->required(),
                            Forms\Components\TextInput::make('payment_reference')
                                ->default($pendingPayment->payment_reference)
                                ->required(),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $pendingPayment = $record->payments()->pending()->latest()->first();
                        if (!$pendingPayment) return;

                        DB::beginTransaction();
                        try {
                            $pendingPayment->update([
                                'status' => 'approved',
                                'payment_method' => $data['payment_method'],
                                'payment_reference' => $data['payment_reference'],
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                            $record->update(['payment_status' => 'completed', 'status' => 'active']);

                            if ($record->payments()->approved()->count() === 1) {
                                $record->tier->increment('quantity_sold');
                            }
                            if ($record->client->email) {
                                dispatch(new SendTicketApprovedEmail($record->id))->afterResponse();
                            }
                            if ($record->has_whatsapp && $record->client->phone) {
                                dispatch(fn () => app(\App\Services\TicketDeliveryService::class)->deliver($record))->afterResponse();
                            }

                            Notification::make()->title('Payment Approved')->success()->send();
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Approval Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('preview_pass')
                    ->label('View Pass')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => route('ticket.download', $record->qr_code))
                    ->openUrlInNewTab(),

                // Edit as a slide-over — no navigation away from the
                // event page needed, keeps the "everything about this
                // event lives here" feeling you're going for.
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}