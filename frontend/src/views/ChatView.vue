<template>
    <div>
        <AppNav current="chat" />

        <SceneToolbar
            :game-session="gameSession"
            :is-storyteller="isStoryteller"
            v-model:selected-scene-id="selectedSceneId"
            :scenes="scenes"
            :selected-scene="selectedScene"
            v-model:new-scene-title="newSceneTitle"
            :scene-loading="sceneLoading"
            :scene-error="sceneError"
            :can-edit-participants="canEditParticipants"
            :current-participants="currentParticipants"
            :available-to-add="availableToAdd"
            v-model:add-character-id="addCharacterId"
            :participant-busy="participantBusy"
            :participant-error="participantError"
            :participant-flash="participantFlash"
            v-model:participant-system-message="participantSystemMessage"
            v-model:scene-situation="sceneSituation"
            :scene-situation-saving="sceneSituationSaving"
            :scene-situation-error="sceneSituationError"
            v-model:new-game-session-title="newGameSessionTitle"
            :active-character-id="activeCharacterId"
            :turn-busy="participantBusy"
            @switch="switchScene"
            @create-scene="onCreateScene"
            @activate="activateScene"
            @close="closeScene"
            @add="onAddToScene"
            @remove="onRemoveFromScene"
            @create-session="onCreateGameSession"
            @save-situation="saveSceneSituation"
            @pass-turn="passTurn"
        />

        <div class="stage">
            <div class="chat-column">
                <ChatLog
                    ref="chatLog"
                    :messages="messages"
                    :is-storyteller="isStoryteller"
                    :current-user-id="auth.user?.id ?? null"
                    :storyteller-user-ids="storytellerUserIds"
                    :is-typing="isTyping"
                    :typing-label="myCharacterName ?? auth.user?.name ?? ''"
                    @toggle-ooc="toggleOoc"
                    @delete="deleteMessage"
                    @restore="restoreMessage"
                    @edit="onEditMessage"
                />

                <ChatToolsDock
                    :drawer-open="characterDrawerOpen"
                    :show-panels="diceOpen || notesOpen"
                >
                    <template #panels>
                        <DiceRoller
                            v-model:open="diceOpen"
                            :is-storyteller="isStoryteller"
                            :my-character-id="myCharacterId"
                            :character-options="diceCharacterOptions"
                            :rolling="rolling"
                            :roll-error="rollError"
                            :last-result="lastResult"
                            :can-roll="canPost"
                            @roll="onDiceRoll"
                            @clear-result="clearLastResult"
                        />
                        <ChatNotesPanel
                            v-model:content="notesContent"
                            :open="notesOpen"
                            :loading="notesLoading"
                            :saving="notesSaving"
                            :error="notesError"
                            @close="closeNotes"
                            @save="saveNotes"
                        />
                    </template>
                    <template #bar>
                        <div class="chat-tools-dock-actions">
                            <label class="toggle">
                                <input v-model="showDeleted" type="checkbox" />
                                Показать удалённые
                            </label>
                            <button
                                type="button"
                                class="secondary"
                                title="Личные заметки"
                                @click="toggleNotes"
                            >
                                📝 Заметки
                            </button>
                        </div>
                        <ChatComposer
                            v-model:body="body"
                            v-model:is-ooc="isOoc"
                            :can-post="canPost"
                            :placeholder="composerPlaceholder"
                            :my-character-name="myCharacterName"
                            :character-sheet-enabled="characterSheetEnabled"
                            :character-sheet-title="characterSheetTitle"
                            :is-my-turn="isMyTurn"
                            :turn-releasing="participantBusy"
                            @send="send"
                            @end-turn="releaseMyTurn"
                            @open-dice="toggleDiceRoller"
                            @open-character-sheet="openCharacterDrawer"
                        />
                    </template>
                </ChatToolsDock>
            </div>

            <aside class="side-panel">
                <BattleLog
                    :logs="logs"
                    :log-error="logError"
                    :selected-scene-id="selectedSceneId"
                />

                <StorytellerCopilotPanel
                    v-if="isStoryteller"
                    ref="copilotPanel"
                    v-model:selected-npc-id="selectedNpcId"
                    :scene-npcs="sceneNpcs"
                    :selected-scene-id="selectedSceneId"
                    :chronicle-id="chronicleId"
                    :can-post="canPost"
                    @sent="onNpcSent"
                />
            </aside>
        </div>

        <ChatCharacterDrawer
            :open="characterDrawerOpen"
            :is-storyteller="isStoryteller"
            :participants="drawerParticipants"
            :selected-character-id="drawerCharacterId"
            :my-character-id="myCharacterId"
            @close="characterDrawerOpen = false"
            @select-character="drawerCharacterId = $event"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { isStoryteller, useAuth } from '../auth';
