<?php
// app/Imports/TicketsImport.php

namespace App\Imports;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\Event;
use App\Models\EventTier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TicketsImport implements ToCollection, WithHeadingRow
{
    protected $eventId;
    protected $tierId;
    protected $isComplimentary;
    protected $reason;
    protected $organizationId;
    protected $createdBy;
    
    public $successCount = 0;
    public $errorCount = 0;
    public $errors = [];

    public function __construct(
        int $eventId,
        int $tierId,
        int $organizationId,
        int $createdBy,
        bool $isComplimentary = false,
        ?string $reason = null
    ) {
        $this->eventId = $eventId;
        $this->tierId = $tierId;
        $this->organizationId = $organizationId;
        $this->createdBy = $createdBy;
        $this->isComplimentary = $isComplimentary;
        $this->reason = $reason;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because: 1 for header, 1 for 0-based index
            
            try {
                DB::beginTransaction();

                // Convert row to array for easier access
                $rowData = $row->toArray();
                
                // Try different possible column name variations
                $fullName = $rowData['full_name'] ?? $rowData['fullname'] ?? $rowData['name'] ?? null;
                $phone = $rowData['phone'] ?? $rowData['phone_number'] ?? $rowData['mobile'] ?? null;
                $email = $rowData['email'] ?? $rowData['email_address'] ?? null;
                $hasWhatsApp = $rowData['has_whatsapp'] ?? $rowData['whatsapp'] ?? false;

                // Debug log
                Log::info("Processing row {$rowNumber}", [
                    'row_keys' => array_keys($rowData),
                    'full_name' => $fullName,
                    'phone' => $phone,
                ]);

                // Validate required fields (ONLY full_name is required)
                if (empty($fullName)) {
                    throw new \Exception("Missing full_name. Available columns: " . implode(', ', array_keys($rowData)));
                }

                // A row without a usable number is reported, never guessed:
                // this used to invent +266 numbers and send them tickets.
                $phone = \App\Support\Phone::normalize($phone);
                if (!$phone) {
                    throw new \Exception('Missing or invalid phone number');
                }

                // Clients belong to one organization; never reuse another's.
                $client = Client::firstOrCreate(
                    ['phone' => $phone, 'organization_id' => $this->organizationId],
                    [
                        'full_name' => $fullName,
                        'email' => $email,
                    ]
                );

                if (!$client->email && $email) {
                    $client->update(['email' => $email]);
                }

                // Check if ticket already exists for this client & event
                $existingTicket = Ticket::where('client_id', $client->id)
                    ->where('event_id', $this->eventId)
                    ->where('event_tier_id', $this->tierId)
                    ->first();

                if ($existingTicket) {
                    throw new \Exception("Ticket already exists for this client at this event/tier");
                }

                // Convert WhatsApp to boolean
                if (is_string($hasWhatsApp)) {
                    $hasWhatsApp = in_array(strtolower($hasWhatsApp), ['true', '1', 'yes', 'y']);
                }

                // Create ticket
                $ticket = Ticket::create([
                    'event_id' => $this->eventId,
                    'client_id' => $client->id,
                    'event_tier_id' => $this->tierId,
                    'created_by' => $this->createdBy,
                    'has_whatsapp' => $hasWhatsApp,
                    'preferred_delivery' => $hasWhatsApp ? 'both' : 'email',
                ]);

                // Mark as complimentary if specified
                if ($this->isComplimentary) {
                    $ticket->markAsComplimentary(
                        $this->createdBy,
                        $this->reason ?? 'Bulk import - complimentary ticket'
                    );
                } else {
                    // For paid tickets, set initial payment status
                    $ticket->update([
                        'payment_status' => 'pending',
                        'status' => 'pending',
                    ]);
                }

                // Generate QR code
                $ticket->generateQrCode();

                // Complimentary tickets are delivered by Ticket's `updated`
                // hook when markAsComplimentary marks them paid.

                DB::commit();
                $this->successCount++;

                Log::info("Bulk import: Created ticket for {$client->full_name}", [
                    'row' => $rowNumber,
                    'ticket_id' => $ticket->id,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->errorCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'name' => $fullName ?? 'N/A',
                    'phone' => $phone ?? 'N/A',
                    'error' => $e->getMessage(),
                ];

                Log::error("Bulk import error on row {$rowNumber}", [
                    'error' => $e->getMessage(),
                    'data' => $row->toArray(),
                ]);
            }
        }
    }
}
