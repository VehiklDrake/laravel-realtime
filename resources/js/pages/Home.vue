<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store as storeGame } from '@/actions/App/Http/Controllers/GameController';
import JoinGameController from '@/actions/App/Http/Controllers/JoinGameController';

defineProps<{
    defaults: {
        rounds: number;
        roundDuration: number;
    };
}>();
</script>

<template>
    <Head title="Draw together" />

    <main
        class="relative min-h-screen overflow-hidden bg-[#fffaf0] text-zinc-900"
    >
        <div
            class="pointer-events-none absolute inset-0 opacity-40"
            aria-hidden="true"
        >
            <div
                class="absolute top-16 left-[8%] h-24 w-24 rotate-12 rounded-[42%] border-4 border-dashed border-coral-400"
            />
            <div
                class="absolute right-[10%] bottom-24 h-32 w-32 -rotate-6 rounded-full border-4 border-sky-300"
            />
            <svg
                class="absolute top-20 right-[13%] h-28 w-40 text-violet-300"
                viewBox="0 0 160 112"
                fill="none"
            >
                <path
                    d="M8 84C32 14 58 100 82 36C101 -13 119 91 153 18"
                    stroke="currentColor"
                    stroke-width="7"
                    stroke-linecap="round"
                />
            </svg>
            <svg
                class="absolute bottom-12 left-[12%] h-20 w-36 text-amber-300"
                viewBox="0 0 144 80"
                fill="none"
            >
                <path
                    d="M8 63C28 14 40 69 62 24C80 -13 102 78 136 11"
                    stroke="currentColor"
                    stroke-width="8"
                    stroke-linecap="round"
                />
            </svg>
        </div>

        <section
            class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-5 py-12 sm:px-8"
        >
            <div
                class="grid w-full items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]"
            >
                <div class="max-w-xl">
                    <div
                        class="mb-7 inline-flex rotate-[-2deg] items-center gap-2 rounded-full border-2 border-zinc-900 bg-lime-300 px-4 py-2 text-sm font-bold shadow-[3px_3px_0_#18181b]"
                    >
                        <span
                            class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-600"
                        />
                        Live rooms · zero sign-up
                    </div>

                    <h1
                        class="font-display text-6xl leading-[0.92] font-black tracking-[-0.055em] sm:text-7xl lg:text-8xl"
                    >
                        Draw it.<br />
                        <span class="relative inline-block text-violet-600">
                            Guess it.
                            <svg
                                class="absolute -bottom-3 left-0 h-4 w-full text-coral-500"
                                viewBox="0 0 320 18"
                                preserveAspectRatio="none"
                                fill="none"
                            >
                                <path
                                    d="M5 11C70 3 124 17 188 9C240 3 274 4 315 7"
                                    stroke="currentColor"
                                    stroke-width="7"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>
                    </h1>

                    <p
                        class="mt-9 max-w-lg text-lg leading-relaxed text-zinc-600 sm:text-xl"
                    >
                        A fast, friendly drawing game powered by Laravel Reverb.
                        Share one room code and let the questionable art begin.
                    </p>

                    <div
                        class="mt-8 flex flex-wrap gap-3 text-sm font-semibold text-zinc-600"
                    >
                        <span
                            class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-zinc-900/10"
                            >{{ defaults.rounds }} quick rounds</span
                        >
                        <span
                            class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-zinc-900/10"
                            >{{ defaults.roundDuration }} seconds each</span
                        >
                        <span
                            class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-zinc-900/10"
                            >2–8 players</span
                        >
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="absolute -inset-3 rotate-2 rounded-[2.25rem] bg-violet-300"
                    />
                    <div
                        class="relative rounded-[2rem] border-3 border-zinc-900 bg-white p-6 shadow-[8px_8px_0_#18181b] sm:p-8"
                    >
                        <div
                            class="mb-7 flex items-center justify-between gap-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-black tracking-[0.22em] text-violet-600 uppercase"
                                >
                                    Start here
                                </p>
                                <h2
                                    class="mt-1 text-3xl font-black tracking-tight"
                                >
                                    Grab a marker
                                </h2>
                            </div>
                            <div
                                class="grid h-14 w-14 rotate-6 place-items-center rounded-2xl bg-amber-300 text-3xl shadow-[3px_3px_0_#18181b]"
                            >
                                ✏️
                            </div>
                        </div>

                        <Form
                            v-bind="storeGame.form()"
                            class="grid gap-3"
                            #default="{ errors, processing }"
                        >
                            <label for="create-name" class="text-sm font-bold"
                                >Your drawing name</label
                            >
                            <input
                                id="create-name"
                                name="name"
                                autocomplete="nickname"
                                maxlength="24"
                                placeholder="e.g. DoodleDan"
                                class="h-14 rounded-2xl border-2 border-zinc-300 bg-[#fffdf7] px-4 text-lg font-semibold transition outline-none focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                            />
                            <p
                                v-if="errors.name"
                                class="text-sm font-semibold text-red-600"
                            >
                                {{ errors.name }}
                            </p>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="mt-1 h-14 rounded-2xl border-2 border-zinc-900 bg-violet-600 px-5 text-lg font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:bg-violet-500 disabled:cursor-wait disabled:opacity-60"
                            >
                                {{
                                    processing
                                        ? 'Making room…'
                                        : 'Create a game'
                                }}
                            </button>
                        </Form>

                        <div
                            class="my-7 flex items-center gap-3 text-xs font-black tracking-[0.2em] text-zinc-400 uppercase"
                        >
                            <span class="h-px grow bg-zinc-200" />or join
                            friends<span class="h-px grow bg-zinc-200" />
                        </div>

                        <Form
                            v-bind="JoinGameController.form()"
                            class="grid gap-3 sm:grid-cols-[0.85fr_1.15fr]"
                            #default="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <label for="room-code" class="text-sm font-bold"
                                    >Room code</label
                                >
                                <input
                                    id="room-code"
                                    name="code"
                                    maxlength="6"
                                    placeholder="ABC123"
                                    class="h-13 rounded-2xl border-2 border-zinc-300 px-4 font-mono text-lg font-black tracking-[0.18em] uppercase outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100"
                                />
                            </div>
                            <div class="grid gap-2">
                                <label for="join-name" class="text-sm font-bold"
                                    >Your name</label
                                >
                                <input
                                    id="join-name"
                                    name="name"
                                    autocomplete="nickname"
                                    maxlength="24"
                                    placeholder="Picasso-ish"
                                    class="h-13 rounded-2xl border-2 border-zinc-300 px-4 font-semibold outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100"
                                />
                            </div>
                            <p
                                v-if="errors.code || errors.name"
                                class="text-sm font-semibold text-red-600 sm:col-span-2"
                            >
                                {{ errors.code || errors.name }}
                            </p>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="h-13 rounded-2xl border-2 border-zinc-900 bg-sky-300 px-5 font-black shadow-[3px_3px_0_#18181b] transition hover:-translate-y-0.5 hover:bg-sky-200 disabled:opacity-60 sm:col-span-2"
                            >
                                {{ processing ? 'Joining…' : 'Join the room' }}
                            </button>
                        </Form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
