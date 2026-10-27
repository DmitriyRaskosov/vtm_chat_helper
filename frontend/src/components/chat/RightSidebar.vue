<template>
    <aside class="right-panel">
        <div class="participants">
            <div class="participants-heading">
                <span class="muted">На сцене</span>
                <button
                    v-if="isStoryteller"
                    type="button"
                    class="participants-manage"
                    title="Управление сценой"
                    @click="emit('open-scene-manage')"
                >
                    👥
                </button>
            </div>
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
        </div>
        <div class="tabs">
            <button
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
                type="button"
                class="tab"
                :class="{ active: tab === 'events' }"
                @click="tab = 'events'"
            >
                События
            </button>
        </div>
        <div v-show="tab === 'copilot'" class="tab-content">
            <StorytellerCopilotPanel
                v-if="isStoryteller"
                ref="copilotPanel"
                v-model:selected-npc-id="selectedNpcId"
                :scene-npcs="sceneNpcs"
                :selected-scene-id="selectedSceneId"
                :chronicle-id="chronicleId"
                :can-post="canPost"
                @sent="emit('npc-sent', $event)"
            />
            <p v-else class="muted">Copilot доступен рассказчику.</p>
        </div>
        <div v-show="tab === 'battle'" class="tab-content">
            <BattleLog
                :logs="logs"
                :log-error="logError"
                :selected-scene-id="selectedSceneId"
                :is-storyteller="isStoryteller"
                :active="tab === 'battle'"
                @seed-change="eventSeed = $event"
            />
        </div>
        <div v-show="tab === 'events'" class="tab-content">
            <EventGeneratorModal
                v-if="isStoryteller"
                embedded
                :open="true"
                :generating="eventGenerating"
                :error="eventGenerateError"
                :generated-text="eventGeneratedText"
                :seed="eventSeed"
                @generate="onGenerateEvent"
            />
            <p v-else class="muted">Генератор событий доступен рассказчику.</p>
        </div>
    </aside>
</template>

<script setup>
import { computed, ref } from 'vue';
import BattleLog from './BattleLog.vue';
import EventGeneratorModal from './EventGeneratorModal.vue';
import StorytellerCopilotPanel from './StorytellerCopilotPanel.vue';
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
});

const selectedNpcId = defineModel('selectedNpcId');

const emit = defineEmits(['select-participant', 'npc-sent', 'open-scene-manage']);

const tab = ref('copilot');
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