import AppNav from '../components/layout/AppNav.vue';
import BattleLog from '../components/chat/BattleLog.vue';
import ChatCharacterDrawer from '../components/chat/ChatCharacterDrawer.vue';
import ChatComposer from '../components/chat/ChatComposer.vue';
import ChatLog from '../components/chat/ChatLog.vue';
import ChatNotesPanel from '../components/chat/ChatNotesPanel.vue';
import { playPlayerTurnSound } from '../utils/chatMessageSound';
import DiceRoller from '../components/chat/DiceRoller.vue';
import SceneToolbar from '../components/chat/SceneToolbar.vue';
import StorytellerCopilotPanel from '../components/chat/StorytellerCopilotPanel.vue';
import { useChatMessages } from '../composables/useChatMessages';
import { useDiceRoll, useSceneBattleLog } from '../composables/useSceneBattleLog';
import { useSceneSession } from '../composables/useSceneSession';
import { useUserNotes } from '../composables/useUserNotes';
import ChatToolsDock from '../components/chat/ChatToolsDock.vue';

const auth = useAuth();
const route = useRoute();
const chronicleId = computed(() => route.params.chronicleId);
const chatLog = ref(null);
const copilotPanel = ref(null);
const diceOpen = ref(false);
const characterDrawerOpen = ref(false);
const drawerCharacterId = ref(null);

const {
    gameSession,
    newGameSessionTitle,
    selectedSceneId,
    myCharacterId,
    newSceneTitle,
    sceneLoading,
    sceneError,
    addCharacterId,
    participantBusy,
    participantError,
    participantFlash,
    participantSystemMessage,
    sceneSituation,
    sceneSituationSaving,
    sceneSituationError,
    selectedNpcId,
    scenes,
    selectedScene,
    canPost,
    canEditParticipants,
    currentParticipants,
    availableToAdd,
    sceneNpcs,
    activeCharacterId,
    isMyTurn,
    bindMessages,
    loadGameSession,
    saveSceneSituation,
    pollSceneSituation,
    passTurn,
    releaseMyTurn,
    loadParticipants,
    addToScene,
    removeFromScene,
    createGameSession,
    createScene,
    activateScene,
    closeScene,
} = useSceneSession({ auth, chronicleId });

const {
    messages,
    body,
    isOoc,
    showDeleted,
    isTyping,
    storytellerUserIds,
    lastId,
    merge,
    scrollDown,
    load,
    send,
    toggleOoc,
    deleteMessage,
    restoreMessage,
    editMessage,
} = useChatMessages({
    selectedSceneId,
    canPost,
    getLog: () => chatLog.value,
    myCharacterId,
    chronicleId,
    auth,
    isStoryteller,
});

bindMessages(messages);

const {
    open: notesOpen,
    content: notesContent,
    loading: notesLoading,
    saving: notesSaving,
    error: notesError,
    openModal: openNotes,
    closeModal: closeNotes,
    save: saveNotes,
} = useUserNotes(selectedSceneId);

const {
    logs,
    logError,
    reloadLogs,
    pollLogs,
    resetLogs,
    mergeLogs,
} = useSceneBattleLog({
    chronicleId,
    selectedSceneId,
    isStoryteller,
});

const {
    rolling,
    rollError,
    lastResult,
    rollDice,
    clearLastResult,
} = useDiceRoll({ chronicleId, selectedSceneId });

const composerPlaceholder = computed(() => {
    if (!gameSession.value) {
        return 'Нет активной игровой сессии';
    }
    if (!canPost.value) {
        return 'Сцена доступна только для чтения';
    }
    return isOoc.value
        ? 'Вопрос мастеру, уточнение, мета-обсуждение…'
        : 'Реплика в сцене…';
});

