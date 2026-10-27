<template>
    <div class="turn-badge">
        <span>ВАШ ХОД<span v-if="characterName"> ({{ characterName }})</span></span>
        <button
            type="button"
            class="turn-badge-done"
            :disabled="busy"
            @click="emit('end-turn')"
        >
            {{ busy ? '…' : 'Готово' }}
        </button>
        <button type="button" class="turn-badge-close" title="Скрыть" @click="emit('close')">×</button>
    </div>
</template>

<script setup>
defineProps({
    characterName: { type: String, default: '' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'end-turn']);
</script>
