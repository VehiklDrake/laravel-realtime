<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/DrawingController';
import type { Stroke } from '@/types';

const props = defineProps<{
    gameCode: string;
    roundId: number;
    strokes: Stroke[];
    canDraw: boolean;
    disabled: boolean;
}>();

const emit = defineEmits<{
    stroke: [stroke: Stroke];
    cleared: [];
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
const color = ref('#18181b');
const brushSize = ref(6);
const drawing = ref(false);
const colors = [
    '#18181b',
    '#7c3aed',
    '#0284c7',
    '#059669',
    '#eab308',
    '#f97316',
    '#e11d48',
];
const pendingStrokes: Stroke[] = [];
const drawingRequest = useHttp<{ round_id: number; strokes: Stroke[] }>({
    round_id: props.roundId,
    strokes: [],
});
const clearRequest = useHttp<{ round_id: number }>({
    round_id: props.roundId,
});

let lastPoint: { x: number; y: number } | null = null;
let resizeObserver: ResizeObserver | null = null;
let flushTimer: number | null = null;
let isUnmounted = false;

function context(): CanvasRenderingContext2D | null {
    return canvas.value?.getContext('2d') ?? null;
}

function drawStroke(stroke: Stroke): void {
    const element = canvas.value;
    const drawingContext = context();

    if (!element || !drawingContext) {
        return;
    }

    drawingContext.beginPath();
    drawingContext.moveTo(
        stroke.from_x * element.width,
        stroke.from_y * element.height,
    );
    drawingContext.lineTo(
        stroke.to_x * element.width,
        stroke.to_y * element.height,
    );
    drawingContext.strokeStyle = stroke.color;
    drawingContext.lineWidth = stroke.size * window.devicePixelRatio;
    drawingContext.lineCap = 'round';
    drawingContext.lineJoin = 'round';
    drawingContext.stroke();
}

function redraw(): void {
    const element = canvas.value;
    const drawingContext = context();

    if (!element || !drawingContext) {
        return;
    }

    drawingContext.fillStyle = '#ffffff';
    drawingContext.fillRect(0, 0, element.width, element.height);
    props.strokes.forEach(drawStroke);
}

function resize(): void {
    const element = canvas.value;

    if (!element) {
        return;
    }

    const bounds = element.getBoundingClientRect();
    const scale = window.devicePixelRatio || 1;
    element.width = Math.max(1, Math.round(bounds.width * scale));
    element.height = Math.max(1, Math.round(bounds.height * scale));
    redraw();
}

function point(event: PointerEvent): { x: number; y: number } {
    const bounds = canvas.value!.getBoundingClientRect();

    return {
        x: Math.min(
            1,
            Math.max(0, (event.clientX - bounds.left) / bounds.width),
        ),
        y: Math.min(
            1,
            Math.max(0, (event.clientY - bounds.top) / bounds.height),
        ),
    };
}

function startDrawing(event: PointerEvent): void {
    if (!props.canDraw || props.disabled) {
        return;
    }

    canvas.value?.setPointerCapture(event.pointerId);
    drawing.value = true;
    lastPoint = point(event);
}

function continueDrawing(event: PointerEvent): void {
    if (!drawing.value || !lastPoint) {
        return;
    }

    const nextPoint = point(event);
    const stroke: Stroke = {
        from_x: lastPoint.x,
        from_y: lastPoint.y,
        to_x: nextPoint.x,
        to_y: nextPoint.y,
        color: color.value,
        size: brushSize.value,
    };

    drawStroke(stroke);
    emit('stroke', stroke);
    pendingStrokes.push(stroke);
    lastPoint = nextPoint;
    scheduleFlush();
}

function stopDrawing(): void {
    drawing.value = false;
    lastPoint = null;
    void flush();
}

function scheduleFlush(delay = 250): void {
    if (isUnmounted || flushTimer !== null) {
        return;
    }

    flushTimer = window.setTimeout(() => {
        flushTimer = null;
        void flush();
    }, delay);
}

async function flush(): Promise<void> {
    if (isUnmounted) {
        return;
    }

    if (drawingRequest.processing) {
        scheduleFlush();

        return;
    }

    const strokes = pendingStrokes.splice(0, 50);

    if (strokes.length === 0) {
        return;
    }

    drawingRequest.strokes = strokes;
    drawingRequest.round_id = props.roundId;

    try {
        await drawingRequest.post(store.url(props.gameCode));
    } catch {
        if (!isUnmounted && !props.disabled) {
            pendingStrokes.unshift(...strokes);
            scheduleFlush(500);
        }
    } finally {
        if (!isUnmounted && pendingStrokes.length > 0) {
            scheduleFlush();
        }
    }
}

async function clearCanvas(): Promise<void> {
    clearRequest.round_id = props.roundId;

    try {
        await clearRequest.delete(destroy.url(props.gameCode));
        emit('cleared');
        await nextTick();
        redraw();
    } catch {
        // The request state renders the server error without leaking a rejected event promise.
    }
}

watch(
    () => props.strokes.length,
    () => redraw(),
);

onMounted(() => {
    resizeObserver = new ResizeObserver(resize);

    if (canvas.value) {
        resizeObserver.observe(canvas.value);
    }

    resize();
});

onBeforeUnmount(() => {
    isUnmounted = true;
    resizeObserver?.disconnect();
    drawingRequest.cancel();
    clearRequest.cancel();

    if (flushTimer !== null) {
        window.clearTimeout(flushTimer);
    }
});
</script>

<template>
    <div class="grid gap-3">
        <div
            class="relative aspect-[4/3] overflow-hidden rounded-3xl border-3 border-zinc-900 bg-white shadow-[5px_5px_0_#18181b]"
        >
            <canvas
                ref="canvas"
                class="h-full w-full touch-none"
                :class="
                    canDraw && !disabled ? 'cursor-crosshair' : 'cursor-default'
                "
                aria-label="Shared drawing canvas"
                @pointerdown="startDrawing"
                @pointermove="continueDrawing"
                @pointerup="stopDrawing"
                @pointercancel="stopDrawing"
                @pointerleave="stopDrawing"
            />

            <div
                v-if="!canDraw"
                class="pointer-events-none absolute inset-x-0 bottom-4 flex justify-center"
            >
                <span
                    class="rounded-full bg-zinc-900/85 px-4 py-2 text-xs font-bold text-white backdrop-blur"
                    >Watch closely—then guess!</span
                >
            </div>
        </div>

        <div
            v-if="canDraw"
            class="flex flex-wrap items-center gap-3 rounded-2xl border-2 border-zinc-200 bg-white p-3"
        >
            <div class="flex items-center gap-1.5" aria-label="Brush colors">
                <button
                    v-for="swatch in colors"
                    :key="swatch"
                    type="button"
                    :aria-label="`Use ${swatch}`"
                    class="h-7 w-7 rounded-full border-2 transition hover:scale-110"
                    :class="
                        color === swatch
                            ? 'border-zinc-900 ring-2 ring-zinc-300'
                            : 'border-white'
                    "
                    :style="{ backgroundColor: swatch }"
                    @click="color = swatch"
                />
            </div>

            <label
                class="flex min-w-32 grow items-center gap-2 text-xs font-bold text-zinc-500"
            >
                Size
                <input
                    v-model.number="brushSize"
                    type="range"
                    min="1"
                    max="32"
                    class="accent-violet-600"
                />
            </label>

            <button
                type="button"
                :disabled="clearRequest.processing || disabled"
                class="rounded-xl border-2 border-zinc-900 bg-zinc-100 px-4 py-2 text-xs font-black transition hover:bg-red-100 disabled:opacity-50"
                @click="clearCanvas"
            >
                Clear
            </button>
        </div>

        <p
            v-if="drawingRequest.hasErrors"
            class="text-sm font-semibold text-red-600"
        >
            A stroke could not be synced. Keep drawing and we’ll retry the next
            batch.
        </p>
    </div>
</template>
