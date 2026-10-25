<script setup>
defineProps({ title: String, text: String })
</script>

<template>
    <div class="px-6 py-12 text-center">
        <p class="font-medium text-slate-700 dark:text-slate-200">{{ title }}</p>
        <p v-if="text" class="muted mx-auto mt-1 max-w-sm text-sm">{{ text }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
