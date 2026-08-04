<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import JoinGameController from '@/actions/App/Http/Controllers/JoinGameController';
import { home } from '@/routes';

defineProps<{ code: string }>();
</script>

<template>
    <Head :title="`Join ${code}`" />

    <main
        class="grid min-h-screen place-items-center overflow-hidden bg-[#eefaff] px-5 py-12 text-zinc-900"
    >
        <div class="relative w-full max-w-lg">
            <div
                class="absolute -inset-4 -rotate-2 rounded-[2.5rem] bg-amber-300"
            />
            <div
                class="relative rounded-[2rem] border-3 border-zinc-900 bg-white p-7 shadow-[8px_8px_0_#18181b] sm:p-10"
            >
                <Link
                    :href="home()"
                    class="mb-8 inline-flex items-center gap-2 text-sm font-bold text-zinc-500 hover:text-zinc-900"
                    >← Back home</Link
                >
                <p
                    class="text-xs font-black tracking-[0.22em] text-sky-600 uppercase"
                >
                    You’ve been invited
                </p>
                <h1 class="mt-2 text-4xl font-black tracking-tight">
                    Join room
                    <span class="font-mono text-violet-600">{{ code }}</span>
                </h1>
                <p class="mt-3 text-zinc-500">
                    Pick a name your friends will recognize. Or won’t. We don’t
                    judge.
                </p>

                <Form
                    v-bind="JoinGameController.form()"
                    class="mt-8 grid gap-4"
                    #default="{ errors, processing }"
                >
                    <input type="hidden" name="code" :value="code" />
                    <label for="name" class="text-sm font-bold"
                        >Your drawing name</label
                    >
                    <input
                        id="name"
                        name="name"
                        autocomplete="nickname"
                        autofocus
                        maxlength="24"
                        placeholder="e.g. ScribbleSam"
                        class="h-14 rounded-2xl border-2 border-zinc-300 bg-[#fffdf7] px-4 text-lg font-semibold outline-none focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                    />
                    <p
                        v-if="errors.name || errors.code"
                        class="text-sm font-semibold text-red-600"
                    >
                        {{ errors.name || errors.code }}
                    </p>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="h-14 rounded-2xl border-2 border-zinc-900 bg-violet-600 text-lg font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:bg-violet-500 disabled:opacity-60"
                    >
                        {{
                            processing ? 'Joining the crew…' : 'Enter the lobby'
                        }}
                    </button>
                </Form>
            </div>
        </div>
    </main>
</template>
