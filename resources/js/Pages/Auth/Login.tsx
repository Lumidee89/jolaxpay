import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-8">
                <span className="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">Secure staff access</span>
                <h1 className="mt-5 text-3xl font-bold tracking-tight text-gray-950">Welcome back</h1>
                <p className="mt-2 text-sm leading-6 text-gray-500">Enter your staff credentials to continue to the JolaxPay operations dashboard.</p>
            </div>

            {status && (
                <div className="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-2 block h-14 w-full rounded-xl border-gray-200 bg-gray-50 px-4 shadow-none focus:border-brand-500 focus:ring-brand-500"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-2 block h-14 w-full rounded-xl border-gray-200 bg-gray-50 px-4 shadow-none focus:border-brand-500 focus:ring-brand-500"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Remember me
                        </span>
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm font-medium text-brand-700 hover:text-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2"
                        >
                            Forgot your password?
                        </Link>
                    )}

                </div>

                <PrimaryButton className="flex h-14 w-full justify-center rounded-xl bg-brand-700 text-sm shadow-lg shadow-brand-800/20 hover:bg-brand-800" disabled={processing}>
                        Log in
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
