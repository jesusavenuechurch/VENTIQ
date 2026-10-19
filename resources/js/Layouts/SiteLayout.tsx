import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Headset, MapPin, Search, Tag } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AskVentiq } from '@/components/site/ask-ventiq';
import { useDistrict } from '@/components/site/district';
import { SearchDialog } from '@/components/site/search-dialog';
import { SupportDialog } from '@/components/site/support-dialog';
import { TermsDialog } from '@/components/site/terms-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * The site around signed-in and public pages: the top bar (logo, search
 * with district, pricing, support, account menu), the footer, and the
 * search, support, terms and Ask Ventiq panels. Pages with a phone tab bar
 * (the organizer area) pass it as `bottomBar`; the footer then shows only
 * on wider screens.
 */
export default function SiteLayout({ children, bottomBar }: { children: React.ReactNode; bottomBar?: React.ReactNode }) {
    const { auth, site } = usePage().props;
    const user = auth.user;
    const [district, setDistrict] = useDistrict();
    const [searching, setSearching] = useState(false);
    const [support, setSupport] = useState(false);
    const [terms, setTerms] = useState(false);

    // ⌘K / Ctrl+K opens the search; other pages can open Support.
    useEffect(() => {
        const key = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setSearching(true);
            }
        };
        const contact = () => setSupport(true);
        window.addEventListener('keydown', key);
        window.addEventListener('contact-open', contact);
        return () => {
            window.removeEventListener('keydown', key);
            window.removeEventListener('contact-open', contact);
        };
    }, []);

    const org = user?.currentOrganizationId;
    const menu: Array<[string, string] | 'divider'> = user
        ? [
            ...(org ? ([['Events', route('organizer.home')], ['Payments', route('organizer.payments.index')]] as [string, string][]) : []),
            ...(user.isSuperAdmin ? ([['Admin', route('ventiq.money.index')]] as [string, string][]) : []),
            ['Sessions', route('sessions.index')],
            ['Programmes', route('programmes.index')],
            ...(org ? ([['Organization', route('organizer.organization.edit')]] as [string, string][]) : []),
            ['Team', route('organization.members')],
            ['My account', route('account.edit')],
        ]
        : [];

    return (
        <div className="flex min-h-screen flex-col bg-[#F8FAFC] text-brand">
            <nav className="sticky top-0 z-50 flex-none border-b border-gray-100 bg-white/80 backdrop-blur-md">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 md:h-20">
                    <div className="flex flex-1 items-center gap-8 md:gap-10">
                        <a href="/" className="flex shrink-0 items-center gap-3 transition-transform active:scale-95">
                            <img src="/images/ventiq-logo-112.webp" width={151} height={112} alt="VENTIQ" className="h-6 w-auto object-contain md:h-7" />
                            <div className="hidden flex-col leading-none sm:flex">
                                <span className="text-sm font-black uppercase tracking-tighter text-brand">VENTI<span className="text-action">Q</span></span>
                                <span className="text-[6.5px] font-bold uppercase tracking-[0.2em] text-gray-400">Intelligence</span>
                            </div>
                        </a>

                        {/* Search, with the district it ranks first. */}
                        <div className="relative hidden h-12 items-center rounded-full border border-gray-200 bg-white px-2 shadow-sm transition-all hover:border-gray-300 hover:shadow-md md:flex">
                            <button type="button" onClick={() => setSearching(true)} className="h-full w-40 py-2 pl-4 text-left text-[13px] font-bold text-gray-400">Search event</button>
                            <div className="mx-2 h-5 w-px shrink-0 bg-gray-200" />
                            <DropdownMenu>
                                <DropdownMenuTrigger className="flex w-36 items-center gap-1.5 px-2 text-left outline-none">
                                    <MapPin className="size-3 text-action" />
                                    <span className="truncate text-[13px] font-bold text-brand">{district}</span>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="max-h-64 w-48 overflow-y-auto">
                                    {site.districts.map((d) => (
                                        <DropdownMenuItem key={d} onSelect={() => setDistrict(d)} className={cn(d === district && 'text-action')}>{d}</DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <button type="button" onClick={() => setSearching(true)} aria-label="Search" className="group flex size-9 shrink-0 items-center justify-center rounded-full bg-brand/5 transition-all hover:bg-brand active:scale-95">
                                <Search className="size-3.5 text-brand group-hover:text-white" />
                            </button>
                        </div>

                        <button type="button" onClick={() => setSearching(true)} aria-label="Search" className="flex size-10 items-center justify-center rounded-full bg-gray-50 text-brand md:hidden">
                            <Search className="size-3.5" />
                        </button>
                    </div>

                    <div className="flex shrink-0 items-center gap-4 md:gap-6">
                        <div className="flex items-center gap-1">
                            <a href={route('pricing')} aria-label="Pricing" className="flex h-10 w-10 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-50 hover:text-brand md:w-auto md:gap-2 md:px-4">
                                <Tag className="size-4" />
                                <span className="hidden text-[10px] font-bold uppercase tracking-widest md:inline">Pricing</span>
                            </a>
                            <button type="button" onClick={() => setSupport(true)} aria-label="Support" className="flex size-10 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-50 hover:text-brand">
                                <Headset className="size-4" />
                            </button>
                        </div>

                        {user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger className="group flex items-center gap-2 rounded-full bg-brand py-1.5 pl-1.5 pr-4 text-[10px] font-black uppercase tracking-widest text-white outline-none transition-all duration-300 hover:bg-action active:scale-95">
                                    <img src={user.avatar} alt="" className="size-7 rounded-full bg-white ring-2 ring-white/30" />
                                    {user.firstName}
                                    <ChevronDown className="size-3 transition-transform group-data-[state=open]:rotate-180" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {menu.map((item) => item !== 'divider' && (
                                        <DropdownMenuItem key={item[0]} asChild><a href={item[1]}>{item[0]}</a></DropdownMenuItem>
                                    ))}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onSelect={() => router.post(route('logout'))}>Logout</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <a href={route('login')} className="rounded-full border border-brand/10 px-5 py-2 text-[10px] font-black uppercase tracking-widest text-brand transition-all duration-300 hover:border-brand hover:bg-brand hover:text-white active:scale-95">Login</a>
                        )}
                    </div>
                </div>
            </nav>

            <main className={cn('relative flex-1', bottomBar && 'pb-20 sm:pb-0')}>{children}</main>

            {bottomBar && <div className="fixed inset-x-0 bottom-0 z-40 sm:hidden">{bottomBar}</div>}

            <footer className={cn('flex-none border-t border-gray-100 bg-white px-6 py-3', bottomBar && 'hidden sm:block')}>
                <div className="mx-auto flex max-w-7xl items-center justify-between">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">© {new Date().getFullYear()} VENTI<span className="text-action">Q</span> LESOTHO</p>
                    <div className="flex gap-4 text-[10px] font-bold uppercase tracking-tight text-gray-500">
                        <button type="button" onClick={() => setTerms(true)} className="uppercase hover:text-action">Terms</button>
                        <a href={route('privacy')} className="hover:text-action">Privacy</a>
                        <button type="button" onClick={() => setSupport(true)} className="uppercase hover:text-action">Support</button>
                    </div>
                </div>
            </footer>

            <SearchDialog open={searching} onOpenChange={setSearching} district={district} />
            <SupportDialog open={support} onOpenChange={setSupport} />
            <TermsDialog open={terms} onOpenChange={setTerms} />
            {/* Ask Ventiq is about an organization's sessions (super admins have none). */}
            {user?.organization && <AskVentiq raised={!!bottomBar} />}
        </div>
    );
}
