import { Head, usePage } from '@inertiajs/react';
import { ChevronDown, Hourglass, Route as RouteIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { DirectPay } from '@/components/pay/direct-pay';
import { MerchantPay } from '@/components/pay/merchant-pay';
import { OnlinePay, type Panel } from '@/components/pay/online-pay';
import type { Account, ManualOption, Merchant, OnlineOptions } from '@/components/pay/types';
import { SaveLink } from '@/components/save-link';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { TicketData } from '@/types/ticket';
import { cn, money } from '@/lib/utils';

/**
 * Paying for a ticket: online with a PIN prompt (the default), or another
 * way: VENTIQ's EcoCash merchant by hand, or, on events VENTIQ sponsors,
 * the organizer's own accounts.
 */
export default function Pay({
    ticket,
    owed,
    online,
    byHand,
    anotherWay,
    merchant,
    merchantReference,
    accounts,
    manual,
    installments,
}: {
    ticket: TicketData;
    owed: number;
    online: OnlineOptions;
    byHand: boolean;
    anotherWay: boolean;
    merchant: Merchant | null;
    merchantReference: string;
    accounts: Account[];
    manual: ManualOption[];
    installments: { percent: number; minDeposit: number; full: number } | null;
}) {
    const { errors } = usePage().props;
    const onlineEnabled = online.methods.length > 0;
    // The pay card: online methods, and those paid to VENTIQ by hand.
    const payCard = onlineEnabled || manual.length > 0;
    const direct = accounts.length > 0;
    // The other way to pay: the organizer's accounts, or VENTIQ's merchant.
    const merchantCard = !direct && !!merchant;
    const errorFields = merchantCard ? ['merchant_phone', 'merchant_reference', 'proof'] : ['payment_method_id', 'payment_reference', 'proof', 'deposit_amount'];
    const [open, setOpen] = useState(!onlineEnabled || errorFields.some((f) => errors[f]));
    const another = useRef<HTMLDivElement>(null);

    const startPanel: Panel = byHand ? 'byhand' : online.inFlight ? 'waiting' : online.attemptsLeft === 0 || anotherWay ? 'fallback' : 'form';
    const errorList = Object.values(errors);

    return (
        <GuestLayout context={ticket.event.organization ?? undefined} label="Payment">
            <Head title={`Payment | ${ticket.event.name}`} />

            <div className="mb-8 overflow-hidden rounded-[2.5rem] bg-gray-900 shadow-2xl">
                <div className="flex items-center justify-between p-8 text-white">
                    <div>
                        <span className="text-[9px] font-black uppercase tracking-[0.4em] text-action">Amount Due</span>
                        <h2 className="mt-1 text-4xl font-black tracking-tighter">{money(owed)}</h2>
                        {owed < ticket.amount && <p className="mt-1 text-[11px] font-bold text-white/70">Balance left of {money(ticket.amount)}</p>}
                        <p className="mt-2 text-[10px] font-bold uppercase tracking-widest text-white/50">{ticket.event.name} · {ticket.tier.name}</p>
                        {ticket.paymentDueAt && (
                            <p className="mt-2 text-[11px] font-bold text-action"><Hourglass className="mr-1 inline size-3.5" />Your place is held until {ticket.paymentDueAt}</p>
                        )}
                    </div>
                    <div className="text-right">
                        <span className="text-[9px] font-black uppercase tracking-widest text-white/40">Ref</span>
                        <p className="font-mono text-sm font-black">{ticket.number}</p>
                    </div>
                </div>
            </div>

            {errorList.length > 0 && (
                <Alert variant="destructive" className="mb-8">
                    <ul className="list-inside list-disc text-xs">{errorList.map((e) => <li key={e}>{e}</li>)}</ul>
                </Alert>
            )}

            {!payCard && <SaveLink ticket={ticket} className="mb-6" />}

            {payCard && (
                <OnlinePay
                    code={ticket.code}
                    ticketUrl={ticket.link}
                    options={online}
                    manual={manual}
                    owed={owed}
                    top={<SaveLink ticket={ticket} />}
                    startPanel={startPanel}
                    fallback={({ attemptsLeft, retry }) => (
                        <div className="py-2">
                            <div className="mb-6 text-center">
                                <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-slate-100"><RouteIcon className="size-7 text-brand" /></div>
                                <h3 className="mb-2 text-2xl font-black uppercase tracking-tight text-gray-900">Let's try another way</h3>
                                <p className="text-sm font-bold text-gray-500">
                                    {attemptsLeft > 0
                                        ? 'The payment prompt didn\'t go through. Pay directly instead and send us the proof.'
                                        : `The payment prompt didn't work after ${online.maxAttempts} tries.`}{' '}
                                    Your place is still held.
                                </p>
                            </div>
                            {direct ? (
                                <Button
                                    type="button"
                                    variant="dark"
                                    className="w-full"
                                    onClick={() => {
                                        setOpen(true);
                                        another.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    }}
                                >
                                    Pay the organizer directly
                                </Button>
                            ) : merchant ? (
                                <MerchantPay code={ticket.code} merchant={merchant} reference={merchantReference} paidFrom={online.phone} owed={owed} idPrefix="fallback" />
                            ) : (
                                <p className="text-center text-[13px] font-bold text-gray-500">Please contact {ticket.event.organization} to pay for your ticket.</p>
                            )}
                            {attemptsLeft > 0 && (
                                <button type="button" onClick={retry} className="mt-4 w-full py-3 text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">
                                    Or try the payment prompt again
                                </button>
                            )}
                        </div>
                    )}
                />
            )}

            <div ref={another} className="overflow-hidden rounded-[2.5rem] border border-gray-100 bg-white shadow-lg shadow-gray-200/40">
                {onlineEnabled ? (
                    <button type="button" onClick={() => setOpen((o) => !o)} className="flex w-full items-center justify-between p-6 text-left sm:p-8">
                        <span>
                            <span className="block text-xs font-black uppercase tracking-widest text-gray-500">
                                {merchantCard ? 'Or pay with EcoCash yourself' : 'Or pay directly to the organizer'}
                            </span>
                            <span className="mt-1 block text-[11px] font-medium normal-case text-gray-400">
                                {merchantCard
                                    ? "Send the payment to VENTIQ's merchant code from your EcoCash menu."
                                    : 'Payment is made directly to the event organizer, who confirms it before your ticket activates.'}
                            </span>
                        </span>
                        <ChevronDown className={cn('size-4 text-gray-400 transition-transform', open && 'rotate-180')} />
                    </button>
                ) : (
                    <div className="p-6 pb-0 sm:p-8 sm:pb-0">
                        <span className="block text-xs font-black uppercase tracking-widest text-gray-500">{merchantCard ? 'Pay with EcoCash' : 'Pay directly to the organizer'}</span>
                        {!merchantCard && <span className="mt-1 block text-[11px] font-medium text-gray-400">Payment is made directly to the event organizer, who confirms it before your ticket activates.</span>}
                    </div>
                )}

                <div className={cn('grid transition-all duration-300', open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]')}>
                    <div className="overflow-hidden">
                        <div className="p-6 pt-4 sm:p-8">
                            {merchantCard && merchant ? (
                                <MerchantPay code={ticket.code} merchant={merchant} reference={merchantReference} paidFrom={online.phone} owed={owed} idPrefix="merchant" />
                            ) : (
                                <DirectPay code={ticket.code} accounts={accounts} owed={owed} installments={installments} />
                            )}
                        </div>
                    </div>
                </div>
            </div>

        </GuestLayout>
    );
}
