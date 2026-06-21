<?php

namespace App\Filament\Resources;

use App\Models\OrganizationalRecord;
use App\Models\RecordExtraction;
use App\Models\RecordActionItem;
use App\Models\RecordOpenIssue;
use App\Models\Event;
use App\Models\AiGenerationResult;
use App\Jobs\GenerateOrganizationalRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\OrganizationalRecordResource\Pages;

class OrganizationalRecordResource extends Resource
{
    protected static ?string $model = OrganizationalRecord::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Organisation';
    protected static ?string $navigationLabel = 'Records';
    protected static ?string $modelLabel      = 'Organisational Record';
    protected static ?int    $navigationSort  = 10;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isSalesAgent()) return false;
        if ($user->isSuperAdmin()) return true;
    
        // Must belong to an org with the feature enabled on any active package
        return $user->organization?->activePackages()
            ->get()
            ->contains(fn ($p) => $p->hasFeature('organizational_records'))
            ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();
        if ($user?->isSuperAdmin()) return $query;
        return $query->where('organization_id', $user->organization_id);
    }

    // ── FORM ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();

        return $form->schema([
            Forms\Components\Hidden::make('organization_id')
                ->default($user->organization_id),
            Forms\Components\Hidden::make('created_by')
                ->default($user->id),

            // ── PHASE A: Input ─────────────────────────────────────────────
            Forms\Components\Section::make('Record Details')
                ->description('Start by telling us what type of record this is and linking it to an event if relevant.')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\Select::make('record_type')
                            ->label('Record Type')
                            ->options([
                                'meeting'      => '🗓️ Meeting',
                                'brainstorming'=> '💡 Brainstorming',
                                'planning'     => '📋 Planning Session',
                                'report'       => '📊 Operational Report',
                                'update'       => '📣 Ministry / Department Update',
                                'committee'    => '⚖️ Committee Notes',
                            ])
                            ->default('meeting')
                            ->required()
                            ->live(),

                        Forms\Components\DatePicker::make('meeting_date')
                            ->label('Date')
                            ->native(false)
                            ->displayFormat('D, M j, Y')
                            ->default(today())
                            ->prefixIcon('heroicon-o-calendar'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\Select::make('event_id')
                            ->label('Link to Event (optional)')
                            ->placeholder('No event — standalone record')
                            ->options(fn () => Event::when(
                                    !$isSuperAdmin,
                                    fn ($q) => $q->where('organization_id', $user->organization_id)
                                )
                                ->orderBy('event_date', 'desc')
                                ->limit(50)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $event = Event::find($state);
                                    if ($event) {
                                        $set('title', $event->name);
                                        $set('location', $event->venue ?? $event->location);
                                    }
                                }
                            })
                            ->helperText('If linked, attendance is pulled from checked-in tickets automatically.'),

                        Forms\Components\TextInput::make('location')
                            ->label('Location / Venue')
                            ->placeholder('e.g. Maseru City Hall')
                            ->prefixIcon('heroicon-o-map-pin'),
                    ]),

                    Forms\Components\TextInput::make('title')
                        ->label('Title (optional)')
                        ->placeholder('Leave blank — Ventiq Assist will suggest one from your notes')
                        ->helperText('If linked to an event, the event name is used automatically.')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // ── RAW INPUT ──────────────────────────────────────────────────
            Forms\Components\Section::make('Your Notes')
                ->description('Paste or type anything — WhatsApp messages, handwritten notes, bullet points, partial sentences. Ventiq Assist will extract the structure.')
                ->schema([
                    Forms\Components\Textarea::make('raw_input')
                        ->label('')
                        ->placeholder(
                            "Example:\n\n" .
                            "budget approved but transport still issue\n" .
                            "john to ask venues for quotations\n" .
                            "youth conference august maybe 14th\n" .
                            "pastor says marketing should start early\n" .
                            "media team complained about delays"
                        )
                        ->rows(10)
                        ->required()
                        ->columnSpanFull(),
                ]),

            // ── PHASE B: Extracted results (visible only after extraction) ─
            Forms\Components\Section::make('Extracted Structure')
                ->description('Ventiq Assist extracted the following from your notes. Review and edit before finalizing.')
                ->visible(fn ($record) => $record && in_array($record->status, ['extracted', 'finalized']))
                ->schema([

                    // Attendance — only if event linked
                    Forms\Components\Placeholder::make('attendance_summary')
                        ->label('Attendance')
                        ->content(function ($record) {
                            if (!$record?->event_id) return null;
                            $att = $record->attendance;
                            if (!$att) return new HtmlString('<span class="text-gray-400 text-sm">No attendance data.</span>');

                            return new HtmlString("
                                <div class='flex gap-4 text-sm'>
                                    <div class='p-3 bg-green-50 border border-green-200 rounded-lg text-center'>
                                        <div class='text-xl font-bold text-green-700'>{$att->count}</div>
                                        <div class='text-xs text-green-600'>Checked In</div>
                                    </div>
                                    <div class='p-3 bg-red-50 border border-red-200 rounded-lg text-center'>
                                        <div class='text-xl font-bold text-red-700'>{$att->absent}</div>
                                        <div class='text-xs text-red-600'>Absent</div>
                                    </div>
                                    <div class='p-3 bg-gray-50 border border-gray-200 rounded-lg text-center'>
                                        <div class='text-xl font-bold text-gray-700'>{$att->total}</div>
                                        <div class='text-xs text-gray-500'>Total Tickets</div>
                                    </div>
                                </div>
                            ");
                        })
                        ->visible(fn ($record) => $record?->event_id !== null)
                        ->columnSpanFull(),

                    // Agenda
                    Forms\Components\Repeater::make('agendaItems')
                        ->label('📋 Agenda / Topics')
                        ->relationship('agendaItems')
                        ->schema([
                            Forms\Components\Hidden::make('category')->default('agenda'),
                            Forms\Components\Hidden::make('is_ai_generated')->default(true),
                            Forms\Components\TextInput::make('content')
                                ->label('')
                                ->required()
                                ->placeholder('Agenda item'),
                        ])
                        ->addActionLabel('Add agenda item')
                        ->collapsible()
                        ->columnSpanFull(),

                    // Discussion points
                    Forms\Components\Repeater::make('discussionItems')
                        ->label('💬 Key Discussion Points')
                        ->relationship('discussionPoints')
                        ->schema([
                            Forms\Components\Hidden::make('category')->default('discussion_point'),
                            Forms\Components\Hidden::make('is_ai_generated')->default(true),
                            Forms\Components\Textarea::make('content')
                                ->label('')
                                ->rows(2)
                                ->required()
                                ->placeholder('Discussion point'),
                        ])
                        ->addActionLabel('Add discussion point')
                        ->collapsible()
                        ->columnSpanFull(),

                    // Decisions
                    Forms\Components\Repeater::make('decisionItems')
                        ->label('✅ Decisions / Resolutions')
                        ->relationship('decisions')
                        ->schema([
                            Forms\Components\Hidden::make('category')->default('decision'),
                            Forms\Components\Hidden::make('is_ai_generated')->default(true),
                            Forms\Components\Textarea::make('content')
                                ->label('')
                                ->rows(2)
                                ->required()
                                ->placeholder('Decision or resolution'),
                        ])
                        ->addActionLabel('Add decision')
                        ->collapsible()
                        ->columnSpanFull(),

                    // Action items
                    Forms\Components\Repeater::make('actionItems')
                        ->label('⚡ Action Items')
                        ->relationship('actionItems')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Action')
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('assigned_to_name')
                                    ->label('Assigned To')
                                    ->placeholder('Person responsible')
                                    ->columnSpan(1),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->native(false)
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->addActionLabel('Add action item')
                        ->collapsible()
                        ->columnSpanFull(),

                    // Open issues
                    Forms\Components\Repeater::make('openIssues')
                        ->label('⚠️ Open Issues / Pending Matters')
                        ->relationship('openIssues')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Textarea::make('description')
                                    ->label('Issue')
                                    ->rows(2)
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('raised_by')
                                    ->label('Raised By')
                                    ->placeholder('Who raised this?')
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->addActionLabel('Add open issue')
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            // ── Final output (visible only when finalized) ─────────────────
            Forms\Components\Section::make('Generated Document')
                ->visible(fn ($record) => $record?->status === 'finalized')
                ->schema([
                    Forms\Components\Textarea::make('final_output')
                        ->label('')
                        ->rows(20)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ── TABLE ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('resolved_title')
                    ->label('Record')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable(query: fn ($query, $search) =>
                        $query->where('title', 'like', "%{$search}%")
                              ->orWhere('raw_input', 'like', "%{$search}%")
                    )
                    ->description(fn ($record) =>
                        ucfirst($record->record_type) .
                        ($record->meeting_date ? ' · ' . $record->meeting_date->format('d M Y') : '') .
                        ($record->event ? ' · ' . $record->event->name : '')
                    )
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'extracted',
                        'success' => 'finalized',
                    ]),

                Tables\Columns\TextColumn::make('record_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('actionItems_count')
                    ->label('Actions')
                    ->counts('actionItems')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('openIssues_count')
                    ->label('Open Issues')
                    ->counts('openIssues')
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('record_type')
                    ->options([
                        'meeting'       => 'Meeting',
                        'brainstorming' => 'Brainstorming',
                        'planning'      => 'Planning Session',
                        'report'        => 'Operational Report',
                        'update'        => 'Ministry Update',
                        'committee'     => 'Committee Notes',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'extracted' => 'Extracted',
                        'finalized' => 'Finalized',
                    ]),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn () => Event::when(
                            !auth()->user()->isSuperAdmin(),
                            fn ($q) => $q->where('organization_id', auth()->user()->organization_id)
                        )
                        ->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('extract')
                    ->label('Extract')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->button()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(fn ($record) => static::dispatchExtraction($record)),

                Tables\Actions\Action::make('finalize')
                    ->label('Finalize & Generate')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->button()
                    ->visible(fn ($record) => $record->status === 'extracted')
                    ->action(fn ($record) => static::generateFinalDocument($record)),

                Tables\Actions\Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button()
                    ->visible(fn ($record) => $record->status === 'finalized')
                    ->url(fn ($record) => route('organizational-records.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()->slideOver(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->label('More'),
            ]);
    }

    // ── Dispatch extraction job ───────────────────────────────────────────────

    public static function dispatchExtraction(OrganizationalRecord $record): void
    {
        $user    = auth()->user();
        $jobId   = (string) Str::uuid();
        $org     = $record->organization;
        $event   = $record->event;

        // Create the tracking result — same pattern as event description
        AiGenerationResult::create([
            'job_id'  => $jobId,
            'user_id' => $user->id,
            'type'    => 'organizational_record',
            'status'  => 'pending',
            'payload' => json_encode(['record_id' => $record->id]),
        ]);

        // Stamp the job id so the edit page can poll
        $record->update(['extraction_job_id' => $jobId]);

        GenerateOrganizationalRecord::dispatch(
            jobId:      $jobId,
            userId:     $user->id,
            recordId:   $record->id,
            promptData: [
                'raw_input'    => $record->raw_input,
                'record_type'  => $record->record_type,
                'organisation' => $org?->name ?? '',
                'event_name'   => $event?->name ?? '',
                'date'         => $record->meeting_date?->format('d F Y') ?? '',
                'venue'        => $record->location ?? $event?->venue ?? '',
            ],
        );

        Notification::make()
            ->title('Extracting with Ventiq Assist')
            ->body('This takes a few seconds. Refresh the page to see the results.')
            ->info()
            ->send();
    }

    // ── Generate final formatted document ────────────────────────────────────

    public static function generateFinalDocument(OrganizationalRecord $record): void
    {
        $agenda      = $record->agenda()->pluck('content')->map(fn ($c) => "• {$c}")->join("\n");
        $discussion  = $record->discussionPoints()->pluck('content')->map(fn ($c) => "• {$c}")->join("\n");
        $decisions   = $record->decisions()->pluck('content')->map(fn ($c) => "• {$c}")->join("\n");
        $actions     = $record->actionItems->map(fn ($a) =>
            "• {$a->description}" .
            ($a->assigned_to_name ? " — {$a->assigned_to_name}" : '') .
            ($a->due_date ? " — {$a->due_date->format('d M Y')}" : '')
        )->join("\n");
        $issues      = $record->openIssues->map(fn ($i) => "• {$i->description}")->join("\n");

        // Attendance block
        $attendanceBlock = '';
        if ($record->event_id && $record->attendance) {
            $att = $record->attendance;
            $names = $att->checked_in->map(fn ($t) => $t->client->full_name)->join(', ');
            $attendanceBlock = "\nATTENDANCE: {$att->count} of {$att->total} present\n{$names}\n";
        }

        $org   = $record->organization?->name ?? '';
        $title = $record->resolved_title;
        $date  = $record->meeting_date?->format('l, d F Y') ?? now()->format('l, d F Y');
        $venue = $record->location ?? 'Not specified';

        $output = <<<DOC
{$org}
{$title}
Date: {$date}
Venue: {$venue}
{$attendanceBlock}
AGENDA
{$agenda}

KEY DISCUSSION POINTS
{$discussion}

DECISIONS / RESOLUTIONS
{$decisions}

ACTION ITEMS
{$actions}

OPEN ISSUES / PENDING MATTERS
{$issues}

---
Generated by Ventiq Assist · {$org}
DOC;

        $record->update([
            'final_output' => $output,
            'status'       => 'finalized',
        ]);

        Notification::make()
            ->title('Document Finalized')
            ->body('Your record is ready. You can now download it as a PDF.')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizationalRecords::route('/'),
            'create' => Pages\CreateOrganizationalRecord::route('/create'),
            'edit'   => Pages\EditOrganizationalRecord::route('/{record}/edit'),
        ];
    }
}