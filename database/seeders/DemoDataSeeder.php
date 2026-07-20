<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\User;
use App\Models\Event;
use App\Models\EventTier;
use App\Models\Client;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n=== Starting Demo Data Seeder ===\n";

        // ===== ORGANIZATIONS =====
        echo "Creating Organizations...\n";
        
        $org1 = Organization::create([
            'name' => 'Grace Church Kampala',
            'slug' => 'grace-church-kampala',
            'email' => 'admin@gracechurch.com',
            'contact_email' => 'info@gracechurch.com',
            'phone' => '+256701234567',
            'website' => 'www.gracechurch.com',
            'description' => 'A vibrant church community dedicated to worship, fellowship, and community service',
            'tagline' => 'Where Faith Meets Community',
            'is_active' => true,
        ]);
        echo "✅ Created: {$org1->name} (slug: {$org1->slug})\n";

        $org2 = Organization::create([
            'name' => 'Kampala Event Masters',
            'slug' => 'kampala-event-masters',
            'email' => 'info@eventmasters.ug',
            'contact_email' => 'hello@eventmasters.ug',
            'phone' => '+256702345678',
            'website' => 'www.eventmasters.ug',
            'description' => 'Professional event management and planning services across Uganda',
            'tagline' => 'Creating Unforgettable Experiences',
            'is_active' => true,
        ]);
        echo "✅ Created: {$org2->name} (slug: {$org2->slug})\n";

        // ===== USERS =====
        echo "\nCreating Users...\n";

        // Super Admin (can see everything)
        $superAdmin = User::create([
            'name' => 'System Admin',
            'email' => 'superadmin@system.com',
            'password' => bcrypt('password'),
            'organization_id' => null,
        ]);
        $superAdmin->assignRole('super_admin');
        echo "✅ Super Admin: {$superAdmin->email} (password: password)\n";

        // Org 1 Admin
        $org1Admin = User::create([
            'name' => 'Grace Church Admin',
            'email' => 'admin@gracechurch.com',
            'password' => bcrypt('password'),
            'organization_id' => $org1->id,
        ]);
        $org1Admin->assignRole('org_admin');
        echo "✅ Org Admin (Grace Church): {$org1Admin->email} (password: password)\n";

        // Org 1 Staff
        $org1Staff = User::create([
            'name' => 'Grace Church Staff',
            'email' => 'staff@gracechurch.com',
            'password' => bcrypt('password'),
            'organization_id' => $org1->id,
        ]);
        $org1Staff->assignRole('staff');
        echo "✅ Staff (Grace Church): {$org1Staff->email} (password: password)\n";

        // Org 1 Scanner
        $org1Scanner = User::create([
            'name' => 'Grace Church Scanner',
            'email' => 'scanner@gracechurch.com',
            'password' => bcrypt('password'),
            'organization_id' => $org1->id,
        ]);
        $org1Scanner->assignRole('scanner');
        echo "✅ Scanner (Grace Church): {$org1Scanner->email} (password: password)\n";

        // Org 2 Admin
        $org2Admin = User::create([
            'name' => 'Event Masters Admin',
            'email' => 'admin@eventmasters.ug',
            'password' => bcrypt('password'),
            'organization_id' => $org2->id,
        ]);
        $org2Admin->assignRole('org_admin');
        echo "✅ Org Admin (Event Masters): {$org2Admin->email} (password: password)\n";

        // Org 2 Staff
        $org2Staff = User::create([
            'name' => 'Event Masters Staff',
            'email' => 'staff@eventmasters.ug',
            'password' => bcrypt('password'),
            'organization_id' => $org2->id,
        ]);
        $org2Staff->assignRole('staff');
        echo "✅ Staff (Event Masters): {$org2Staff->email} (password: password)\n";

        // ===== EVENTS =====
        echo "\nCreating Events...\n";

        // Event 1: Grace Church Conference (PUBLIC)
        $event1 = Event::create([
            'organization_id' => $org1->id,
            'name' => 'Grace Church Annual Conference 2025',
            'slug' => 'grace-church-annual-conference-2025',
            'tagline' => 'Transform Your Faith, Transform Your Life',
            'description' => "Join us for an inspiring 3-day conference with international speakers, worship sessions, and interactive workshops. Experience spiritual growth, connect with believers, and discover your purpose.\n\nFeatured Speakers:\n- Pastor John Smith (USA)\n- Dr. Sarah Omondi (Kenya)\n- Rev. David Mukasa (Uganda)\n\nIncludes: Conference materials, lunch, coffee breaks",
            'event_date' => Carbon::now()->addDays(30)->setHour(9)->setMinute(0),
            'duration_days' => 3,
            'venue' => 'Grace Church Main Auditorium',
            'location' => 'Plot 15 Kampala Road, Nakasero, Kampala, Uganda',
            'capacity' => 500,
            'registration_deadline' => Carbon::now()->addDays(25),
            'status' => 'published',
            'is_public' => true,
        ]);
        echo "✅ Created Event (PUBLIC): {$event1->name}\n";

        // Event 2: Tech Summit (PUBLIC)
        $event2 = Event::create([
            'organization_id' => $org2->id,
            'name' => 'Uganda Tech Innovation Summit 2025',
            'slug' => 'uganda-tech-innovation-summit-2025',
            'tagline' => 'Building Africa\'s Digital Future',
            'description' => "East Africa's premier tech conference bringing together innovators, investors, and tech leaders. Network with industry professionals, attend keynote sessions, and discover the latest in AI, blockchain, and fintech.\n\nHighlights:\n- 50+ Expert Speakers\n- Startup Pitch Competition (UGX 50M Prize)\n- Tech Exhibition\n- Networking Sessions\n\nIncludes: All sessions, lunch, exhibition access, conference swag",
            'event_date' => Carbon::now()->addDays(45)->setHour(8)->setMinute(0),
            'duration_days' => 2,
            'venue' => 'Kampala Serena Hotel',
            'location' => 'Kintu Road, Nakasero Hill, Kampala, Uganda',
            'capacity' => 1000,
            'registration_deadline' => Carbon::now()->addDays(40),
            'status' => 'published',
            'is_public' => true,
        ]);
        echo "✅ Created Event (PUBLIC): {$event2->name}\n";

        // Event 3: Private Church Retreat (PRIVATE)
        $event3 = Event::create([
            'organization_id' => $org1->id,
            'name' => 'Leadership Retreat 2025',
            'slug' => 'leadership-retreat-2025',
            'tagline' => 'Empowering Tomorrow\'s Leaders',
            'description' => 'Exclusive retreat for church leadership team. Internal event only.',
            'event_date' => Carbon::now()->addDays(15)->setHour(9)->setMinute(0),
            'duration_days' => 2,
            'venue' => 'Jinja Retreat Center',
            'location' => 'Jinja, Uganda',
            'capacity' => 50,
            'status' => 'published',
            'is_public' => false,
        ]);
        echo "✅ Created Event (PRIVATE): {$event3->name}\n";

        // Event 4: Workshop (with WorkshopTicketDetail)
        $event4 = Event::create([
            'organization_id' => $org1->id,
            'name'            => 'Teachers Professional Development Workshop',
            'slug'            => 'teachers-professional-development-2025',
            'tagline'         => 'Empowering Educators Across Lesotho',
            'description'     => 'A one-day professional development workshop for teachers across all districts.',
            'event_date'      => Carbon::now()->addDays(20)->setHour(8)->setMinute(0),
            'venue'           => 'Maseru City Hall',
            'location'        => 'Kingsway Road, Maseru',
            'capacity'        => 200,
            'status'          => 'published',
            'is_public'       => true,
            'event_type'      => 'workshop',
        ]);
        echo "✅ Created Workshop Event: {$event4->name}\n";

        // ===== EVENT TIERS =====
        echo "\nCreating Event Tiers...\n";

        // Event 1 Tiers (Grace Church Conference)
        $tier1_early = EventTier::create([
            'event_id' => $event1->id,
            'tier_name' => 'Early Bird General',
            'price' => 35000,
            'quantity_available' => 100,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $tier1_general = EventTier::create([
            'event_id' => $event1->id,
            'tier_name' => 'General Admission',
            'price' => 50000,
            'quantity_available' => 200,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $tier1_vip = EventTier::create([
            'event_id' => $event1->id,
            'tier_name' => 'VIP Pass',
            'price' => 150000,
            'quantity_available' => 100,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        // LOW INVENTORY TIER - for testing low inventory notifications
        $tier1_limited = EventTier::create([
            'event_id' => $event1->id,
            'tier_name' => 'Limited VIP',
            'price' => 200000,
            'quantity_available' => 10, // Only 10 available
            'quantity_sold' => 8,       // 8 already sold = 20% remaining
            'is_active' => true,
        ]);
        echo "✅ Tier: {$tier1_limited->tier_name} - LOW INVENTORY (2 left)\n";

        $tier1_diamond = EventTier::create([
            'event_id' => $event1->id,
            'tier_name' => 'Diamond Package',
            'price' => 300000,
            'quantity_available' => 50,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        // Event 2 Tiers (Tech Summit)
        $tier2_student = EventTier::create([
            'event_id' => $event2->id,
            'tier_name' => 'Student Pass',
            'price' => 50000,
            'quantity_available' => 200,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $tier2_standard = EventTier::create([
            'event_id' => $event2->id,
            'tier_name' => 'Standard Pass',
            'price' => 100000,
            'quantity_available' => 400,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $tier2_premium = EventTier::create([
            'event_id' => $event2->id,
            'tier_name' => 'Premium Pass',
            'price' => 250000,
            'quantity_available' => 200,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $tier2_vvip = EventTier::create([
            'event_id' => $event2->id,
            'tier_name' => 'VVIP Corporate',
            'price' => 500000,
            'quantity_available' => null,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        // Event 3 Tier (Private retreat)
        $tier3_leader = EventTier::create([
            'event_id' => $event3->id,
            'tier_name' => 'Leadership Package',
            'price' => 0,
            'quantity_available' => null,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $tier4 = EventTier::create([
            'event_id'           => $event4->id,
            'tier_name'          => 'Delegate Pass',
            'price'              => 0,
            'quantity_available' => 200,
            'quantity_sold'      => 0,
            'is_active'          => true,
        ]);

        // ===== CLIENTS =====
        echo "\nCreating Clients...\n";

        $clients = [
            ['org' => $org1->id, 'name' => 'John Ssemwogerere', 'email' => 'john.ssem@example.com', 'phone' => '+256701234561'],
            ['org' => $org1->id, 'name' => 'Jane Nakato', 'email' => 'jane.nakato@example.com', 'phone' => '+256702345672'],
            ['org' => $org1->id, 'name' => 'James Katende', 'email' => 'james.k@example.com', 'phone' => '+256703456783'],
            ['org' => $org1->id, 'name' => 'Sarah Namukasa', 'email' => 'sarah.n@example.com', 'phone' => '+256704567894'],
            ['org' => $org1->id, 'name' => 'Mark Opio', 'email' => 'mark.o@example.com', 'phone' => '+256705678905'],
            ['org' => $org2->id, 'name' => 'Alice Mukasa', 'email' => 'alice.m@example.com', 'phone' => '+256706789016'],
            ['org' => $org2->id, 'name' => 'David Okello', 'email' => 'david.o@example.com', 'phone' => '+256707890127'],
            ['org' => $org2->id, 'name' => 'Grace Atim', 'email' => 'grace.a@example.com', 'phone' => '+256708901238'],
        ];

        $clientModels = [];
        foreach ($clients as $clientData) {
            $client = Client::create([
                'organization_id' => $clientData['org'],
                'full_name' => $clientData['name'],
                'email' => $clientData['email'],
                'phone' => $clientData['phone'],
                'status' => 'active',
            ]);
            $clientModels[] = $client;
            echo "✅ Client: {$client->full_name}\n";
        }

        // Workshop clients & tickets with details
        $workshopAttendees = [
            ['name' => 'Mpho Lerotholi',  'phone' => '+26658001001', 'email' => 'mpho@school.ls',  'position' => 'Teacher',    'institution' => 'Maseru High School',    'district' => 'maseru'],
            ['name' => 'Teboho Nkosi',    'phone' => '+26658001002', 'email' => 'teboho@school.ls', 'position' => 'Principal',  'institution' => 'Leribe Primary School',  'district' => 'leribe'],
            ['name' => 'Palesa Mokoena',  'phone' => '+26658001003', 'email' => null,               'position' => 'HOD',        'institution' => 'Berea Secondary School', 'district' => 'berea'],
            ['name' => 'Retselisitsoe M', 'phone' => '+26658001004', 'email' => null,               'position' => 'Teacher',    'institution' => 'Mafeteng High School',   'district' => 'mafeteng'],
            ['name' => 'Lineo Theko',     'phone' => '+26658001005', 'email' => 'lineo@edu.ls',     'position' => 'Instructor', 'institution' => 'Mohale\'s Hoek College', 'district' => 'mohales_hoek'],
        ];

        foreach ($workshopAttendees as $index => $attendee) {
            $client = Client::create([
                'organization_id' => $org1->id,
                'full_name'       => $attendee['name'],
                'phone'           => $attendee['phone'],
                'email'           => $attendee['email'],
                'status'          => 'active',
            ]);

            $ticket = Ticket::create([
                'event_id'       => $event4->id,
                'client_id'      => $client->id,
                'event_tier_id'  => $tier4->id,
                'ticket_number'  => 'WRK-' . $event4->id . '-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'qr_code'        => 'QR-' . Str::uuid(),
                'status'         => 'active',
                'payment_method' => 'free',
                'amount'         => 0,
                'payment_status' => 'completed',
                'delivered_at'   => now(),
                'created_by'     => $org1Staff->id,
            ]);

            // Workshop detail — mix of signed and pending for realistic data
            $isSigned = $index < 3; // first 3 signed, last 2 pending

            $detail = $ticket->workshopDetail()->create([
                'position'         => $attendee['position'],
                'institution'      => $attendee['institution'],
                'district'         => $attendee['district'],
                'signature_status' => $isSigned ? 'signed' : 'pending',
            ]);

            if ($isSigned) {
                // Generate fake signature PNG
                $img = imagecreatetruecolor(400, 150);
                $bg  = imagecolorallocate($img, 255, 255, 255);
                $ink = imagecolorallocate($img, 30, 64, 105);
                imagefill($img, 0, 0, $bg);
                // Simple squiggle per person
                imageline($img, 40,  80, 120, 50, $ink);
                imageline($img, 120, 50, 200, 90, $ink);
                imageline($img, 200, 90, 280, 60, $ink);
                imageline($img, 280, 60, 360, 85, $ink);

                ob_start();
                imagepng($img);
                $imageData = ob_get_clean();
                imagedestroy($img);

                $path = 'signatures/workshops/' . $org1->id . '/ticket_' . $ticket->id . '.png';
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $imageData);

                $detail->update([
                    'signature_path'   => $path,
                    'signed_at'        => now()->subHours(rand(1, 5)),
                    'signed_by'        => $org1Scanner->id,
                    'signature_status' => 'signed',
                ]);
            }

            echo "✅ Workshop ticket: {$client->full_name} — " . ($isSigned ? 'Signed' : 'Pending') . "\n";
        }

        // ===== TICKETS WITH NOTIFICATION TESTING =====
        echo "\n🔔 Creating Tickets (Testing Notifications)...\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        $ticketCounter = 1;

        // ✅ SCENARIO 1: Active/Completed Ticket (No notification)
        $ticket1 = Ticket::create([
            'event_id' => $event1->id,
            'client_id' => $clientModels[0]->id,
            'event_tier_id' => $tier1_general->id,
            'ticket_number' => 'TKT-' . $event1->id . '-' . str_pad($ticketCounter++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'active',
            'payment_method' => 'cash',
            'amount' => $tier1_general->price,
            'payment_status' => 'completed', // Already completed - no notification
            'payment_date' => now()->subDays(2),
            'payment_reference' => 'CASH-001',
            'delivery_method' => 'email',
            'delivered_at' => now()->subDays(2),
            'created_by' => $org1Staff->id,
        ]);
        echo "✅ Ticket #{$ticket1->ticket_number}: {$clientModels[0]->full_name}\n";
        echo "   Status: COMPLETED (no notification sent - already paid)\n\n";

        // 🔔 SCENARIO 2: PENDING PAYMENT - Should trigger notification
        echo "🔔 Creating PENDING ticket (should send notification)...\n";
        $ticket2 = Ticket::create([
            'event_id' => $event1->id,
            'client_id' => $clientModels[1]->id,
            'event_tier_id' => $tier1_vip->id,
            'ticket_number' => 'TKT-' . $event1->id . '-' . str_pad($ticketCounter++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'amount' => $tier1_vip->price,
            'payment_status' => 'pending', // PENDING - Will notify admins
            'payment_reference' => 'MPESA-' . rand(100000, 999999),
            'delivery_method' => 'whatsapp',
            'created_by' => $org1Staff->id,
        ]);
        echo "✅ Ticket #{$ticket2->ticket_number}: {$clientModels[1]->full_name}\n";
        echo "   Status: PENDING\n";
        echo "   📧 Notification sent to: {$org1Admin->email}\n\n";

        // 🔔 SCENARIO 3: Another PENDING - Should also notify
        echo "🔔 Creating another PENDING ticket...\n";
        $ticket3 = Ticket::create([
            'event_id' => $event1->id,
            'client_id' => $clientModels[2]->id,
            'event_tier_id' => $tier1_diamond->id,
            'ticket_number' => 'TKT-' . $event1->id . '-' . str_pad($ticketCounter++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'pending',
            'payment_method' => 'online',
            'amount' => $tier1_diamond->price,
            'payment_status' => 'pending', // PENDING - Will notify admins
            'payment_reference' => 'ONLINE-' . rand(100000, 999999),
            'delivery_method' => 'email',
            'created_by' => null, // Self-registered
        ]);
        echo "✅ Ticket #{$ticket3->ticket_number}: {$clientModels[2]->full_name}\n";
        echo "   Status: PENDING (self-registered)\n";
        echo "   📧 Notification sent to: {$org1Admin->email}\n\n";

        // ✅ SCENARIO 4: Free Ticket (Completed, no notification)
        $ticket4 = Ticket::create([
            'event_id' => $event1->id,
            'client_id' => $clientModels[3]->id,
            'event_tier_id' => $tier1_general->id,
            'ticket_number' => 'TKT-' . $event1->id . '-' . str_pad($ticketCounter++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'active',
            'payment_method' => 'free',
            'amount' => 0,
            'payment_status' => 'completed',
            'delivery_method' => 'email',
            'delivered_at' => now(),
            'created_by' => null,
        ]);
        echo "✅ Ticket #{$ticket4->ticket_number}: {$clientModels[3]->full_name}\n";
        echo "   Status: FREE (no notification - auto-approved)\n\n";

        // 🔔 SCENARIO 5: LOW INVENTORY - Will trigger when approved
        echo "🔔 Creating ticket for LOW INVENTORY tier...\n";
        $ticket5 = Ticket::create([
            'event_id' => $event1->id,
            'client_id' => $clientModels[4]->id,
            'event_tier_id' => $tier1_limited->id, // The low inventory tier
            'ticket_number' => 'TKT-' . $event1->id . '-' . str_pad($ticketCounter++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'amount' => $tier1_limited->price,
            'payment_status' => 'pending',
            'payment_reference' => 'MPESA-' . rand(100000, 999999),
            'created_by' => $org1Staff->id,
        ]);
        echo "✅ Ticket #{$ticket5->ticket_number}: {$clientModels[4]->full_name}\n";
        echo "   Status: PENDING (for low inventory tier)\n";
        echo "   📧 Admin notification sent\n";
        echo "   ⚠️  When approved, will trigger LOW INVENTORY alert\n\n";

        // Org 2 Tickets (Tech Summit)
        $ticketCounter2 = 1;

        // 🔔 SCENARIO 6: Pending for Org 2
        echo "🔔 Creating PENDING ticket for Event Masters...\n";
        $ticket6 = Ticket::create([
            'event_id' => $event2->id,
            'client_id' => $clientModels[5]->id,
            'event_tier_id' => $tier2_premium->id,
            'ticket_number' => 'TKT-' . $event2->id . '-' . str_pad($ticketCounter2++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'pending',
            'payment_method' => 'online',
            'amount' => $tier2_premium->price,
            'payment_status' => 'pending',
            'payment_reference' => 'ONLINE-' . rand(100000, 999999),
            'delivery_method' => 'email',
            'created_by' => $org2Staff->id,
        ]);
        echo "✅ Ticket #{$ticket6->ticket_number}: {$clientModels[5]->full_name}\n";
        echo "   Status: PENDING (Tech Summit)\n";
        echo "   📧 Notification sent to: {$org2Admin->email}\n\n";

        // ✅ SCENARIO 7: Completed Student Ticket
        $ticket7 = Ticket::create([
            'event_id' => $event2->id,
            'client_id' => $clientModels[6]->id,
            'event_tier_id' => $tier2_student->id,
            'ticket_number' => 'TKT-' . $event2->id . '-' . str_pad($ticketCounter2++, 5, '0', STR_PAD_LEFT),
            'qr_code' => 'QR-' . Str::uuid(),
            'status' => 'active',
            'payment_method' => 'mpesa',
            'amount' => $tier2_student->price,
            'payment_status' => 'completed',
            'payment_date' => now(),
            'payment_reference' => 'MPESA-' . rand(100000, 999999),
            'delivery_method' => 'whatsapp',
            'delivered_at' => now(),
            'created_by' => null,
        ]);
        echo "✅ Ticket #{$ticket7->ticket_number}: {$clientModels[6]->full_name}\n";
        echo "   Status: COMPLETED (no notification)\n\n";

        // ===== GENERATE QR CODES =====
        echo "\n🎨 Generating QR Codes for Tickets...\n";
        
        $allTickets = Ticket::all();
        $qrSuccess = 0;
        $qrFailed = 0;
        
        foreach ($allTickets as $ticket) {
            try {
                if ($ticket->generateQrCode()) {
                    $qrSuccess++;
                } else {
                    $qrFailed++;
                }
            } catch (\Exception $e) {
                $qrFailed++;
                echo "❌ Error for ticket {$ticket->ticket_number}: {$e->getMessage()}\n";
            }
        }
        
        echo "\n📊 QR Generation Summary: ✅ {$qrSuccess} | ❌ {$qrFailed}\n";

        echo "\n=== Demo Data Created Successfully ===\n\n";

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🔔 NOTIFICATION TESTING SCENARIOS\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo "1️⃣  PENDING APPROVALS (3 tickets)\n";
        echo "   Login as: admin@gracechurch.com\n";
        echo "   You should see:\n";
        echo "   • 🔔 3 notifications in bell icon\n";
        echo "   • Dashboard widget showing 3 pending approvals\n";
        echo "   • Stats widget: '3 Pending Approvals' (warning color)\n";
        echo "   Tickets:\n";
        echo "   - Jane Nakato (VIP Pass - 150,000 UGX)\n";
        echo "   - James Katende (Diamond - 300,000 UGX)\n";
        echo "   - Mark Opio (Limited VIP - 200,000 UGX)\n\n";

        echo "2️⃣  TEST APPROVAL FLOW\n";
        echo "   a) Click on a notification → Takes you to ticket edit page\n";
        echo "   b) Or use 'Approve' button in dashboard widget\n";
        echo "   c) Enter payment reference (e.g., MPESA-123456)\n";
        echo "   d) Select payment method\n";
        echo "   e) Click 'Approve'\n";
        echo "   Result:\n";
        echo "   • ✅ Success notification shown\n";
        echo "   • 🔔 Notification marked as read\n";
        echo "   • 📊 Pending count decreases\n";
        echo "   • 💰 Revenue increases in stats\n\n";

        echo "3️⃣  LOW INVENTORY ALERT\n";
        echo "   When you approve Mark Opio's ticket (Limited VIP):\n";
        echo "   • The tier will have 1/10 left (10% remaining)\n";
        echo "   • 🔔 NEW notification: 'Low inventory alert'\n";
        echo "   • Message: 'Only 1 tickets left for Limited VIP'\n";
        echo "   • Click notification → Takes you to tier edit page\n\n";

        echo "4️⃣  MULTI-TENANCY TEST\n";
        echo "   Login as: admin@eventmasters.ug\n";
        echo "   You should see:\n";
        echo "   • 🔔 1 notification (Alice Mukasa - Tech Summit)\n";
        echo "   • NO Grace Church notifications (isolated)\n";
        echo "   • Dashboard shows only Event Masters data\n\n";

        echo "5️⃣  DASHBOARD WIDGETS\n";
        echo "   • Stats Overview (top): 4 cards showing:\n";
        echo "     - Pending Approvals (warning badge)\n";
        echo "     - Active Tickets\n";
        echo "     - Total Revenue\n";
        echo "     - Today's Registrations\n";
        echo "   • Pending Approvals Table (below stats):\n";
        echo "     - Shows latest 10 pending tickets\n";
        echo "     - Quick approve button\n";
        echo "     - Real-time updates\n\n";

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📋 TEST ACCOUNTS\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo "GRACE CHURCH (3 pending approvals):\n";
        echo "  📧 admin@gracechurch.com\n";
        echo "  🔑 password\n";
        echo "  🔔 Will have 3+ notifications\n\n";

        echo "EVENT MASTERS (1 pending approval):\n";
        echo "  📧 admin@eventmasters.ug\n";
        echo "  🔑 password\n";
        echo "  🔔 Will have 1 notification\n\n";

        echo "SUPER ADMIN (sees all):\n";
        echo "  📧 superadmin@system.com\n";
        echo "  🔑 password\n";
        echo "  🔔 Will have 4 notifications (all orgs)\n\n";

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ EXPECTED BEHAVIOR\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo "When ticket is created with 'pending' payment_status:\n";
        echo "  ✅ Notification sent to all org admins\n";
        echo "  ✅ Appears in bell icon (top right)\n";
        echo "  ✅ Shows in pending approvals widget\n";
        echo "  ✅ Count increases in stats widget\n\n";

        echo "When admin approves payment:\n";
        echo "  ✅ Ticket status → 'active'\n";
    }
}