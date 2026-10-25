<script setup>
defineProps({ title: String, subtitle: String })
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ title }}</h2>
            <p v-if="subtitle" class="muted mt-1 max-w-2xl text-sm">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
