<?php

namespace Database\Seeders\Demo;

/**
 * What happened at the Maluti Community Health Trust over six months, as
 * its weekly management meetings recorded it. Week 0 is six months ago;
 * week 25 is this week's meeting, held but not yet reviewed.
 *
 * Each item is [the notes typed in the room, the discussion as minuted,
 * the decisions]. Items a week leaves out get a short routine update.
 * Action points name who owns them (a team member's key, or a name) and
 * the week they were done in, if they were.
 */
final class SessionsDemoStory
{
    public const ITEMS = [
        'finance'    => ['Finance',               'Nthabiseng Letsie'],
        'programmes' => ['Programmes',            'Palesa Mokoena'],
        'logistics'  => ['Logistics and fleet',   'Teboho Mokhele'],
        'staffing'   => ['Staffing',              'Dr Lineo Ramoeletsi'],
        'me'         => ['Monitoring and evaluation', 'Mpho Sello'],
    ];

    /** The M&E item is on the agenda from the week the officer started. */
    public const ME_FROM = 10;

    public const ROUTINE = [
        'finance'    => [['spending on track', 'no new requests'], 'The Finance Officer reported that spending for the week was within budget and no new requests had been received.'],
        'programmes' => [['CHW visits continuing as planned', 'no issues from districts'], 'The Programmes Manager reported that community health worker visits continued as planned in all three districts, with no issues raised.'],
        'logistics'  => [['deliveries on schedule'], 'The Logistics Officer reported that deliveries ran on schedule during the week.'],
        'staffing'   => [['no changes'], 'The Director reported no changes to staffing.'],
        'me'         => [['weekly data in from all districts'], 'The M&E Officer reported that weekly data had been received from all districts on time.'],
    ];

