<?php

namespace App\Filament\Resources\OrganizationalRecordResource\Pages;

use App\Filament\Resources\OrganizationalRecordResource;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreateOrganizationalRecord extends CreateRecord
{
    protected static string $resource = OrganizationalRecordResource::class;

    // Public properties so modals can write back to the form
    public string $selectedType  = 'meeting';
    public ?int   $selectedEvent = null;

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();

        return $form->schema([

            // Hidden fields — written by modals
            Components\Hidden::make('organization_id')->default($user->organization_id),
            Components\Hidden::make('created_by')->default($user->id),
            Components\Hidden::make('status')->default('draft'),
            Components\Hidden::make('record_type')->default('meeting'),
            Components\Hidden::make('event_id')->default(null),

            // ── Top config pills ──────────────────────────────────────────
            Components\Placeholder::make('config_bar')
                ->label('')
                ->content(new HtmlString('
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding-bottom:8px;">
                        <button type="button"
                            wire:click="mountAction(\'selectType\')"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
                                   background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:20px;
                                   font-size:13px;font-weight:600;color:#1D4069;cursor:pointer;">
                            <span>🗓️</span>
                            <span wire:ignore id="type-pill-label">{{ $this->getTypePillLabel() }}</span>
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <button type="button"
                            wire:click="mountAction(\'linkEvent\')"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
                                   background:#F0FDF4;border:1.5px solid #BBF7D0;border-radius:20px;
                                   font-size:13px;font-weight:600;color:#166534;cursor:pointer;">
                            🔗 <span>{{ $this->getEventPillLabel() }}</span>
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <span style="font-size:12px;color:#94a3b8;">
                            Optional — set before or after writing your notes
                        </span>
                    </div>
                '))
                ->columnSpanFull(),

            // ── Input mode toggle (meeting only) ──────────────────────────
            Components\ToggleButtons::make('input_mode')
                ->label('How do you have your notes?')
                ->options([
                    'dump'       => '🗒️  Dump everything',
                    'structured' => '📝  I have some structure',
                ])
                ->inline()
                ->default('dump')
                ->live()
                ->visible(fn (Get $get) => $get('record_type') === 'meeting')
                ->columnSpanFull(),

            // ── DUMP: single big textarea ─────────────────────────────────
            Components\Textarea::make('raw_input')
                ->label('')
                ->placeholder(
                    "Paste anything here — WhatsApp messages, bullet points, voice memo transcripts, half-sentences...\n\n" .
                    "Example:\n" .
                    "budget approved but transport still an issue\n" .
                    "john to get venue quotations before friday\n" .
                    "youth conference august 14th — pastor approved\n" .
                    "media team raised delays — sarah to follow up"
                )
                ->rows(14)
                ->required(fn (Get $get) => ($get('input_mode') ?? 'dump') === 'dump')
                ->visible(fn (Get $get) => ($get('input_mode') ?? 'dump') === 'dump')
                ->columnSpanFull(),

            // ── STRUCTURED: guided sections ───────────────────────────────
            Components\Placeholder::make('structured_hint')
                ->label('')
                ->content(new HtmlString('
                    <p style="font-size:13px;color:#64748b;margin:0 0 4px;">
                        Fill in whichever sections you have — leave the rest blank.
                        Ventiq Assist will work with whatever you provide.
                    </p>
                '))
                ->visible(fn (Get $get) => $get('input_mode') === 'structured')
                ->columnSpanFull(),

            Components\Textarea::make('structured_agenda')
                ->label('📋 What was on the agenda?')
                ->placeholder("• Budget review\n• Youth conference planning\n• Marketing update")
                ->rows(3)
                ->visible(fn (Get $get) => $get('input_mode') === 'structured')
                ->columnSpanFull(),

            Components\Textarea::make('structured_discussion')
                ->label('💬 What was discussed or decided?')
                ->placeholder("• Marketing budget approved\n• August 14th confirmed\n• John raised transport concern")
                ->rows(4)
                ->visible(fn (Get $get) => $get('input_mode') === 'structured')
                ->columnSpanFull(),

            Components\Textarea::make('structured_actions')
                ->label('⚡ Who needs to do what?')
                ->placeholder("• John to get venue quotations by Friday\n• Sarah to finalize marketing plan")
                ->rows(3)
                ->visible(fn (Get $get) => $get('input_mode') === 'structured')
                ->columnSpanFull(),

            Components\Textarea::make('structured_issues')
                ->label('⚠️ What\'s still unresolved?')
                ->placeholder("• Transport budget not confirmed\n• Venue not yet booked")
                ->rows(3)
                ->visible(fn (Get $get) => $get('input_mode') === 'structured')
                ->columnSpanFull(),

            // ── Optional metadata ─────────────────────────────────────────
            Components\Fieldset::make('Optional Details')
                ->schema([
                    Components\Grid::make(2)->schema([
                        Components\DatePicker::make('meeting_date')
                            ->label('Date')
                            ->native(false)
                            ->displayFormat('D, M j, Y')
                            ->default(today())
                            ->prefixIcon('heroicon-o-calendar'),

                        Components\TextInput::make('location')
                            ->label('Location / Venue')
                            ->placeholder('e.g. Maseru City Hall')
                            ->prefixIcon('heroicon-o-map-pin'),
                    ]),

                    Components\TextInput::make('title')
                        ->label('Title')
                        ->placeholder('Leave blank — Ventiq Assist will suggest one from your notes')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->columns(1),
        ]);
    }

    // ── Modal actions ─────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();

        return [
            // Modal 1 — Record type selector
            Action::make('selectType')
                ->label($this->getTypePillLabel())
                ->modalHeading('What are you capturing?')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Confirm')
                ->form([
                    Components\Radio::make('record_type')
                        ->label('')
                        ->options([
                            'meeting'       => '🗓️  Meeting',
                            'brainstorming' => '💡  Brainstorming Session',
                            'planning'      => '📋  Planning Session',
                            'report'        => '📊  Operational Report',
                            'update'        => '📣  Ministry / Department Update',
                            'committee'     => '⚖️  Committee Notes',
                        ])
                        ->descriptions([
                            'meeting'       => 'Formal or informal — agenda, decisions, action items.',
                            'brainstorming' => 'Ideas session — messy, open-ended, no formal resolutions needed.',
                            'planning'      => 'Focused on timelines, tasks, and preparation.',
                            'report'        => 'Status update or operational report.',
                            'update'        => 'Ministry, department, or team update.',
                            'committee'     => 'Formal resolutions and voting outcomes.',
                        ])
                        ->default($this->selectedType)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->selectedType = $data['record_type'];
                    // Write back into the form
                    $this->form->fill([
                        ...$this->form->getState(),
                        'record_type' => $data['record_type'],
                    ]);
                }),

            // Modal 2 — Event linker
            Action::make('linkEvent')
                ->label($this->getEventPillLabel())
                ->modalHeading('Link to a Ventiq Event')
                ->modalDescription('Optional — pulls in date, venue and attendance automatically.')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Link Event')
                ->form([
                    Components\Select::make('event_id')
                        ->label('Event')
                        ->placeholder('Search for an event...')
                        ->options(fn () => Event::when(
                                !auth()->user()->isSuperAdmin(),
                                fn ($q) => $q->where('organization_id', auth()->user()->organization_id)
                            )
                            ->orderBy('event_date', 'desc')
                            ->limit(50)
                            ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->live()
                        ->default($this->selectedEvent),

                    Components\Placeholder::make('event_preview')
                        ->label('')
                        ->content(function (Get $get) {
                            $eventId = $get('event_id');
                            if (!$eventId) return null;

                            $event     = Event::find($eventId);
                            if (!$event) return null;

                            $checkedIn = $event->tickets()->whereNotNull('checked_in_at')->count();
                            $total     = $event->tickets()->count();

                            return new HtmlString("
                                <div style='padding:12px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;font-size:13px;color:#0c4a6e;'>
                                    ✅ <strong>{$event->name}</strong><br>
                                    📅 " . ($event->event_date?->format('d M Y') ?? 'Date TBC') . "
                                    " . ($event->venue ? " · 📍 {$event->venue}" : '') . "
                                    " . ($total > 0 ? " · 👥 {$checkedIn}/{$total} checked in" : '') . "
                                </div>
                            ");
                        })
                        ->visible(fn (Get $get) => (bool) $get('event_id')),

                    Components\Placeholder::make('no_event_note')
                        ->label('')
                        ->content(new HtmlString('
                            <p style="font-size:12px;color:#94a3b8;margin:8px 0 0;">
                                Leave blank if this record is not related to a specific event.
                            </p>
                        ')),
                ])
                ->action(function (array $data): void {
                    $this->selectedEvent = $data['event_id'] ?? null;

                    $current = $this->form->getState();

                    // Auto-fill date and location from event
                    if ($this->selectedEvent) {
                        $event = Event::find($this->selectedEvent);
                        if ($event) {
                            $current['title']        = $current['title'] ?: $event->name;
                            $current['meeting_date'] = $event->event_date?->format('Y-m-d');
                            $current['location']     = $current['location'] ?: ($event->venue ?? $event->location);
                        }
                    }

                    $this->form->fill([
                        ...$current,
                        'event_id' => $this->selectedEvent,
                    ]);
                }),
        ];
    }

    // ── Pill label helpers ────────────────────────────────────────────────────

    public function getTypePillLabel(): string
    {
        return match($this->selectedType) {
            'brainstorming' => '💡 Brainstorming',
            'planning'      => '📋 Planning',
            'report'        => '📊 Report',
            'update'        => '📣 Update',
            'committee'     => '⚖️ Committee',
            default         => '🗓️ Meeting',
        };
    }

    public function getEventPillLabel(): string
    {
        if (!$this->selectedEvent) return '🔗 Link to Event';
        $event = Event::find($this->selectedEvent);
        return $event ? "🔗 {$event->name}" : '🔗 Link to Event';
    }

    // ── Mutate before save ────────────────────────────────────────────────────

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Merge structured fields into raw_input
        if (($data['input_mode'] ?? 'dump') === 'structured') {
            $parts = [];
            if (!empty($data['structured_agenda']))     $parts[] = "AGENDA:\n" . $data['structured_agenda'];
            if (!empty($data['structured_discussion'])) $parts[] = "DISCUSSION & DECISIONS:\n" . $data['structured_discussion'];
            if (!empty($data['structured_actions']))    $parts[] = "ACTION ITEMS:\n" . $data['structured_actions'];
            if (!empty($data['structured_issues']))     $parts[] = "OPEN ISSUES:\n" . $data['structured_issues'];
            $data['raw_input'] = implode("\n\n", $parts) ?: '(No notes provided)';
        }

        if (empty($data['raw_input'])) {
            $data['raw_input'] = '(No notes provided)';
        }

        // Stamp the selected type from component property
        // (Hidden field may not always carry through)
        $data['record_type'] = $this->selectedType ?: ($data['record_type'] ?? 'meeting');
        $data['event_id']    = $this->selectedEvent ?: ($data['event_id'] ?? null);

        // Remove fields that don't belong on the model
        unset(
            $data['structured_agenda'],
            $data['structured_discussion'],
            $data['structured_actions'],
            $data['structured_issues'],
            $data['input_mode'],
        );

        return $data;
    }

    // ── After create ──────────────────────────────────────────────────────────

    protected function afterCreate(): void
    {
        OrganizationalRecordResource::dispatchExtraction($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Save & Extract with Ventiq Assist ✨')
                ->submit('create')
                ->color('success'),

            Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}