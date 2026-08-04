<script setup lang="ts">
import { Form, Head, useHttp } from '@inertiajs/vue3';
import { useConnectionStatus, useEchoPresence } from '@laravel/echo-vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import GameStateController from '@/actions/App/Http/Controllers/GameStateController';
import GuessController from '@/actions/App/Http/Controllers/GuessController';
import LeaveGameController from '@/actions/App/Http/Controllers/LeaveGameController';
import StartGameController from '@/actions/App/Http/Controllers/StartGameController';
import DrawingCanvas from '@/components/game/DrawingCanvas.vue';
import type { Game, GameState, Message, Player, Round, Stroke } from '@/types';

const props = defineProps<{
    game: Game;
    player: Player;
    round: Round | null;
}>();

type PresenceMember = { id: number; name: string };
type ChatPayload = { message: Message };
type StrokesPayload = { round_id: number; strokes: Stroke[] };
type CorrectPayload = { player: Player; points: number; message: Message };

const state = ref<GameState>({
    game: props.game,
    player: props.player,
    round: props.round,
});
const onlinePlayerIds = ref<number[]>([props.player.id]);
const copied = ref(false);
const guessFailed = ref(false);
const isClientMounted = ref(false);
const now = ref(0);
const messagesPanel = ref<HTMLElement | null>(null);
const connectionStatus = useConnectionStatus();
const stateRequest = useHttp<Record<string, never>, GameState>({});
const startRequest = useHttp<Record<string, never>, GameState>({});
const guessRequest = useHttp<
    { round_id: number; message: string },
    { correct: boolean; points: number; message: Message }
>({ round_id: props.round?.id ?? 0, message: '' });
const channelName = `game.${props.game.id}`;

let clockTimer: number | null = null;
let reconcileTimer: number | null = null;

const currentGame = computed(() => state.value.game);
const currentPlayer = computed(() => state.value.player);
const currentRound = computed(() => state.value.round);
const isDrawer = computed(
    () => currentRound.value?.drawer_id === currentPlayer.value.id,
);
const displayedConnectionStatus = computed(() =>
    isClientMounted.value ? connectionStatus.value : 'connecting',
);
const remainingSeconds = computed(() => {
    if (!currentRound.value || currentGame.value.status !== 'playing') {
        return 0;
    }

    if (!isClientMounted.value) {
        return currentGame.value.round_duration;
    }

    return Math.max(
        0,
        Math.ceil((Date.parse(currentRound.value.ends_at) - now.value) / 1000),
    );
});
const timerProgress = computed(
    () => (remainingSeconds.value / currentGame.value.round_duration) * 100,
);
const timerLabel = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});
const winner = computed(() => currentGame.value.players[0] ?? null);

function sync(nextState: GameState): void {
    state.value = nextState;
}

async function refreshState(): Promise<void> {
    if (stateRequest.processing) {
        return;
    }

    try {
        sync(
            await stateRequest.get(
                GameStateController.url(currentGame.value.code),
            ),
        );
    } catch {
        // The reactive request state displays connectivity errors without disrupting play.
    }
}

async function startGame(): Promise<void> {
    try {
        sync(
            await startRequest.post(
                StartGameController.url(currentGame.value.code),
            ),
        );
    } catch {
        // Validation details are exposed by useHttp below the button.
    }
}

async function submitGuess(): Promise<void> {
    if (
        !currentRound.value ||
        !guessRequest.message.trim() ||
        guessRequest.processing
    ) {
        return;
    }

    guessFailed.value = false;
    guessRequest.round_id = currentRound.value.id;

    try {
        const response = await guessRequest.post(
            GuessController.url(currentGame.value.code),
        );
        addMessage(response.message);
        guessRequest.message = '';
    } catch {
        guessFailed.value = true;
    }
}

function addStroke(stroke: Stroke): void {
    if (!currentRound.value) {
        return;
    }

    currentRound.value.strokes.push(stroke);
}

function clearStrokes(): void {
    if (currentRound.value) {
        currentRound.value.strokes = [];
    }
}

function addMessage(message: Message): void {
    if (
        !currentRound.value ||
        currentRound.value.messages.some((item) => item.id === message.id)
    ) {
        return;
    }

    currentRound.value.messages.push(message);
}

function addCorrectMessage(payload: CorrectPayload): void {
    addMessage(payload.message);
    void refreshState();
}

async function copyInvite(): Promise<void> {
    await navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1800);
}