    public const WEEKS = [
        // ── Month 1: fuel, a broken truck, the donor report, two vacancies, ORS stockouts ──
        0 => [
            'finance'    => [['fuel up M1.80/l since last month', 'fuel line will overrun if prices hold', 'donor Q1 financial report due in 3 weeks'],
                'The Finance Officer reported that the fuel price had risen by M1.80 a litre since the previous month and that the fuel budget line would be overspent by the end of the quarter if prices held. She reminded members that the donor quarterly financial report was due in three weeks.',
                ['Fuel use to be reviewed weekly until prices settle.']],
            'logistics'  => [['Mafeteng truck broke down on Tuesday, gearbox', 'deliveries to Mafeteng clinics delayed 3 days', 'borrowed bakkie from Ministry'],
                'The Logistics Officer reported that the Mafeteng truck had broken down with a gearbox fault, delaying deliveries to Mafeteng clinics by three days. A bakkie had been borrowed from the Ministry of Health district office in the meantime.',
                ['Get two quotes for the gearbox repair before any work is done.']],
            'staffing'   => [['M&E officer post vacant since March', 'driver post vacant', 'advertise both'],
                'The Director noted that the Monitoring and Evaluation Officer post had been vacant since March and the driver post was also vacant. Both were affecting delivery and reporting.',
                ['Advertise the M&E Officer and driver posts.']],
            'actions'    => [
                ['Get two quotes for the Mafeteng truck gearbox', 'Teboho Mokhele', 7, 'done' => 1],
                ['Advertise the M&E Officer and driver posts', 'lineo', 10, 'done' => 2],
                ['Draft the donor Q1 financial report', 'Nthabiseng Letsie', 18, 'done' => 3],
            ],
        ],
        1 => [
            'programmes' => [['Quthing clinics report ORS stockout', '3 of 7 clinics out of ORS', 'district store also low'],
                'The Programmes Manager reported that three of the seven clinics supported in Quthing had run out of oral rehydration salts, and the district store was also low. Community health workers were referring cases to the hospital.',
                ['Request an emergency ORS allocation from the district pharmacist.']],
            'logistics'  => [['two quotes in for gearbox: M8,400 and M11,200', 'Mokhele Motors cheaper, 5 days'],
                'The Logistics Officer presented two quotes for the gearbox repair: M8,400 from Mokhele Motors and M11,200 from Auto Lesotho. Mokhele Motors could complete the work in five days.',
                ['Accept the Mokhele Motors quote of M8,400 for the gearbox repair.']],
            'actions'    => [
                ['Request an emergency ORS allocation for Quthing from the district pharmacist', 'palesa', 3, 'done' => 2],
                ['Book the truck in at Mokhele Motors', 'Teboho Mokhele', 2, 'done' => 2],
            ],
        ],
        2 => [
            'programmes' => [['district pharmacist sent 400 sachets ORS to Quthing', 'enough for ~3 weeks', 'need a buffer'],
                'The Programmes Manager reported that the district pharmacist had sent 400 sachets of ORS to Quthing, enough for about three weeks. Members agreed the Trust needed a buffer of its own so that clinics were not left short again.',
                ['Keep a buffer stock of ORS at the Quthing field office.']],
            'staffing'   => [['adverts out in Lesotho Times and on Facebook', 'closing in two weeks'],
                'The Director reported that both posts had been advertised in the Lesotho Times and on social media, closing in two weeks.',
                []],
            'actions'    => [
                ['Cost a three-month ORS buffer for Quthing', 'palesa', 14, 'done' => 4],
            ],
        ],
        3 => [
            'finance'    => [['Q1 donor financial report drafted', 'fuel 9% over budget for the quarter', 'explain variance in narrative'],
                'The Finance Officer presented the draft donor Q1 financial report. Fuel was 9% over budget for the quarter; the variance would be explained in the narrative report as caused by the price rise.',
                ['Submit the Q1 financial report with an explanation of the fuel variance.']],
            'logistics'  => [['truck back from Mokhele Motors, M8,400 paid', 'running well', 'old truck, 280,000 km'],
                'The Logistics Officer reported that the truck was back from Mokhele Motors and running well, at a cost of M8,400. He cautioned that it had done 280,000 km and further breakdowns were likely.',
                []],
            'apologies'  => 'Teboho Mokhele joined for the logistics item only',
            'actions'    => [
                ['Submit the donor Q1 financial and narrative report', 'palesa', 4, 'done' => 4],
            ],
        ],

        // ── Month 2: data quality, interviews, a second vehicle proposed ──
        4 => [
            'programmes' => [['Q1 report submitted to the donor on Friday', 'ORS buffer costed at M6,200 for 3 months', 'data quality problems in CHW registers again'],
                'The Programmes Manager confirmed the Q1 report had been submitted to the donor. The three-month ORS buffer for Quthing had been costed at M6,200. She raised again the quality of data in community health worker registers, with missing dates and double counting.',
                ['Approve M6,200 for a three-month ORS buffer in Quthing.', 'Hold a data quality workshop for district supervisors.']],
            'actions'    => [
                ['Organise a data quality workshop for district supervisors', 'thabo', 14, 'done' => 6],
                ['Buy the ORS buffer for Quthing', 'Teboho Mokhele', 10, 'done' => 6],
            ],
        ],
        5 => [
            'staffing'   => [['M&E applications: 23, shortlisted 4', 'driver applications: 41, shortlisted 5', 'panel Palesa, Lineo, Thabo'],
                'The Director reported 23 applications for the M&E Officer post, with four shortlisted, and 41 for the driver post, with five shortlisted. The interview panel would be the Director, the Programmes Manager and the Programme Coordinator.',
                ['Interview the shortlisted M&E candidates in two weeks.']],
            'logistics'  => [['one truck for Mafeteng and Quthing not enough', 'Quthing deliveries every 2 weeks only', 'propose second vehicle'],
                'The Logistics Officer reported that one truck serving both Mafeteng and Quthing was not enough: Quthing clinics were receiving deliveries only every two weeks. He proposed a second vehicle.',
                ['Prepare a proposal for a second vehicle for the board.']],
            'actions'    => [
                ['Prepare a second vehicle proposal for the board', 'Teboho Mokhele', 21, 'done' => 8],
            ],
        ],
        6 => [
            'programmes' => [['data quality workshop held Wednesday, 14 supervisors', 'agreed monthly data review routine', 'register template to be simplified'],
                'The Programme Coordinator reported that the data quality workshop had been held with 14 district supervisors. They agreed a monthly data review routine and asked for a simpler register template.',
                ['Adopt the monthly data review routine from next month.', 'Simplify the CHW register template.']],
            'actions'    => [
                ['Simplify the CHW register template', 'thabo', 21, 'done' => 10],
            ],
        ],
        7 => [
            'staffing'   => [['M&E interviews done, 4 candidates', 'Mpho Sello strongest: DHIS2, 5 yrs MoH', 'reference checks'],
                'The Director reported that the M&E Officer interviews had been held. The panel recommended Mpho Sello, who had five years of experience with the Ministry of Health and strong DHIS2 skills, subject to reference checks.',
                ['Offer the M&E Officer post to Mpho Sello, subject to references.']],
            'finance'    => [['fuel still above budget, 11% for the year to date'],
                'The Finance Officer reported that fuel was 11% over budget for the year to date.',
                []],
            'actions'    => [
                ['Check references for Mpho Sello', 'lineo', 7, 'done' => 8],
            ],
        ],
        8 => [
            'logistics'  => [['second vehicle proposal drafted: double-cab 4x4', 'est M450,000 to M480,000', 'running cost M7,000/month'],
                'The Logistics Officer presented the second vehicle proposal: a double-cab 4x4 estimated at M450,000 to M480,000, with running costs of about M7,000 a month. It would let Quthing receive weekly deliveries.',
                ['Take the second vehicle proposal to the board meeting.']],
            'staffing'   => [['references for Mpho good', 'offer letter sent, starts in two weeks'],
                'The Director reported that the references for Mpho Sello were good and an offer letter had been sent. He would start in two weeks.',
                []],
        ],

        // ── Month 3: vehicle approved, M&E officer starts, stockouts resolved ──
        9 => [
            'logistics'  => [['board approved second vehicle up to M480,000', 'get three quotes', 'Toyota, Isuzu, Nissan dealers'],
                'The Director reported that the board had approved the purchase of a second vehicle up to M480,000. The Logistics Officer would obtain three quotes, from the Toyota, Isuzu and Nissan dealers in Maseru.',
                ['Obtain three quotes for the second vehicle.']],
            'programmes' => [['Quthing: no stockouts for 4 weeks thanks to buffer'],
                'The Programmes Manager reported that there had been no ORS stockouts in Quthing for four weeks, thanks to the buffer stock.',
                []],
            'actions'    => [
                ['Obtain three quotes for the second vehicle', 'Teboho Mokhele', 14, 'done' => 11],
            ],
        ],
        10 => [
            'staffing'   => [['Mpho Sello started Monday', 'induction this week', 'driver interviews next week'],
                'The Director welcomed Mpho Sello, the new M&E Officer, who started on Monday. Driver interviews would be held the following week.',
                []],
            'me'         => [['first week: reviewing the indicator framework', 'DHIS2 access requested'],
                'The M&E Officer reported that he was reviewing the indicator framework and had requested access to the district DHIS2.',
                []],
            'programmes' => [['new register template printed and sent to districts'],
                'The Programme Coordinator reported that the simplified register template had been printed and sent to all districts.',
                []],
        ],
        11 => [
            'logistics'  => [['vehicle quotes: Toyota M472,000, Isuzu M455,000, Nissan M468,000', 'Isuzu delivery 6 weeks', 'Toyota 3 weeks, better service network in south'],
                'The Logistics Officer presented three quotes for the second vehicle: Toyota M472,000, Isuzu M455,000 and Nissan M468,000. Isuzu would deliver in six weeks and Toyota in three; Toyota also had the better service network in the south.',
                ['Buy the Toyota double-cab at M472,000, for its delivery time and service network.']],
            'finance'    => [['fuel 12% over budget, Q2', 'need a policy on fuel allowance'],
                'The Finance Officer reported that fuel was now 12% over budget for the second quarter and proposed that the Trust adopt a fuel allowance policy for field travel.',
                []],
            'actions'    => [
                ['Raise the purchase order for the Toyota double-cab', 'Nthabiseng Letsie', 5, 'done' => 12],
                ['Draft a fuel allowance policy', 'Nthabiseng Letsie', 30, 'done' => 19],
            ],
        ],
        12 => [
            'me'         => [['DHIS2 access granted', 'baseline for Q2 indicators ready next week', 'Mafeteng reporting rate 78%'],
                'The M&E Officer reported that DHIS2 access had been granted. The reporting rate in Mafeteng was 78%, below the 90% target.',
                ['Follow up with Mafeteng supervisors on late reports.']],
            'staffing'   => [['driver interviews held, Lehlohonolo Mafa recommended'],
                'The Director reported that driver interviews had been held and the panel recommended Lehlohonolo Mafa.',
                ['Offer the driver post to Lehlohonolo Mafa.']],
            'apologies'  => 'Palesa Mokoena',
            'actions'    => [
                ['Follow up with Mafeteng supervisors on late reports', 'Mpho Sello', 14, 'done' => 14],
            ],
        ],

        // ── Month 4: the vehicle arrives, a driver, CHW training planned ──
        13 => [
            'programmes' => [['CHW training planned for Mafeteng', '3 days, 24 CHWs', 'modules: danger signs, ORS, referrals, registers'],
                'The Programmes Manager presented plans for a three-day community health worker training in Mafeteng for 24 CHWs, covering danger signs in children, ORS and zinc, referrals and the new registers.',
                ['Hold the CHW training in Mafeteng over three days.', 'Certificates for those who attend at least two days.']],
            'actions'    => [
                ['Book the venue for the Mafeteng CHW training', 'thabo', 14, 'done' => 15],
                ['Prepare the CHW training materials', 'palesa', 28, 'done' => 17],
            ],
        ],
        14 => [
            'logistics'  => [['Toyota delivered Thursday', 'branded and insured', 'based in Quthing from next week'],
                'The Logistics Officer reported that the Toyota double-cab had been delivered, branded and insured. It would be based in Quthing from the following week.',
                []],
            'staffing'   => [['driver Lehlohonolo Mafa started', 'defensive driving course booked'],
                'The Director reported that the new driver, Lehlohonolo Mafa, had started and a defensive driving course had been booked for him.',
                []],
        ],
        15 => [
            'logistics'  => [['Quthing weekly deliveries started', 'first week all 7 clinics reached'],
                'The Logistics Officer reported that weekly deliveries to Quthing had started, with all seven clinics reached in the first week.',
                []],
            'programmes' => [['venue booked: Mafeteng Community Hall', 'enrolment link sent to supervisors'],
                'The Programme Coordinator reported that the Mafeteng Community Hall had been booked for the CHW training and the enrolment link sent to district supervisors.',
                []],
        ],
        16 => [
            'me'         => [['Q2 indicators: home visits up 18%', 'Mafeteng reporting rate now 91%'],
                'The M&E Officer reported that home visits were up 18% on the first quarter and that the Mafeteng reporting rate had risen to 91%.',
                []],
            'finance'    => [['Q2 donor financial report due in 2 weeks', 'vehicle purchase to be reported as capital'],
                'The Finance Officer reminded members that the Q2 donor financial report was due in two weeks; the vehicle would be reported as capital expenditure.',
                []],
            'actions'    => [
                ['Submit the Q2 donor report', 'palesa', 14, 'done' => 18],
            ],
        ],
        17 => [
            'programmes' => [['CHW training Tue-Thu this week, 24 enrolled', 'materials printed', 'facilitators: Thabo, Mpho, Palesa'],
                'The Programmes Manager reported that 24 community health workers were enrolled for the training from Tuesday that week, the materials were printed, and the facilitators would be the Programme Coordinator, the M&E Officer and herself.',
                []],
        ],

        // ── Month 5: the training held, the rains, the fuel policy ──
        18 => [
            'programmes' => [['CHW training held Tue-Thu', '21 qualified for certificates (2 of 3 days)', '3 attended one day only, follow-up'],
                'The Programmes Manager reported that the CHW training had been held over three days. Twenty-one participants had attended at least two days and received certificates; three had attended only one day and would be followed up.',
                ['Follow up with the three CHWs who attended one day.']],
            'actions'    => [
                ['Follow up with the CHWs who attended only one day of the training', 'thabo', 14, 'done' => 20],
            ],
        ],
        19 => [
            'logistics'  => [['rains started early', 'road to Mount Moorosi washed out', 'deliveries via Mphaki, +2 hours'],
                'The Logistics Officer reported that the rains had started early and the road to Mount Moorosi was washed out. Deliveries were going via Mphaki, adding two hours.',
                ['Pre-position a month of supplies at the furthest Quthing clinics before the heavy rains.']],
            'finance'    => [['fuel allowance policy drafted', 'M2.50/km, capped by route', 'logbook required'],
                'The Finance Officer presented the draft fuel allowance policy: M2.50 a kilometre, capped by route, with a logbook required for every trip.',
                ['Adopt the fuel allowance policy from the first of next month.']],
            'actions'    => [
                ['Pre-position a month of supplies at the furthest Quthing clinics', 'Teboho Mokhele', 10, 'done' => 21],
                ['Brief all field staff on the fuel allowance policy', 'Nthabiseng Letsie', 10, 'done' => 21],
            ],
        ],
        20 => [
            'programmes' => [['Quthing supervision visits: 5 of 7 clinics', 'register use good, ORS stock fine', 'two CHWs inactive in Qacha road area'],
                'The Programmes Manager reported on supervision visits to five of the seven Quthing clinics: registers were well kept and ORS stock was sufficient, but two CHWs on the Qacha road had stopped working.',
                ['Replace the two inactive CHWs on the Qacha road.']],
            'apologies'  => 'Nthabiseng Letsie',
            'actions'    => [
                ['Recruit two CHWs for the Qacha road area', 'palesa', 30],
            ],
        ],
        21 => [
            'me'         => [['data review routine now monthly in all districts', 'Quthing supervisors asking for a refresher'],
                'The M&E Officer reported that the monthly data review routine was now running in all districts. Quthing supervisors had asked for refresher training.',
                ['Plan a refresher training for supervisors in Quthing.']],
            'finance'    => [['fuel back within budget since policy', '3% under this month'],
                'The Finance Officer reported that, since the fuel allowance policy, fuel spending was 3% under budget for the month.',
                []],
            'actions'    => [
                ['Plan a refresher training for Quthing supervisors', 'thabo', 21, 'done' => 23],
            ],
        ],

        // ── Month 6: the donor visit, the Q3 report, the 2027 budget ──
        22 => [
            'programmes' => [['donor field visit next week, Quthing and Mafeteng', 'itinerary draft', 'Q3 report due end of month'],
                'The Programmes Manager reported that the donor would make a field visit to Quthing and Mafeteng the following week. An itinerary had been drafted; the Q3 report was due at the end of the month.',
                []],
            'actions'    => [
                ['Confirm the donor field visit itinerary', 'palesa', 7, 'done' => 23],
                ['Draft the Q3 narrative report', 'Mpho Sello', 21],
            ],
        ],
        23 => [
            'programmes' => [['refresher training for Quthing supervisors set for next month', '3 days, enrolment open', 'certificates every day attended'],
                'The Programme Coordinator reported that the refresher training for Quthing supervisors had been set for the following month over three days, with enrolment open.',
                []],
            'logistics'  => [['vehicle for donor visit: the Toyota', 'Mafeteng truck service due'],
                'The Logistics Officer confirmed the Toyota would be used for the donor visit and that the Mafeteng truck was due for a service.',
                []],
            'actions'    => [
                ['Service the Mafeteng truck', 'Teboho Mokhele', 7],
            ],
        ],
        24 => [
            'programmes' => [['donor visit went well', 'impressed by Quthing deliveries and registers', 'asked about scaling to Qacha\'s Nek'],
                'The Director reported that the donor field visit had gone well. The donor was impressed by the weekly Quthing deliveries and the new registers, and asked whether the programme could be extended to Qacha\'s Nek.',
                ['Prepare a concept note on extending to Qacha\'s Nek.']],
            'finance'    => [['2027 budget drafting starts', 'inputs from each unit in 3 weeks'],
                'The Finance Officer reported that drafting of the 2027 budget had started and asked each unit for its inputs within three weeks.',
                ['Each unit to submit 2027 budget inputs within three weeks.']],
            'aob'        => 'The Director thanked the team for the donor visit.',
            'actions'    => [
                ['Prepare a concept note on extending to Qacha\'s Nek', 'lineo', 21],
                ['Submit 2027 budget inputs for Programmes', 'palesa', 21],
                ['Submit 2027 budget inputs for Logistics', 'Teboho Mokhele', 21],
            ],
        ],
        25 => [
            'me'         => [['Q3 narrative 70% done', 'need final numbers from Quthing'],
                'The M&E Officer reported that the Q3 narrative report was about 70% complete, awaiting final numbers from Quthing.',
                []],
            'logistics'  => [['Mafeteng truck service overdue, garage booked Thursday', 'rains getting heavier'],
                'The Logistics Officer reported that the Mafeteng truck service had been booked for Thursday. The rains were getting heavier.',
                ['Check the Quthing supply pre-positioning again before the end of the month.']],
            'staffing'   => [['two CHWs for Qacha road still to recruit', 'chiefs asked for names'],
                'The Director noted that the two CHWs for the Qacha road had not yet been recruited; the local chiefs had been asked to propose names.',
                []],
            // This week's: proposed by Ventiq Assist, for someone to confirm on the report.
            'proposed'   => [
                ['Get Quthing numbers to the M&E Officer for the Q3 report', 'Palesa Mokoena', 4],
                ['Check supplies at the furthest Quthing clinics', 'Teboho Mokhele', 9],
            ],
        ],
    ];

