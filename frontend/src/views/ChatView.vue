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
            v-model:environment-details="environmentDetails"
            :scene-situation-saving="sceneSituationSaving"
            :scene-situation-error="sceneSituationError"
            :chronicle-id="chronicleId"
            :scene-context-revision="sceneSituationRevision"
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
            @location-applied="onLocationApplied"
            @pass-turn="passTurn"
        />

        <div class="stage">
            <div class="chat-column">
                <div ref="splitContainer" class="chat-split-container">
                    <section class="chat-ic-pane">
                        <h2 class="chat-pane-title">Сюжет</h2>
                        <ChatLog
                            ref="icChatLog"
                            mode="ic"
                            v-model:auto-scroll="isIcAutoScroll"
                            :messages="icMessages"
                            :is-storyteller="isStoryteller"
                            :current-user-id="auth.user?.id ?? null"
                            :storyteller-user-ids="storytellerUserIds"
                            :is-typing="isTyping && !isOoc"
                            :typing-label="myCharacterName ?? auth.user?.name ?? ''"
                            @toggle-ooc="toggleOoc"
                            @delete="deleteMessage"
                            @restore="restoreMessage"
                            @edit="onEditMessage"
                        />
                    </section>
                    <section
                        class="chat-ooc-pane"
                        :style="oocPaneStyle"
                        @mousedown="onOocPaneFocus"
                    >
                        <div
                            class="chat-split-resizer"
                            role="separator"
                            aria-orientation="horizontal"
                            aria-label="Изменить высоту панели обсуждения"
                            @mousedown.prevent.stop="onSplitResizeStart"
                        />
                        <h2 class="chat-pane-title">Обсуждение</h2>
                        <ChatLog
                            ref="oocChatLog"
                            mode="ooc"
                            v-model:auto-scroll="isOocAutoScroll"
                            :messages="oocMessages"
                            :is-storyteller="isStoryteller"
                            :current-user-id="auth.user?.id ?? null"
                            :storyteller-user-ids="storytellerUserIds"
                            :is-typing="isTyping && isOoc"
                            :typing-label="myCharacterName ?? auth.user?.name ?? ''"
                            @toggle-ooc="toggleOoc"
                            @delete="deleteMessage"
                            @restore="restoreMessage"
                            @edit="onEditMessage"
                        />
                    </section>
                </div>

                <div class="chat-composer-stack">
                    <div
                        v-if="isDiceOpen || isNotesOpen"
                        class="chat-panel-popovers"
                    >
                        <DiceRoller
                            v-if="isDiceOpen"
                            :is-storyteller="isStoryteller"
                            :my-character-id="myCharacterId"
                            :character-options="diceCharacterOptions"
                            :rolling="rolling"
                            :roll-error="rollError"
                            :last-result="lastResult"
                            :can-roll="canPost"
                            @close="isDiceOpen = false"
                            @roll="onDiceRoll"
                            @clear-result="clearLastResult"
                        />
                        <ChatNotesPanel
                            v-if="isNotesOpen"
                            v-model:content="notesContent"
                            :loading="notesLoading"
                            :saving="notesSaving"
                            :error="notesError"
                            @close="isNotesOpen = false"
                            @save="saveNotes"
                        />
                    </div>
                    <ChatToolsDock>
                        <template #bar>
                            <div class="chat-tools-dock-actions">
                                <label class="toggle">
                                    <input v-model="showDeleted" type="checkbox" />
                                    Показать удалённые
                                </label>
                            </div>
                            <ChatComposer
                                v-model:body="body"
                                v-model:is-ooc="isOoc"
                                :can-post="canPost"
                                :placeholder="composerPlaceholder"
                                :my-character-name="myCharacterName"
                                :is-storyteller="isStoryteller"
                                :my-character-id="myCharacterId"
                                :is-my-turn="isMyTurn"
                                :turn-releasing="participantBusy"
                                @send="send"
                                @end-turn="releaseMyTurn"
                            />
                        </template>
                    </ChatToolsDock>
                </div>
            </div>

            <aside class="side-panel">
                <BattleLog
                    :logs="logs"
                    :log-error="logError"
                    :selected-scene-id="selectedSceneId"
                    :chronicle-id="chronicleId"
                    :is-storyteller="isStoryteller"
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
            v-if="isCharacterOpen"
            :is-storyteller="isStoryteller"
            :participants="drawerParticipants"
            :selected-character-id="drawerCharacterId"
            :my-character-id="myCharacterId"
            @close="isCharacterOpen = false"
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
import DiceRoller from '../components/chat/DiceRoller.vue';
import { playPlayerTurnSound } from '../utils/chatMessageSound';
import SceneToolbar from '../components/chat/SceneToolbar.vue';
import StorytellerCopilotPanel from '../components/chat/StorytellerCopilotPanel.vue';
import { useChatMessages } from '../composables/useChatMessages';
import { useChatPanels } from '../composables/useChatPanels';
import { useDiceRoll, useSceneBattleLog } from '../composables/useSceneBattleLog';
import { useSceneSession } from '../composables/useSceneSession';
import { useUserNotes } from '../composables/useUserNotes';
import ChatToolsDock from '../components/chat/ChatToolsDock.vue';

