<template>
    <aside class="right-panel">
        <div v-if="loading" class="sidebar-skeleton" aria-busy="true" aria-label="Загрузка панели">
            <div class="skeleton-block"></div>
            <div class="skeleton-block"></div>
            <div class="skeleton-block skeleton-block--tall"></div>
        </div>
        <template v-else>
        <div class="participants">
            <div class="participants-heading">
                <button
                    type="button"
                    class="participants-toggle"
                    :aria-expanded="sceneCastOpen"
                    @click="toggleSceneCast"
                >
                    <span aria-hidden="true">{{ sceneCastOpen ? '▲' : '▼' }}</span>
                    На сцене
                </button>
            </div>
            <button
                v-if="isStoryteller"
                type="button"
                class="participants-manage"
                @click="emit('open-scene-manage')"
            >
                Управление сценой
            </button>
            <template v-if="sceneCastOpen">
                <p v-if="!participants.length" class="muted">На сцене никого.</p>
                <button
                    v-for="row in participants"
                    :key="row.character_id"
                    type="button"
                    class="participant-item"
                    @click="emit('select-participant', row.character_id)"
                >
                    <span class="p-name">{{ row.character_name }}</span>
                    <span class="p-desc">{{ row.character_description || characterTypeLabel(row.character_type) }}</span>
                </button>
            </template>
        </div>
        <div class="tabs">
            <button
                v-if="viewerIsStoryteller"
                type="button"
                class="tab"
                :class="{ active: tab === 'copilot' }"
                @click="tab = 'copilot'"
            >
                Copilot
            </button>
            <button
                type="button"
                class="tab"
                :class="{ active: tab === 'battle' }"
                @click="tab = 'battle'"
            >
                Лог боя
            </button>
            <button
                v-if="viewerIsStoryteller"
                type="button"
                class="tab"
                :class="{ active: tab === 'events' }"
                @click="tab = 'events'"
            >
                События
            </button>
        </div>
        <div v-if="viewerIsStoryteller" v-show="tab === 'copilot'" class="tab-content">
            <StorytellerCopilotPanel
                v-if="viewerIsStoryteller"
                ref="copilotPanel"
                v-model:selected-npc-id="selectedNpcId"
                :scene-npcs="sceneNpcs"
                :selected-scene-id="selectedSceneId"
                :chronicle-id="chronicleId"
                :can-post="canPost"
                @sent="emit('npc-sent', $event)"
            />
        </div>
        <div v-show="tab === 'battle'" class="tab-content">
            <BattleLog
                :logs="logs"
                :log-error="logError"
                :selected-scene-id="selectedSceneId"
                :is-storyteller="isStoryteller"
                :active="tab === 'battle'"
                :active-encounter-id="activeEncounterId"
                @seed-change="eventSeed = $event"
            />
        </div>
        <div v-if="viewerIsStoryteller" v-show="tab === 'events'" class="tab-content">
            <EventGeneratorModal
                v-if="viewerIsStoryteller"
                embedded
                :open="true"
                :generating="eventGenerating"
                :error="eventGenerateError"
                :generated-text="eventGeneratedText"
                :seed="eventSeed"
                @generate="onGenerateEvent"
            />
        </div>
        </template>
    </aside>
</template>

<script setup>
import { computed, ref } from 'vue';
import BattleLog from './BattleLog.vue';
import EventGeneratorModal from './EventGeneratorModal.vue';
import StorytellerCopilotPanel from './StorytellerCopilotPanel.vue';
import { isStoryteller as viewerIsStoryteller } from '../../auth';
import { useEventGenerator } from '../../composables/useEventGenerator';
import { characterTypeLabel } from '../../utils/labels';

const props = defineProps({
    participants: { type: Array, required: true },
    isStoryteller: { type: Boolean, required: true },
    sceneNpcs: { type: Array, required: true },
    selectedSceneId: { default: null },
    chronicleId: { type: [String, Number], default: null },
    canPost: { type: Boolean, required: true },
    logs: { type: Array, required: true },
    logError: { type: String, default: '' },
    activeEncounterId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
});

const selectedNpcId = defineModel('selectedNpcId');

const emit = defineEmits(['select-participant', 'npc-sent', 'open-scene-manage']);

const SCENE_CAST_OPEN_KEY = 'chat-scene-cast-open';

const sceneCastOpen = ref(localStorage.getItem(SCENE_CAST_OPEN_KEY) === '1');

function toggleSceneCast() {
    sceneCastOpen.value = !sceneCastOpen.value;
    localStorage.setItem(SCENE_CAST_OPEN_KEY, sceneCastOpen.value ? '1' : '0');
}

const tab = ref(viewerIsStoryteller.value ? 'copilot' : 'battle');
const copilotPanel = ref(null);
const eventSeed = ref(null);
const eventGeneratedText = ref('');

const {
    generating: eventGenerating,
    generateError: eventGenerateError,
    generate,
} = useEventGenerator({
    chronicleId: computed(() => props.chronicleId),
    selectedSceneId: computed(() => props.selectedSceneId),
});

async function onGenerateEvent(payload) {
    eventGeneratedText.value = '';
    const text = await generate(payload);
    if (text) {
        eventGeneratedText.value = text;
    }
}

function resetDrafts() {
    copilotPanel.value?.resetDrafts();
}

defineExpose({ resetDrafts });
</script>
