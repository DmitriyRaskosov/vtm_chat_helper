<template>
    <div v-if="open" class="diary-backdrop" @click.self="close">
        <div class="diary-modal card character-notes-modal">
            <header class="diary-modal-header">
                <h2>
                    Заметки
                    <span v-if="characterName" class="muted"> — {{ characterName }}</span>
                </h2>
                <button type="button" class="link" @click="close">Закрыть</button>
            </header>
            <p class="muted">Личные заметки. Видны только вам.</p>
            <textarea
                v-model="content"
                rows="12"
                maxlength="20000"
                placeholder="Планы, напоминания, детали…"
                :disabled="loading"
            />
            <p v-if="error" class="error">{{ error }}</p>
            <p v-else-if="saving" class="muted">Сохранение…</p>
            <button type="button" :disabled="loading || saving" @click="save">
                Сохранить
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useUserNotes } from '../../composables/useUserNotes';

const props = defineProps({
    chronicleId: { type: [String, Number], required: true },
    characterId: { type: [String, Number], default: null },
    characterName: { type: String, default: '' },
});

const open = defineModel('open', { type: Boolean, default: false });

const chronicleIdRef = computed(() => props.chronicleId);
const activeCharacterId = computed(() => (open.value ? props.characterId : null));

const {
    content,
    loading,
    saving,
    error,
    load,
    save,
} = useUserNotes(chronicleIdRef, activeCharacterId, { reloadOnCharacterChange: true });

function close() {
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        load();
    }
});

watch(
    () => props.characterId,
    () => {
        if (open.value) {
            load();
        }
    },
);
</script>

<style scoped>
.character-notes-modal {
    max-width: 28rem;
}

.character-notes-modal textarea {
    width: 100%;
    box-sizing: border-box;
    margin-bottom: 0.75rem;
}
</style>