    /** The board meetings, about monthly: the director's, finance, programme and risk items, then what was resolved. */
    public const BOARD = [
        0 => [['Executive Director\'s report', 'Two vacancies, M&E Officer and driver, being advertised. Fuel prices are affecting delivery costs.'],
              ['Finance report', 'Q1 spending at 94% of budget, fuel over by 9%.'],
              ['Programme report', 'ORS stockouts in Quthing addressed with an emergency allocation from the district.'],
              ['Risk register', 'The age of the Mafeteng truck was added to the risk register as a high risk.'],
              ['Noted the Q1 reports.']],
        1 => [['Executive Director\'s report', 'Shortlisting done for both posts; a data quality workshop was held for 14 supervisors.'],
              ['Finance report', 'Fuel 11% over budget for the year to date.'],
              ['Programme report', 'A three-month ORS buffer was bought for Quthing.'],
              ['Risk register', 'Single-vehicle dependency for two districts discussed.'],
              ['Management to bring a costed proposal for a second vehicle.']],
        2 => [['Executive Director\'s report', 'Mpho Sello appointed as M&E Officer.'],
              ['Finance report', 'Second vehicle proposal costed at M450,000 to M480,000, running costs M7,000 a month.'],
              ['Programme report', 'No ORS stockouts for four weeks.'],
              ['Risk register', 'Reporting rates in Mafeteng below target.'],
              ['Approved the purchase of a second vehicle up to M480,000.']],
        3 => [['Executive Director\'s report', 'Toyota double-cab bought for M472,000 and based in Quthing; driver appointed.'],
              ['Finance report', 'Q2 report submitted; vehicle reported as capital expenditure.'],
              ['Programme report', 'CHW training planned for Mafeteng, 24 CHWs.'],
              ['Risk register', 'Single-vehicle risk closed.'],
              ['Noted the Q2 reports.']],
        4 => [['Executive Director\'s report', 'CHW training held: 21 certificates issued.'],
              ['Finance report', 'Fuel allowance policy adopted; spending back within budget.'],
              ['Programme report', 'Early rains; supplies pre-positioned in Quthing.'],
              ['Risk register', 'Rainy-season access to Quthing clinics added as a risk.'],
              ['Endorsed the fuel allowance policy.']],
        5 => [['Executive Director\'s report', 'Donor field visit went well; the donor asked about Qacha\'s Nek.'],
              ['Finance report', '2027 budget drafting under way.'],
              ['Programme report', 'Refresher training for Quthing supervisors next month.'],
              ['Risk register', 'Two inactive CHWs on the Qacha road.'],
              ['Management to prepare a concept note on extending to Qacha\'s Nek for the next board meeting.']],
    ];

