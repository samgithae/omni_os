<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

const year = new Date().getFullYear();
</script>

<template>
    <Head title="Omni OS" />

    <div
        class="flex min-h-screen flex-col bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900"
    >
        <!-- Nav -->
        <header class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-2">
                <img
                    src="/images/omni-logo-sm.png"
                    alt="Omni OS"
                    class="h-7 w-7"
                />
                <span class="text-sm font-semibold text-white/90">Omni OS</span>
            </div>
            <nav class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-lg bg-white/10 px-4 py-1.5 text-xs font-medium text-white/80 transition hover:bg-white/20"
                    >Dashboard</Link
                >
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-lg bg-white/10 px-4 py-1.5 text-xs font-medium text-white/80 transition hover:bg-white/20"
                        >Log in</Link
                    >
                    <Link
                        :href="register()"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-500"
                        >Register</Link
                    >
                </template>
            </nav>
        </header>

        <!-- Hero -->
        <main
            class="flex flex-1 flex-col items-center justify-center px-6 text-center"
        >
            <img
                src="/images/omni-logo.png"
                alt="Omni OS"
                class="mb-8 w-48 max-w-[50vw] opacity-90 drop-shadow-2xl"
            />

            <h1
                class="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl"
            >
                Omni OS
            </h1>

            <p
                class="mt-4 text-lg font-medium tracking-wide text-indigo-200/80 sm:text-xl"
            >
                You sleep. It sells.
            </p>

            <div class="mt-10">
                <Link
                    v-if="!$page.props.auth.user"
                    :href="login()"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 transition hover:bg-indigo-500 hover:shadow-indigo-500/40"
                >
                    Get Started
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 7l5 5m0 0l-5 5m5-5H6"
                        />
                    </svg>
                </Link>
                <Link
                    v-else
                    :href="dashboard()"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 transition hover:bg-indigo-500"
                >
                    Go to Dashboard
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 7l5 5m0 0l-5 5m5-5H6"
                        />
                    </svg>
                </Link>
            </div>
        </main>

        <!-- Footer -->
        <footer class="px-6 py-4 text-center text-[11px] text-white/30">
            &copy; {{ year }} Omni OS. All rights reserved.
        </footer>
    </div>
</template>
