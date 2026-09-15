<template>
    <div class="inbox-layout">
        <section class="card inbox-list">
            <h2>Очередь разбора</h2>
            <p v-if="loading" class="muted">Загрузка…</p>
            <p v-else-if="!runs.length" class="muted">Нет прогонов, ждущих разбора.</p>
            <ul v-else class="character-tree">
                <li v-for="row in runs" :key="row.id">
                    <button
                        type="button"
                        class="link inbox-run"
                        :class="{ current: selectedRunId === row.id }"
                        @click="emit('select', row.id)"
                    >
                        <strong>{{ runLabel(row) }}</strong>
                        <span class="muted">
                            · {{ runMeta(row) }}
                            <template v-if="row.status === 'failed'"> · ошибка</template>
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <section v-if="selectedRun" class="card inbox-detail">
            <div class="character-row">
                <div>
                    <h2>{{ runLabel(selectedRun) }}</h2>
                    <p class="muted">
                        {{ runMeta(selectedRun) }}
                        <template v-if="selectedRun.source_type === 'scene'">
                            · {{ selectedRun.trigger || 'manual' }}
                        </template>
                        · {{ selectedRun.status }}
                    </p>
                    <p v-if="selectedRun.status === 'failed' && selectedRun.source_type === 'scene'" class="error">
                        Разбор не удался. Переразберите окно — авторазбор это окно больше не ставит.
                    </p>
                    <p v-else-if="selectedRun.status === 'failed'" class="error">
                        Разбор не удался. Запустите «Разобрать» снова из источника.
                    </p>
                </div>
                <button
                    v-if="selectedRun.source_type === 'scene'"
                    type="button"
                    class="secondary"
                    :disabled="reparsing || managingCandidate"
                    @click="emit('reparse')"
                >
                    {{ reparsing ? 'Переразбор…' : 'Переразобрать' }}
                </button>
            </div>

            <section v-if="hasPendingCandidates || discardedRelations.length" class="extraction-panel">
                <h3>{{ panelTitle(selectedRun) }}</h3>
                <ul class="character-tree">
                    <li v-for="row in pendingMemories" :key="'memory-'+row.index">
                        <div class="character-row">
                            <span>{{ extractionMemoryLabel(row) }}</span>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate"
                                @click="emit('accept', 'memory', row.index)"
                            >
                                Принять
                            </button>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate"
                                @click="emit('discard', 'memory', row.index)"
                            >
                                Отбросить
                            </button>
                        </div>
                        <p v-if="getCandidateError('memory', row.index)" class="error">
                            {{ getCandidateError('memory', row.index) }}
                        </p>
                    </li>
                    <li v-for="row in pendingEvents" :key="'event-'+row.index">
                        <div class="character-row">
                            <span>{{ sceneEventCandidateLabel(row) }}</span>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate"
                                @click="emit('accept', 'event', row.index)"
                            >
                                Принять
                            </button>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate"
                                @click="emit('discard', 'event', row.index)"
                            >
                                Отбросить
                            </button>
                        </div>
                        <p v-if="getCandidateError('event', row.index)" class="error">
                            {{ getCandidateError('event', row.index) }}
                        </p>
                    </li>
                    <li v-for="row in pendingMentions" :key="'mention-'+row.index" class="extraction-mention-edit">
                        <div class="character-create-grid extraction-mention-fields">
                            <label>
                                Имя
                                <input v-model="mentionDraftFor(row).name" type="text" maxlength="255" />
                            </label>
                            <label>
                                Тип
                                <select v-model="mentionDraftFor(row).kind">
                                    <option
                                        v-for="kind in extractionDirectoryKinds"
                                        :key="kind.value"
                                        :value="kind.value"
                                    >
                                        {{ kind.label }}
                                    </option>
                                </select>
                            </label>
                            <label v-if="hasSectFactionField(mentionDraftFor(row).kind)">
                                Секта / фракция
                                <select v-model="mentionDraftFor(row).sect_faction_id">
                                    <option value="">— не указана —</option>
                                    <option
                                        v-for="faction in factions"
                                        :key="'sect-'+faction.id"
                                        :value="String(faction.id)"
                                    >
                                        {{ faction.canonical_name }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                Это имя уже существующего…
                                <select v-model="mentionDraftFor(row).alias_of_entity_id">
                                    <option value="">— новая сущность —</option>
                                    <option
                                        v-for="entity in directoryEntityOptions"
                                        :key="'alias-'+entity.id"
                                        :value="entity.id"
                                    >
                                        {{ entity.canonical_name }}
                                    </option>
                                </select>
                            </label>
                            <label class="world-desc">
                                Ещё имена
                                <div class="alias-row">
                                    <input
                                        v-model="mentionDraftFor(row).aliasDraft"
                                        type="text"
                                        maxlength="120"
                                        placeholder="Gangrel"
                                        @keydown.enter.prevent="emit('add-mention-alias', row)"
                                    />
                                    <button type="button" class="secondary" @click="emit('add-mention-alias', row)">
                                        Добавить
                                    </button>
                                </div>
                                <ul v-if="mentionDraftFor(row).aliases.length" class="character-tree">
                                    <li
                                        v-for="(alias, aliasIndex) in mentionDraftFor(row).aliases"
                                        :key="'mention-aka-'+row.index+'-'+alias+aliasIndex"
                                    >
                                        <div class="character-row">
                                            <span>{{ alias }}</span>
                                            <button
                                                class="link"
                                                type="button"
                                                @click="mentionDraftFor(row).aliases.splice(aliasIndex, 1)"
                                            >
                                                Убрать
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </label>
                        </div>
                        <div class="character-row">
                            <span class="muted">{{ extractionCandidateLabel(row, 'mention') }}</span>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate || patchingMentionIndex === row.index"
                                @click="emit('patch-mention', row.index)"
                            >
                                {{ patchingMentionIndex === row.index ? 'Сохранение…' : 'Сохранить правку' }}
                            </button>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate || patchingMentionIndex !== null"
                                @click="emit('accept', 'mention', row.index)"
                            >
                                Принять
                            </button>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate || patchingMentionIndex !== null"
                                @click="emit('discard', 'mention', row.index)"
                            >
                                Отбросить
                            </button>
                        </div>
                        <p v-if="getCandidateError('mention', row.index)" class="error">
                            {{ getCandidateError('mention', row.index) }}
                        </p>
                    </li>
                    <li v-for="row in pendingRelations" :key="'relation-'+row.index">
                        <div class="character-row">
                            <span>
                                {{ extractionCandidateLabel(row, 'relation') }}
                                <span v-if="!row.endpoints_resolved" class="muted"> · ждёт сущности</span>
                            </span>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate || !row.endpoints_resolved || Boolean(row.discard_reason)"
                                @click="emit('accept', 'relation', row.index)"
                            >
                                Принять
                            </button>
                            <button
                                class="link"
                                type="button"
                                :disabled="managingCandidate"
                                @click="emit('discard', 'relation', row.index)"
                            >
                                Отбросить
                            </button>
                        </div>
                        <p v-if="row.discard_reason" class="error">{{ row.discard_reason }}</p>
                        <p v-else-if="getCandidateError('relation', row.index)" class="error">
                            {{ getCandidateError('relation', row.index) }}
                        </p>
                    </li>
                    <li v-for="row in discardedRelations" :key="'discarded-relation-'+row.index">
                        <div class="character-row">
                            <span class="muted">{{ extractionCandidateLabel(row, 'relation') }}</span>
                            <span class="muted"> · отброшено</span>
                        </div>
                        <p v-if="row.discard_reason" class="error">{{ row.discard_reason }}</p>
                    </li>
                </ul>
            </section>
            <p v-else-if="selectedRun.status === 'reviewed'" class="muted">Разбор завершён.</p>
        </section>
    </div>
</template>

<script setup>
import { hasSectFactionField } from '../../composables/useWorldEntities';
import { inboxRunLabel, inboxRunMeta } from '../../composables/useWorldLore';

const props = defineProps({
    runs: { type: Array, required: true },
    selectedRunId: { type: Number, default: null },
    selectedRun: { type: Object, default: null },
    loading: { type: Boolean, required: true },
    reparsing: { type: Boolean, required: true },
    managingCandidate: { type: Boolean, required: true },
    patchingMentionIndex: { type: Number, default: null },
    pendingEvents: { type: Array, required: true },
    pendingMentions: { type: Array, required: true },
    pendingRelations: { type: Array, required: true },
    discardedRelations: { type: Array, required: true },
    pendingMemories: { type: Array, required: true },
    hasPendingCandidates: { type: Boolean, required: true },
    extractionCandidateLabel: { type: Function, required: true },
    extractionDirectoryKinds: { type: Array, required: true },
    extractionMemoryLabel: { type: Function, required: true },
    sceneEventCandidateLabel: { type: Function, required: true },
    mentionDraftFor: { type: Function, required: true },
    directoryEntityOptions: { type: Array, required: true },
    factions: { type: Array, required: true },
    getCandidateError: { type: Function, required: true },
});

const emit = defineEmits(['select', 'reparse', 'accept', 'discard', 'patch-mention', 'add-mention-alias']);

function runLabel(run) {
    return inboxRunLabel(run);
}

function runMeta(run) {
    return inboxRunMeta(run);
}

function panelTitle(run) {
    if (run.source_type === 'biography') {
        return 'Кандидаты памяти';
    }
    return 'Кандидаты графа';
}
</script>