function playerAccent(index: number): string {
    return [
        'bg-violet-100',
        'bg-sky-100',
        'bg-amber-100',
        'bg-emerald-100',
        'bg-rose-100',
    ][index % 5];
}

function initials(name: string): string {
    return name.slice(0, 2).toUpperCase();
}

useEchoPresence<Record<string, never>>(
    channelName,
    [
        '.PlayerJoined',
        '.PlayerLeft',
        '.GameStarted',
        '.RoundStarted',
        '.RoundEnded',
        '.GameFinished',
    ],
    () => void refreshState(),
);

useEchoPresence<ChatPayload>(channelName, '.ChatMessage', (payload) =>
    addMessage(payload.message),
);
useEchoPresence<CorrectPayload>(
    channelName,
    '.CorrectGuess',
    addCorrectMessage,
);
useEchoPresence<StrokesPayload>(channelName, '.DrawingStrokes', (payload) => {
    if (payload.round_id !== currentRound.value?.id) {
        return;
    }

    payload.strokes.forEach((stroke) => {
        if (stroke.player_id !== currentPlayer.value.id) {
            addStroke(stroke);
        }
    });
});
useEchoPresence<{ round_id: number }>(
    channelName,
    '.CanvasCleared',
    (payload) => {
        if (payload.round_id === currentRound.value?.id) {
            clearStrokes();
        }
    },
);

const presence = useEchoPresence<Record<string, never>>(channelName);

watch(
    () => currentRound.value?.messages.length,
    async () => {
        await nextTick();
        messagesPanel.value?.scrollTo({
            top: messagesPanel.value.scrollHeight,
            behavior: 'smooth',
        });
    },
);

onMounted(() => {
    now.value = Date.now();
    isClientMounted.value = true;

    presence
        .channel()
        .here((members: PresenceMember[]) => {
            onlinePlayerIds.value = members.map((member) => member.id);
        })
        .joining((member: PresenceMember) => {
            onlinePlayerIds.value = [
                ...new Set([...onlinePlayerIds.value, member.id]),
            ];
        })
        .leaving((member: PresenceMember) => {
            onlinePlayerIds.value = onlinePlayerIds.value.filter(
                (id) => id !== member.id,
            );
        });

    clockTimer = window.setInterval(() => (now.value = Date.now()), 250);
    reconcileTimer = window.setInterval(() => void refreshState(), 15000);
});

onBeforeUnmount(() => {
    if (clockTimer !== null) {
        window.clearInterval(clockTimer);
    }

    if (reconcileTimer !== null) {
        window.clearInterval(reconcileTimer);
    }
});
</script>

