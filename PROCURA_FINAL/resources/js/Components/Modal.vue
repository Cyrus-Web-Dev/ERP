<script setup>
import { onMounted, onUnmounted, ref, watch, nextTick } from 'vue'

const props = defineProps({ show: Boolean, title: String, maxWidth: { type: String, default: 'max-w-lg' } })
const emit = defineEmits(['close'])
const panel = ref(null)

function onKey(e) { if (e.key === 'Escape' && props.show) emit('close') }
onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
watch(() => props.show, async (v) => {
    if (v) { await nextTick(); panel.value?.querySelector('input,select,textarea')?.focus() }
})
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8" role="dialog" aria-modal="true" :aria-label="title">
            <div class="fixed inset-0 bg-ink-950/60" @click="emit('close')"></div>
            <div ref="panel" class="card relative my-auto w-full" :class="maxWidth">
                <div class="card-head">
                    <h3 class="card-title">{{ title }}</h3>
                    <button type="button" class="text-xl leading-none text-slate-400 hover:text-slate-700" title="Tutup" @click="emit('close')">&times;</button>
                </div>
                <div class="p-5"><slot /></div>
            </div>
        </div>
    </Teleport>
</template>
