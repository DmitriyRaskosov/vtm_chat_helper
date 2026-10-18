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
            maxlength="6000"
            rows="8"
            placeholder="Что происходит, тон, на что ответить…"
        />

        <div class="preview-row">
            <button
                type="button"
                class="secondary"
                :disabled="selectedNpcId === null"
                @click="openPreview"
            >
                Показать память НПС
            </button>
        </div>

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
                :key="`${copilotRequestId}-${index}`"
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
            <textarea id="draft-edit" v-model="editedDraft" maxlength="6000" rows="10" />
            <button type="button" :disabled="!canSendNpc" @click="sendAsNpc">Отправить в чат от НПС</button>
        </template>

        <div v-if="previewOpen" class="diary-backdrop" @click.self="closePreview">
            <div class="diary-modal card">
                <header class="diary-modal-header">
                    <h2>Что уйдёт в промпт НПС</h2>
                    <button type="button" class="link" @click="closePreview">Закрыть</button>
                </header>

                <p v-if="previewLoading" class="muted">Считаем…</p>
                <p v-else-if="previewError" class="error">{{ previewError }}</p>
                <template v-else-if="previewData">
                    <p class="muted">
                        Сцена: {{ previewData.scene_id ?? 'не указана' }}
                        <span v-if="previewData.recent_first_message_id">
                            · recent начинается с #{{ previewData.recent_first_message_id }}
                        </span>
                    </p>

                    <ul class="preview-stats">
                        <li>Всего записей: <strong>{{ previewData.stats.total_entries }}</strong></li>
                        <li>Stale (исключено): <strong>{{ previewData.stats.stale }}</strong></li>
                        <li>Покрыто recent-окном: <strong>{{ previewData.stats.filtered_by_recent }}</strong></li>
                        <li
                            v-if="previewData.stats.kept_by_current_scene"
                            class="muted"
                        >
                            Из них оставлено как голос текущей сцены:
                            <strong>{{ previewData.stats.kept_by_current_scene }}</strong>
                        </li>
                        <li>В пуле: <strong>{{ previewData.stats.in_pool }}</strong></li>
                        <li>Отобрано: <strong>{{ previewData.stats.selected }}</strong></li>
                        <li>
                            Токенов: <strong>{{ previewData.stats ? previewData.tokens_estimate : 0 }}</strong> / {{ previewData.budget }}
                            <span v-if="previewData.truncated" class="stale-badge">ОБРЕЗАНО</span>
                        </li>
                    </ul>

                    <h3>Отрендеренный блок</h3>
                    <pre class="preview-content">{{ previewData.rendered || '(пусто)' }}</pre>

                    <details v-if="previewData.selected.length" class="preview-details">
                        <summary>Отобранные записи ({{ previewData.selected.length }})</summary>
                        <ul>
                            <li v-for="e in previewData.selected" :key="e.id" class="preview-entry">
                                <div class="preview-entry-header">
                                    <strong>{{ e.label }}</strong>
                                    <span class="muted">
                                        #{{ e.id }} · L{{ e.level }}
                                        <template v-if="e.from_message_id">· msg {{ e.from_message_id }}–{{ e.to_message_id }}</template>
                                    </span>
                                </div>
                                <p>{{ e.excerpt }}<span v-if="e.excerpt.length >= 200">…</span></p>
                            </li>
                        </ul>
                    </details>
                </template>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { toRef } from 'vue';
import { useCopilotPanel } from '../../composables/useCopilotPanel';

const props = defineProps({
    sceneNpcs: { type: Array, required: true },
    selectedSceneId: { default: null },
    chronicleId: { type: [String, Number], required: true },
    canPost: { type: Boolean, required: true },
});

const selectedNpcId = defineModel('selectedNpcId');

const emit = defineEmits(['sent']);

const {
    copilotPrompt,
    copilotRequestId,
    drafts,
    selectedDraftIndex,
    editedDraft,
    copilotLoading,
    copilotError,
    previewOpen,
    previewLoading,
    previewError,
    previewData,
    canGenerate,
    selectedDraft,
    canSendNpc,
    selectDraft,
    resetDrafts,
    generateDrafts,
    sendAsNpc,
    openPreview,
    closePreview,
} = useCopilotPanel({
    selectedSceneId: toRef(props, 'selectedSceneId'),
    selectedNpcId,
    chronicleId: toRef(props, 'chronicleId'),
    onSent: (message) => emit('sent', message),
});

defineExpose({ resetDrafts });
</script>
