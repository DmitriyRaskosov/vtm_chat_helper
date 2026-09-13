<template>
    <aside class="card storyteller-panel">
        <h2>Панель рассказчика</h2>
        <p class="muted panel-hint">Черновики реплики НПС: промпт, три варианта, правка и отправка в чат.</p>

        <label for="npc-character">НПС сцены</label>
        <select id="npc-character" v-model.number="selectedNpcId">
            <option :value="null" disabled>Выберите участника</option>
            <option v-for="npc in sceneNpcs" :key="npc.character_id" :value="npc.character_id">
                {{ npc.character_name }}
            </option>
        </select>
        <p v-if="!sceneNpcs.length" class="muted">
            Copilot видит только НПС, которые сейчас на сцене. Добавьте персонажа блоком «На сцене» выше.
        </p>

        <label for="copilot-prompt">Ситуация / промпт</label>
        <textarea
            id="copilot-prompt"
            v-model="copilotPrompt"
            maxlength="2000"
            placeholder="Что происходит, тон, на что ответить…"
        />

        <button
            type="button"
            :disabled="copilotLoading || !canGenerate || !canPost"
            @click="generateDrafts"
        >
            {{ copilotLoading ? 'Генерация…' : 'Сгенерировать черновики' }}
        </button>

        <p v-if="copilotError" class="error">{{ copilotError }}</p>

        <div v-if="drafts.length" class="draft-list">
            <button
                v-for="(draft, index) in drafts"
                :key="index"
                type="button"
                class="draft-card"
                :class="{ selected: selectedDraftIndex === index }"
                @click="selectDraft(index)"
            >
                <span class="draft-label">Вариант {{ index + 1 }}</span>
                <span class="draft-text">{{ draft }}</span>
            </button>
        </div>

        <template v-if="selectedDraft !== null">
            <label for="draft-edit">Редактирование</label>
            <textarea id="draft-edit" v-model="editedDraft" maxlength="4000" />
            <button type="button" :disabled="!canSendNpc" @click="sendAsNpc">Отправить в чат от НПС</button>
        </template>
    </aside>
</template>

<script setup>
import { computed, ref } from 'vue';
import { api } from '../../auth';

const props = defineProps({
    sceneNpcs: { type: Array, required: true },
    selectedSceneId: { default: null },
    canPost: { type: Boolean, required: true },
});

const selectedNpcId = defineModel('selectedNpcId');

const emit = defineEmits(['sent']);

const copilotPrompt = ref('');
const copilotRequestId = ref(null);
const drafts = ref([]);
const selectedDraftIndex = ref(null);
const editedDraft = ref('');
const copilotLoading = ref(false);
const copilotError = ref('');

const canGenerate = computed(() => selectedNpcId.value !== null && copilotPrompt.value.trim() !== '');
const selectedDraft = computed(() =>
    selectedDraftIndex.value === null ? null : drafts.value[selectedDraftIndex.value] ?? null,
);
const canSendNpc = computed(() => selectedNpcId.value !== null && editedDraft.value.trim() !== '');

function selectDraft(index) {
    selectedDraftIndex.value = index;
    editedDraft.value = drafts.value[index] ?? '';
}

function resetDrafts() {
    copilotRequestId.value = null;
    drafts.value = [];
    selectedDraftIndex.value = null;
    editedDraft.value = '';
}

async function generateDrafts() {
    copilotError.value = '';
    copilotLoading.value = true;
    resetDrafts();

    try {
        const { data } = await api.post('/copilot/drafts', {
            character_id: selectedNpcId.value,
            prompt: copilotPrompt.value.trim(),
            scene_id: props.selectedSceneId,
        });
        copilotRequestId.value = data.copilot_request_id ?? null;
        drafts.value = data.drafts ?? [];
        if (drafts.value.length > 0) {
            selectDraft(0);
        }
    } catch (error) {
        copilotError.value =
            error.response?.data?.message ?? 'Не удалось сгенерировать черновики. Проверьте Ollama.';
    } finally {
        copilotLoading.value = false;
    }
}

async function sendAsNpc() {
    const text = editedDraft.value.trim();
    if (!text || selectedNpcId.value === null) {
        return;
    }

    copilotError.value = '';

    try {
        const { data } = await api.post('/messages', {
            body: text,
            character_id: selectedNpcId.value,
            scene_id: props.selectedSceneId,
            copilot_request_id: copilotRequestId.value,
            copilot_draft_index: selectedDraftIndex.value,
        });
        emit('sent', data.message);
        resetDrafts();
    } catch (error) {
        copilotError.value =
            error.response?.data?.message ?? 'Не удалось отправить выбранный черновик.';
    }
}

defineExpose({ resetDrafts });
</script>
