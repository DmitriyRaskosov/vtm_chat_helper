<template>
    <aside class="right-panel">
        <div class="participants">
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
                :chronicle-id="chronicleId"
                :is-storyteller="isStoryteller"
            />
        </div>
    </aside>
</template>

<script setup>
import { ref } from 'vue';
import BattleLog from './BattleLog.vue';
import StorytellerCopilotPanel from './StorytellerCopilotPanel.vue';
import { characterTypeLabel } from '../../utils/labels';

defineProps({
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

const emit = defineEmits(['select-participant', 'npc-sent']);

const tab = ref('copilot');
const copilotPanel = ref(null);

function resetDrafts() {
    copilotPanel.value?.resetDrafts();
}

defineExpose({ resetDrafts });
</script>