<template>
    <Head :title="`Room ${currentGame.code}`" />

    <main class="min-h-screen bg-[#fffaf0] text-zinc-900">
        <header class="border-b-2 border-zinc-900 bg-white">
            <div
                class="mx-auto flex w-full max-w-[1500px] flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-11 w-11 -rotate-3 place-items-center rounded-xl border-2 border-zinc-900 bg-violet-300 text-xl shadow-[2px_2px_0_#18181b]"
                    >
                        ✏️
                    </div>
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.16em] text-zinc-400 uppercase"
                        >
                            Scribble room
                        </p>
                        <button
                            type="button"
                            class="flex items-center gap-2 font-mono text-xl font-black tracking-[0.13em] hover:text-violet-600"
                            @click="copyInvite"
                        >
                            {{ currentGame.code }}
                            <span
                                class="font-sans text-xs tracking-normal text-zinc-400"
                                >{{ copied ? 'Copied!' : 'Copy link' }}</span
                            >
                        </button>
                    </div>
                </div>

                <div
                    v-if="currentGame.status === 'playing' && currentRound"
                    class="flex min-w-64 items-center gap-3"
                >
                    <div class="grow">
                        <div
                            class="mb-1 flex items-center justify-between text-xs font-black uppercase"
                        >
                            <span
                                >Round {{ currentRound.number }} /
                                {{ currentGame.total_rounds }}</span
                            >
                            <span
                                :class="
                                    remainingSeconds <= 10
                                        ? 'text-red-600'
                                        : 'text-zinc-600'
                                "
                                >{{ timerLabel }}</span
                            >
                        </div>
                        <div
                            class="h-3 overflow-hidden rounded-full border-2 border-zinc-900 bg-zinc-100"
                        >
                            <div
                                class="h-full bg-lime-400 transition-[width] duration-300"
                                :class="{
                                    'bg-red-400': remainingSeconds <= 10,
                                }"
                                :style="{ width: `${timerProgress}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-2 text-xs font-bold text-zinc-500"
                    >
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="
                                displayedConnectionStatus === 'connected'
                                    ? 'bg-emerald-500'
                                    : displayedConnectionStatus === 'failed'
                                      ? 'bg-red-500'
                                      : 'animate-pulse bg-amber-400'
                            "
                        />
                        {{
                            displayedConnectionStatus === 'connected'
                                ? 'Live'
                                : displayedConnectionStatus
                        }}
                    </span>
                    <Form
                        v-if="currentGame.status !== 'playing'"
                        v-bind="LeaveGameController.form(currentGame.code)"
                        #default="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="rounded-xl border-2 border-zinc-300 px-3 py-2 text-xs font-black hover:border-red-300 hover:bg-red-50"
                        >
                            Leave
                        </button>
                    </Form>
                </div>
            </div>
        </header>

        <section
            v-if="currentGame.status === 'waiting'"
            class="mx-auto grid min-h-[calc(100vh-74px)] w-full max-w-6xl place-items-center px-5 py-10"
        >
            <div
                class="grid w-full items-center gap-10 lg:grid-cols-[1fr_0.9fr]"
            >
                <div>
                    <div
                        class="mb-5 inline-flex rotate-[-1deg] items-center gap-2 rounded-full border-2 border-zinc-900 bg-lime-300 px-4 py-2 text-sm font-black shadow-[3px_3px_0_#18181b]"
                    >
                        <span
                            class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-600"
                        />
                        Lobby is live
                    </div>
                    <h1
                        class="text-5xl leading-tight font-black tracking-[-0.045em] sm:text-6xl"
                    >
                        The sketch crew<br /><span class="text-violet-600"
                            >is assembling.</span
                        >
                    </h1>
                    <p
                        class="mt-5 max-w-lg text-lg leading-relaxed text-zinc-600"
                    >
                        Share room code
                        <strong
                            class="font-mono tracking-widest text-zinc-900"
                            >{{ currentGame.code }}</strong
                        >. The host can kick things off when at least two
                        artists are ready.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-2xl border-2 border-zinc-900 bg-sky-300 px-5 py-3 font-black shadow-[3px_3px_0_#18181b] transition hover:-translate-y-0.5"
                            @click="copyInvite"
                        >
                            {{ copied ? 'Invite copied!' : 'Copy invite link' }}
                        </button>
                        <button
                            v-if="currentPlayer.is_host"
                            type="button"
                            :disabled="
                                startRequest.processing ||
                                currentGame.players.length < 2
                            "
                            class="rounded-2xl border-2 border-zinc-900 bg-violet-600 px-6 py-3 font-black text-white shadow-[3px_3px_0_#18181b] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:bg-zinc-300"
                            @click="startGame"
                        >
                            {{
                                startRequest.processing
                                    ? 'Starting…'
                                    : currentGame.players.length < 2
                                      ? 'Waiting for a friend'
                                      : 'Start game'
                            }}
                        </button>
                    </div>
                    <p
                        v-if="startRequest.hasErrors"
                        class="mt-3 text-sm font-semibold text-red-600"
                    >
                        {{ Object.values(startRequest.errors)[0] }}
                    </p>
                    <p
                        v-else-if="!currentPlayer.is_host"
                        class="mt-3 text-sm font-semibold text-zinc-500"
                    >
                        Waiting for the host to start the currentGame.
                    </p>
                </div>

                <div class="relative">
                    <div
                        class="absolute -inset-3 rotate-2 rounded-[2rem] bg-amber-300"
                    />
                    <div
                        class="relative rounded-[1.75rem] border-3 border-zinc-900 bg-white p-6 shadow-[7px_7px_0_#18181b]"
                    >
                        <div class="mb-5 flex items-center justify-between">
                            <h2 class="text-xl font-black">Players</h2>
                            <span
                                class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-black"
                                >{{ currentGame.players.length }} / 8</span
                            >
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="(
                                    roomPlayer, index
                                ) in currentGame.players"
                                :key="roomPlayer.id"
                                class="flex items-center gap-3 rounded-2xl border-2 border-zinc-200 p-3"
                                :class="playerAccent(index)"
                            >
                                <div
                                    class="grid h-11 w-11 place-items-center rounded-xl border-2 border-zinc-900 bg-white font-black"
                                >
                                    {{ initials(roomPlayer.name) }}
                                </div>
                                <div class="min-w-0 grow">
                                    <p class="truncate font-black">
                                        {{ roomPlayer.name }}
                                        <span
                                            v-if="
                                                roomPlayer.id ===
                                                currentPlayer.id
                                            "
                                            class="text-xs text-zinc-400"
                                            >(you)</span
                                        >
                                    </p>
                                    <p class="text-xs font-bold text-zinc-500">
                                        {{
                                            roomPlayer.is_host
                                                ? 'Host'
                                                : 'Ready to draw'
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="h-3 w-3 rounded-full"
                                    :class="
                                        onlinePlayerIds.includes(roomPlayer.id)
                                            ? 'bg-emerald-500'
                                            : 'bg-zinc-300'
                                    "
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-else-if="currentGame.status === 'playing' && currentRound"
            class="mx-auto grid w-full max-w-[1500px] gap-4 px-4 py-5 lg:grid-cols-[240px_minmax(0,1fr)_320px] lg:px-6"
        >
            <aside
                class="order-2 rounded-3xl border-2 border-zinc-900 bg-white p-4 lg:order-1"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-black">Scoreboard</h2>
                    <span class="text-xs font-bold text-zinc-400"
                        >{{ onlinePlayerIds.length }} online</span
                    >
                </div>
                <div class="grid gap-2">
                    <div
                        v-for="(roomPlayer, index) in currentGame.players"
                        :key="roomPlayer.id"
                        class="flex items-center gap-2 rounded-2xl border-2 border-zinc-100 p-2.5"
                        :class="{
                            'border-violet-300 bg-violet-50':
                                roomPlayer.id === currentRound.drawer_id,
                        }"
                    >
                        <span
                            class="w-5 text-center text-xs font-black text-zinc-400"
                            >{{ index + 1 }}</span
                        >
                        <div
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl font-black"
                            :class="playerAccent(index)"
                        >
                            {{ initials(roomPlayer.name) }}
                        </div>
                        <div class="min-w-0 grow">
                            <p class="truncate text-sm font-black">
                                {{ roomPlayer.name }}
                            </p>
                            <p class="text-[11px] font-bold text-zinc-400">
                                {{
                                    roomPlayer.id === currentRound.drawer_id
                                        ? 'drawing now'
                                        : roomPlayer.id === currentPlayer.id
                                          ? 'that’s you'
                                          : 'guessing'
                                }}
                            </p>
                        </div>
                        <span class="font-mono text-sm font-black">{{
                            roomPlayer.score
                        }}</span>
                    </div>
                </div>
            </aside>

            <div class="order-1 grid content-start gap-4 lg:order-2">
                <div
                    class="flex min-h-20 items-center justify-center rounded-3xl border-2 border-zinc-900 bg-white px-4 py-3 text-center"
                >
                    <div>
                        <p
                            class="text-[11px] font-black tracking-[0.2em] text-zinc-400 uppercase"
                        >
                            {{
                                isDrawer
                                    ? 'Your word'
                                    : `${currentRound.drawer_name} is drawing`
                            }}
                        </p>
                        <p
                            class="mt-1 font-mono text-2xl font-black tracking-[0.18em] sm:text-3xl"
                            :class="
                                isDrawer ? 'text-violet-600' : 'text-zinc-800'
                            "
                        >
                            {{ currentRound.word || currentRound.masked_word }}
                        </p>
                    </div>
                </div>

                <DrawingCanvas
                    :key="currentRound.id"
                    :game-code="currentGame.code"
                    :round-id="currentRound.id"
                    :strokes="currentRound.strokes"
                    :can-draw="isDrawer"
                    :disabled="remainingSeconds === 0"
                    @stroke="addStroke"
                    @cleared="clearStrokes"
                />
            </div>

            <aside
                class="order-3 flex min-h-[420px] flex-col overflow-hidden rounded-3xl border-2 border-zinc-900 bg-white"
            >
                <div class="border-b-2 border-zinc-900 bg-sky-100 px-4 py-3">
                    <h2 class="font-black">Guesses</h2>
                    <p class="text-xs font-semibold text-zinc-500">
                        Say what you see. Spelling counts a little.
                    </p>
                </div>
                <div
                    ref="messagesPanel"
                    class="flex max-h-[420px] min-h-64 grow flex-col gap-2 overflow-y-auto p-3"
                >
                    <div
                        v-if="currentRound.messages.length === 0"
                        class="grid grow place-items-center px-6 text-center text-sm font-semibold text-zinc-400"
                    >
                        No guesses yet.<br />Be brave and go first.
                    </div>
                    <div
                        v-for="message in currentRound.messages"
                        :key="message.id"
                        class="rounded-2xl px-3 py-2 text-sm"
                        :class="
                            message.is_correct
                                ? 'border-2 border-emerald-300 bg-emerald-50 text-emerald-800'
                                : message.player_id === currentPlayer.id
                                  ? 'ml-5 bg-violet-100'
                                  : 'mr-5 bg-zinc-100'
                        "
                    >
                        <span class="font-black">{{ message.player }}</span>
                        <span
                            v-if="message.round_number !== currentRound.number"
                            class="ml-1 text-[10px] font-black text-zinc-400 uppercase"
                            >R{{ message.round_number }}</span
                        >
                        <span class="ml-1 font-medium">{{
                            message.message
                        }}</span>
                    </div>
                </div>
                <form
                    v-if="!isDrawer"
                    class="grid gap-2 border-t-2 border-zinc-900 p-3"
                    @submit.prevent="submitGuess"
                >
                    <div class="flex gap-2">
                        <input
                            v-model="guessRequest.message"
                            maxlength="120"
                            autocomplete="off"
                            placeholder="Type a guess…"
                            class="h-12 min-w-0 grow rounded-xl border-2 border-zinc-300 px-3 font-semibold outline-none focus:border-violet-500 focus:ring-3 focus:ring-violet-100"
                        />
                        <button
                            type="submit"
                            :disabled="
                                guessRequest.processing ||
                                remainingSeconds === 0
                            "
                            class="rounded-xl border-2 border-zinc-900 bg-lime-300 px-4 font-black shadow-[2px_2px_0_#18181b] disabled:opacity-50"
                        >
                            Send
                        </button>
                    </div>
                    <p
                        v-if="guessRequest.hasErrors"
                        class="text-xs font-semibold text-red-600"
                    >
                        {{ Object.values(guessRequest.errors)[0] }}
                    </p>
                    <p
                        v-else-if="guessFailed"
                        class="text-xs font-semibold text-red-600"
                    >
                        That guess could not be sent. Please try again.
                    </p>
                </form>
                <div
                    v-else
                    class="border-t-2 border-zinc-900 bg-violet-50 p-4 text-center text-sm font-bold text-violet-700"
                >
                    You’re drawing—make it recognizable!
                </div>
            </aside>
        </section>

        <section
            v-else
            class="mx-auto grid min-h-[calc(100vh-74px)] w-full max-w-4xl place-items-center px-5 py-12"
        >
            <div
                class="w-full rounded-[2rem] border-3 border-zinc-900 bg-white p-7 text-center shadow-[8px_8px_0_#18181b] sm:p-10"
            >
                <div class="text-6xl">🏆</div>
                <p
                    class="mt-5 text-xs font-black tracking-[0.22em] text-violet-600 uppercase"
                >
                    Game complete
                </p>
                <h1 class="mt-2 text-5xl font-black tracking-tight">
                    {{ winner?.name }} wins!
                </h1>
                <p class="mt-3 text-zinc-500">
                    The art was temporary. The bragging rights are forever.
                </p>

                <div class="mx-auto mt-8 grid max-w-xl gap-3">
                    <div
                        v-for="(roomPlayer, index) in currentGame.players"
                        :key="roomPlayer.id"
                        class="flex items-center gap-4 rounded-2xl border-2 p-4 text-left"
                        :class="
                            index === 0
                                ? 'border-amber-400 bg-amber-100'
                                : 'border-zinc-200 bg-zinc-50'
                        "
                    >
                        <span class="w-8 text-2xl font-black">{{
                            index === 0 ? '🥇' : `#${index + 1}`
                        }}</span>
                        <span class="grow font-black">{{
                            roomPlayer.name
                        }}</span>
                        <span class="font-mono text-xl font-black">{{
                            roomPlayer.score
                        }}</span>
                    </div>
                </div>

                <Form
                    v-bind="LeaveGameController.form(currentGame.code)"
                    class="mt-8"
                    #default="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="rounded-2xl border-2 border-zinc-900 bg-violet-600 px-7 py-3 font-black text-white shadow-[3px_3px_0_#18181b]"
                    >
                        Back home
                    </button>
                </Form>
            </div>
        </section>

        <div
            v-if="stateRequest.hasErrors"
            class="fixed right-4 bottom-4 rounded-2xl border-2 border-red-300 bg-white px-4 py-3 text-sm font-bold text-red-700 shadow-lg"
        >
            Realtime sync paused. Retrying…
        </div>
    </main>
</template>
