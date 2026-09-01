import ApplicationLogo from '@/Components/ApplicationLogo';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return <div className="min-h-screen bg-[#ebe8e8] p-0 sm:p-5 lg:p-8">
        <div className="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl overflow-hidden bg-white shadow-2xl shadow-gray-900/10 sm:rounded-[30px] lg:grid-cols-[.95fr_1.05fr]">
            <section className="relative hidden overflow-hidden bg-gradient-to-br from-brand-900 via-brand-800 to-brand-600 p-10 text-white lg:flex lg:flex-col">
                <div className="absolute -right-32 -top-32 h-96 w-96 rounded-full border-[65px] border-white/5"/><div className="absolute -bottom-40 -left-36 h-[30rem] w-[30rem] rounded-full border-[70px] border-white/5"/>
                <div className="relative flex items-center gap-3"><span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-lg"><ApplicationLogo className="h-9 w-auto"/></span><div><div className="text-xl font-bold tracking-tight">JolaxPay</div><div className="text-xs uppercase tracking-[0.2em] text-brand-200">Admin console</div></div></div>
                <div className="relative my-auto max-w-md"><div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15"><svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg></div><h1 className="text-4xl font-bold leading-tight tracking-tight">Payments and utility operations, beautifully organised.</h1><p className="mt-5 max-w-sm text-sm leading-7 text-brand-100/80">Manage transactions, provider health, customer support and reconciliation from one secure workspace.</p></div>
                <div className="relative grid grid-cols-3 gap-3">{['Secure access','Live monitoring','Clear audit trail'].map((item) => <div key={item} className="rounded-xl bg-white/8 px-3 py-3 text-xs font-medium text-brand-50 ring-1 ring-white/10">{item}</div>)}</div>
            </section>
            <main className="flex items-center justify-center px-6 py-12 sm:px-12 lg:px-16"><div className="w-full max-w-md"><div className="mb-10 flex items-center gap-3 lg:hidden"><ApplicationLogo className="h-11 w-auto"/><div><div className="font-bold text-gray-950">JolaxPay</div><div className="text-xs text-brand-600">Admin console</div></div></div>{children}<p className="mt-10 text-center text-xs text-gray-400">Staff access only · Protected JolaxPay workspace</p></div></main>
        </div>
    </div>;
}