    /** People on the CHW training, then the supervisors' refresher. [name, institution, position, district] */
    public const TRAINEES = [
        ['Mamello Thoahlane', 'Ha Ramabanta Clinic', 'Community Health Worker', 'mafeteng'],
        ['Rethabile Mokoena', 'Mafeteng Hospital', 'Community Health Worker', 'mafeteng'],
        ['Nthati Lerotholi', 'Thabana Morena Clinic', 'Community Health Worker', 'mafeteng'],
        ['Lerato Sefako', 'Ha Ramabanta Clinic', 'Community Health Worker', 'mafeteng'],
        ['Tšepo Khanyapa', 'Tsakholo Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Puleng Moshoeshoe', 'Matelile Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Limpho Nkuebe', 'Mafeteng Hospital', 'Community Health Worker', 'mafeteng'],
        ['Kananelo Molefi', 'Thabana Morena Clinic', 'Community Health Worker', 'mafeteng'],
        ['Mpolokeng Ralitlhare', 'Matelile Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Teboho Ntšekhe', 'Tsakholo Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Masechaba Lekhotla', 'Ha Mokhalinyane Clinic', 'Community Health Worker', 'mafeteng'],
        ['Refiloe Phakoe', 'Ha Mokhalinyane Clinic', 'Community Health Worker', 'mafeteng'],
        ['Nthabeleng Mosaase', 'Mafeteng Hospital', 'Community Health Worker', 'mafeteng'],
        ['Motlatsi Sekoati', 'Matelile Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Palesa Tau', 'Thabana Morena Clinic', 'Community Health Worker', 'mafeteng'],
        ['Itumeleng Makara', 'Tsakholo Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Mathabo Letšela', 'Ha Ramabanta Clinic', 'Community Health Worker', 'mafeteng'],
        ['Bokang Ramokhoro', 'Mafeteng Hospital', 'Community Health Worker', 'mafeteng'],
        // Two days only: still a certificate.
        ['Lineo Mphutlane', 'Matelile Health Centre', 'Community Health Worker', 'mafeteng'],
        ['Thato Mohapi', 'Ha Mokhalinyane Clinic', 'Community Health Worker', 'mafeteng'],
        ['Seipati Mohale', 'Tsakholo Health Centre', 'Community Health Worker', 'mafeteng'],
        // One day: followed up, no certificate.
        ['Lebohang Tšoeu', 'Thabana Morena Clinic', 'Community Health Worker', 'mafeteng'],
        ['Mosa Lebusa', 'Mafeteng Hospital', 'Community Health Worker', 'mafeteng'],
        ['Hlompho Matsoso', 'Ha Ramabanta Clinic', 'Community Health Worker', 'mafeteng'],
    ];

    public const SUPERVISORS = [
        ['Mamosa Qhobela', 'Quthing Hospital', 'District Supervisor', 'quthing'],
        ['Tlali Nthejane', 'Mount Moorosi Health Centre', 'Clinic Supervisor', 'quthing'],
        ['Nkhabiseng Lethola', 'Mphaki Clinic', 'Clinic Supervisor', 'quthing'],
        ['Rorisang Matela', 'Villa Maria Clinic', 'Clinic Supervisor', 'quthing'],
        ['Khotso Rantšo', 'Quthing Hospital', 'Data Clerk', 'quthing'],
        ['Mamotšelisi Pitso', 'Tosing Clinic', 'Clinic Supervisor', 'quthing'],
        ['Thabiso Lesoli', 'Seforong Clinic', 'Clinic Supervisor', 'quthing'],
    ];
}