const auth = useAuth();
const route = useRoute();
const chronicleId = computed(() => route.params.chronicleId);
const OOC_PANE_HEIGHT_KEY = 'chat-ooc-pane-height';
const DEFAULT_OOC_PANE_HEIGHT = 180;
const MIN_OOC_PANE_HEIGHT = 72;
const MIN_IC_PANE_PEEK = 2.5;

const icChatLog = ref(null);
const oocChatLog = ref(null);
const splitContainer = ref(null);

function readOocPaneHeight() {
    const raw = localStorage.getItem(OOC_PANE_HEIGHT_KEY);
    const parsed = raw ? Number.parseInt(raw, 10) : Number.NaN;

    if (Number.isFinite(parsed) && parsed >= MIN_OOC_PANE_HEIGHT) {
        return parsed;
    }

    return DEFAULT_OOC_PANE_HEIGHT;
}

const oocPaneHeight = ref(readOocPaneHeight());

const oocPaneStyle = computed(() => ({
    height: `${oocPaneHeight.value}px`,
}));
const copilotPanel = ref(null);
const drawerCharacterId = ref(null);
const {
    isDiceOpen,
    isNotesOpen,
    isCharacterOpen,
    closeAll,
} = useChatPanels();

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
    environmentDetails,
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
    sceneSituationRevision,
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
    icMessages,
    oocMessages,
    body,
    isOoc,
    showDeleted,
    isTyping,
    storytellerUserIds,
    isIcAutoScroll,
    isOocAutoScroll,
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
    getIcLog: () => icChatLog.value,
    getOocLog: () => oocChatLog.value,
    myCharacterId,
    chronicleId,
    auth,
    isStoryteller,
});

bindMessages(messages);

const {
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

const drawerParticipants = computed(() => {
    if (isStoryteller.value) {
        return currentParticipants.value;
    }

    return diceCharacterOptions.value;
});

function initDrawerCharacter() {
    if (isStoryteller.value) {
        const first = currentParticipants.value[0];
        drawerCharacterId.value = first?.character_id ?? null;
    } else {
        drawerCharacterId.value = myCharacterId.value;
    }
}

watch(isNotesOpen, (open, wasOpen) => {
    if (open && !wasOpen) {
        openNotes();
    }
    if (!open && wasOpen) {
        closeNotes();
    }
});

watch(isCharacterOpen, (open) => {
    if (open) {
        initDrawerCharacter();
    }
});

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

function onLocationApplied(context) {
    sceneSituationRevision.value = Number(context?.revision ?? sceneSituationRevision.value);
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

function clampOocPaneHeight(height, containerHeight) {
    const maxHeight = Math.max(
        MIN_OOC_PANE_HEIGHT,
        containerHeight - MIN_IC_PANE_PEEK * 16,
    );

    return Math.min(maxHeight, Math.max(MIN_OOC_PANE_HEIGHT, height));
}

function onSplitResizeStart(event) {
    const container = splitContainer.value;
    if (!container) {
        return;
    }

    const containerHeight = container.getBoundingClientRect().height;
    const startY = event.clientY;
    const startHeight = oocPaneHeight.value;

    function onMove(moveEvent) {
        const delta = startY - moveEvent.clientY;
        oocPaneHeight.value = clampOocPaneHeight(startHeight + delta, containerHeight);
    }

    function onUp() {
        localStorage.setItem(OOC_PANE_HEIGHT_KEY, String(oocPaneHeight.value));
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.classList.remove('chat-split-resizing');
    }

    document.body.classList.add('chat-split-resizing');
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

function onOocPaneFocus() {
    if (canPost.value) {
        isOoc.value = true;
    }
}

async function onNpcSent(message) {
    merge([message]);
    await scrollDown({ ic: true, ooc: false });
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

    if (splitContainer.value) {
        const containerHeight = splitContainer.value.getBoundingClientRect().height;
        oocPaneHeight.value = clampOocPaneHeight(oocPaneHeight.value, containerHeight);
    }
});

onUnmounted(() => {
    clearInterval(timer);
    closeAll();
});
</script>