const myCharacterName = computed(() => {
    if (!myCharacterId.value) return null;
    const pc = currentParticipants.value.find((p) => p.character_id === myCharacterId.value);
    return pc?.character_name ?? null;
});

const diceCharacterOptions = computed(() => {
    if (isStoryteller.value) {
        return currentParticipants.value;
    }

    if (!myCharacterId.value) {
        return [];
    }

    const pc = currentParticipants.value.find((p) => p.character_id === myCharacterId.value);
    return pc ? [pc] : [];
});

const characterSheetEnabled = computed(() => (
    isStoryteller.value ? true : myCharacterId.value !== null
));

const characterSheetTitle = computed(() => (
    isStoryteller.value ? 'Чарлист' : 'Мой персонаж'
));

const drawerParticipants = computed(() => {
    if (isStoryteller.value) {
        return currentParticipants.value;
    }

    return diceCharacterOptions.value;
});

function toggleDiceRoller() {
    diceOpen.value = !diceOpen.value;
}

function toggleNotes() {
    if (notesOpen.value) {
        closeNotes();
        return;
    }
    openNotes();
}

function openCharacterDrawer() {
    if (!characterSheetEnabled.value) {
        return;
    }

    if (characterDrawerOpen.value) {
        characterDrawerOpen.value = false;

        return;
    }

    if (isStoryteller.value) {
        const first = currentParticipants.value[0];
        drawerCharacterId.value = first?.character_id ?? null;
    } else {
        drawerCharacterId.value = myCharacterId.value;
    }

    characterDrawerOpen.value = true;
}

let turnSoundReady = false;

watch(activeCharacterId, (characterId, previousId) => {
    if (!turnSoundReady) {
        return;
    }

    if (isStoryteller.value || myCharacterId.value == null) {
        return;
    }

    if (
        characterId != null
        && Number(characterId) === Number(myCharacterId.value)
        && Number(previousId) !== Number(characterId)
    ) {
        playPlayerTurnSound();
    }
});

let timer;
let polling = false;

function resetCopilotDrafts() {
    copilotPanel.value?.resetDrafts();
}

async function switchScene() {
    messages.value = [];
    resetCopilotDrafts();
    resetLogs();
    await loadParticipants();
    await load();
    await reloadLogs();
}

async function onCreateGameSession() {
    if (!await createGameSession()) {
        return;
    }
    messages.value = [];
    await loadParticipants();
    await load();
}

async function onCreateScene() {
    if (!await createScene()) {
        return;
    }
    messages.value = [];
    resetCopilotDrafts();
    await loadParticipants();
    await load();
}

async function poll() {
    if (polling) {
        return;
    }

    polling = true;

    try {
        const previousSceneId = selectedSceneId.value;
        const preferredSceneId = isStoryteller.value
            ? previousSceneId
            : null;

        await loadGameSession(preferredSceneId);

        if (selectedSceneId.value !== previousSceneId) {
            messages.value = [];
            resetCopilotDrafts();
            await loadParticipants();
            await load();
        } else {
            await load(lastId());
            await pollLogs();
            await pollSceneSituation();
        }
    } catch {
        // A later poll retries transient API failures.
    } finally {
        polling = false;
    }
}

async function onAddToScene() {
    await addToScene();
    await load(lastId());
}

async function onRemoveFromScene(characterId) {
    await removeFromScene(characterId);
    await load(lastId());
}

async function onEditMessage({ message, body }) {
    await editMessage(message, body);
}

async function onNpcSent(message) {
    merge([message]);
    await scrollDown();
}

async function onDiceRoll(payload) {
    const data = await rollDice(payload);
    if (data?.log) {
        mergeLogs([data.log]);
    }
}

async function bootstrapChat() {
    messages.value = [];
    resetCopilotDrafts();
    resetLogs();
    await loadGameSession();
    await loadParticipants();
    await load();
    await reloadLogs();
}

watch(chronicleId, async () => {
    await bootstrapChat();
});

onMounted(async () => {
    await bootstrapChat();
    turnSoundReady = true;
    timer = setInterval(poll, 3000);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>
