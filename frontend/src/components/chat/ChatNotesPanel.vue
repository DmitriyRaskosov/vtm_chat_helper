<template>
    <div class="chat-panel-popover chat-floating-panel card chat-notes-panel" role="dialog">
        <header class="chat-panel-header">
            <h2>Личные заметки</h2>
            <button type="button" class="chat-panel-close" aria-label="Закрыть" @click="emit('close')">×</button>
        </header>
        <p v-if="characterName" class="muted">
            Заметки для {{ characterName }}. Видны только вам.
        </p>
        <p v-else class="muted">Видны только вам. Выберите персонажа, чтобы вести заметки.</p>
        <textarea
            v-model="content"
            rows="10"
            maxlength="20000"
            placeholder="Планы, подсказки, что заметили…"
            :disabled="loading"
        />
        <p v-if="error" class="error">{{ error }}</p>
        <p v-else-if="saving" class="muted">Сохранение…</p>
        <div class="sheet-actions">
            <button type="button" :disabled="loading || saving" @click="emit('save')">
                Сохранить
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    loading: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
    error: { type: String, required: true },
    characterName: { type: String, default: '' },
});

const content = defineModel('content', { type: String, required: true });

const emit = defineEmits(['close', 'save']);
</script>
