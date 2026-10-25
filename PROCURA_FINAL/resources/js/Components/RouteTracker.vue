<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// "Jalur pengadaan": permintaan bergerak melewati stasiun demi stasiun. Titik oranye = posisi sekarang.
const props = defineProps({ station: { type: String, required: true }, status: { type: String, default: '' }, compact: Boolean })
const page = usePage()
const stations = computed(() => Object.entries(page.props.workflow?.stations ?? {}).map(([key, label]) => ({ key, label })))
const idx = computed(() => Math.max(0, stations.value.findIndex((s) => s.key === props.station)))
const stopped = computed(() => ['rejected', 'cancelled'].includes(props.status))
const done = computed(() => ['completed'].includes(props.status))

function state(i) {
    if (stopped.value) return i < idx.value ? 'past' : i === idx.value ? 'stopped' : 'future'
    if (done.value) return 'past'
    return i < idx.value ? 'past' : i === idx.value ? 'now' : 'future'
}
</script>

<template>
    <ol class="flex w-full items-start" :aria-label="`Posisi di jalur pengadaan: ${stations[idx]?.label}`">
        <li v-for="(s, i) in stations" :key="s.key" class="relative flex min-w-0 flex-1 flex-col items-center">
            <!-- ruas jalur ke stasiun sebelumnya -->
            <span v-if="i > 0" class="absolute right-1/2 top-[9px] h-0.5 w-full"
                :class="state(i) === 'future' || state(i) === 'stopped' ? 'border-t-2 border-dashed border-slate-300 dark:border-ink-600' : 'bg-harbor-500'" aria-hidden="true"></span>
            <span class="relative z-10 grid h-5 w-5 place-items-center rounded-full border-2 bg-white dark:bg-ink-900"
                :class="{
                    'border-harbor-500 bg-harbor-500': state(i) === 'past',
                    'pulse-dot border-orange-600': state(i) === 'now',
                    'border-rose-500': state(i) === 'stopped',
                    'border-slate-300 dark:border-ink-600': state(i) === 'future',
                }">
                <svg v-if="state(i) === 'past'" class="h-3 w-3 text-white" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                <span v-else-if="state(i) === 'now'" class="h-2 w-2 rounded-full bg-orange-600"></span>
                <span v-else-if="state(i) === 'stopped'" class="text-[10px] font-bold leading-none text-rose-500">×</span>
            </span>
            <span v-if="!compact" class="mt-1.5 hidden px-0.5 text-center text-[11px] leading-tight sm:block"
                :class="state(i) === 'now' ? 'font-semibold text-orange-700 dark:text-orange-400' : state(i) === 'stopped' ? 'font-semibold text-rose-600' : state(i) === 'past' ? 'text-slate-600 dark:text-slate-300' : 'text-slate-400'">{{ s.label }}</span>
        </li>
    </ol>
</template>
