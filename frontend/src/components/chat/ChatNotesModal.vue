<template>
    <div v-if="open" class="modal-backdrop" @click.self="emit('close')">
        <div class="modal card chat-notes-modal" role="dialog" aria-labelledby="chat-notes-title">
            <header class="modal-header">
                <h2 id="chat-notes-title">Личные заметки</h2>
                <button type="button" class="secondary" @click="emit('close')">Закрыть</button>
            </header>
            <p class="muted">Видны только вам. Привязаны к текущей сцене.</p>
            <textarea
                v-model="content"
                rows="12"
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
    </div>
</template>

<script setup>
defineProps({
    open: { type: Boolean, required: true },
    loading: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
    error: { type: String, required: true },
});

const content = defineModel('content', { type: String, required: true });

const emit = defineEmits(['close', 'save']);
</script>
