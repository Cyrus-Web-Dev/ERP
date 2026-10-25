<script setup>
defineProps({ label: String, error: String, hint: String, for: String })
</script>

<template>
    <div>
        <label v-if="label" :for="$props.for" class="label">{{ label }}</label>
        <slot />
        <p v-if="hint && !error" class="muted mt-1 text-xs">{{ hint }}</p>
        <p v-if="error" class="err">{{ error }}</p>
    </div>
</template>
