<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// <StatusBadge kind="request" :value="pr.status" />  kind: request | funding | po | priority
const props = defineProps({ kind: { type: String, default: 'request' }, value: { type: String, required: true } })
const page = usePage()
const meta = computed(() => page.props.workflow?.[props.kind]?.[props.value] ?? { label: props.value, color: 'slate' })
</script>

<template>
    <span class="badge" :class="`badge-${meta.color}`">{{ meta.label }}</span>
</template>
