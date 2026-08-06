import ApplicationLogo from '@/Components/ApplicationLogo';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gray-50 px-4 py-12">
            {/* Soft brand-tinted glow, matching the logo's own gradient — decorative, not interactive */}
            <div
                aria-hidden
                className="pointer-events-none absolute -top-40 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 rounded-full bg-gradient-to-br from-brand-500/20 via-brand-700/10 to-transparent blur-3xl"
            />

            <div className="relative flex flex-col items-center">
                <ApplicationLogo className="h-16 w-auto drop-shadow-sm" />
                <div className="mt-3 text-center">
                    <div className="text-lg font-semibold tracking-tight text-gray-900">JolaxPay</div>
                    <div className="text-sm text-gray-500">Admin</div>
                </div>
            </div>

            <div className="relative mt-8 w-full overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-xl ring-1 ring-gray-900/5 sm:max-w-md sm:px-8">
                {children}
            </div>

            <p className="relative mt-8 text-center text-xs text-gray-400">
                Staff access only — accounts are provisioned by an administrator.
            </p>
        </div>
    );
}
