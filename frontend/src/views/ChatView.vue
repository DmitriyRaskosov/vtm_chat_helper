<template>
    <div class="gothic-layout">
        <AppNav current="chat" />

        <main class="main-stage">
            <header class="top-bar">
                <div class="scene-info">
                    <div>
                        <div class="scene-title">{{ selectedScene?.title ?? 'Сцена' }}</div>
                        <div v-if="gameSession" class="loc-name">{{ gameSession.title }}</div>
                    </div>
                    <select
                        v-if="scenes.length"
                        class="scene-switch"
                        v-model.number="selectedSceneId"
                        @change="switchScene"
                    >
                        <option v-for="scene in scenes" :key="scene.id" :value="scene.id">
                            {{ scene.position }}. {{ scene.title }} · {{ sceneStatusLabel(scene.status) }}
                        </option>
                    </select>
                </div>
                <TurnBadge
                    v-if="isMyTurn && !turnBadgeDismissed"
                    :character-name="myCharacterName ?? ''"
                    :busy="participantBusy"
                    @close="turnBadgeDismissed = true"
                    @end-turn="releaseMyTurn"
                />
                <button
                    v-else-if="isMyTurn && turnBadgeDismissed"
                    type="button"
                    class="turn-badge"
                    @click="turnBadgeDismissed = false"
                >
                    ВАШ ХОД
                </button>
            </header>

            <p v-if="isFallbackMode" class="connection-fallback-notice" role="status">
                Соединение нестабильно. Включен резервный режим
            </p>

            <LocationBanner
                v-if="gameSession && selectedScene"
                :image-url="locationImageUrl"
                :situation="sceneSituation"
                :is-storyteller="isStoryteller"
                :chronicle-id="chronicleId"
                @zoom="locationZoomOpen = true"
                @open-settings="sceneSettingsOpen = true"
                @open-manage="sceneManageOpen = true"
            />

            <div v-if="gameSession" ref="splitContainer" class="chat-container chat-split-container">
                <section class="chat-ic-pane">
                    <h2 class="chat-pane-title">Сюжет</h2>
                    <ChatLog
                        ref="icChatLog"
                        mode="ic"
                        v-model:auto-scroll="isIcAutoScroll"
                        :messages="icMessages"
                        :is-storyteller="isStoryteller"
                        :current-user-id="auth.user.value?.id ?? null"
                        :storyteller-user-ids="storytellerUserIds"
                        :is-typing="Boolean(remoteTypingIcLabel)"
                        :typing-label="remoteTypingIcLabel"
                        @toggle-ooc="toggleOoc"
                        @delete="deleteMessage"
                        @restore="restoreMessage"
                        @edit="onEditMessage"
                    />
                </section>
                <div
                    class="chat-split-resizer"
                    role="separator"
                    aria-orientation="vertical"
                    aria-label="Изменить ширину панели обсуждения"
                    @mousedown.prevent.stop="onSplitResizeStart"
                />
                <section
                    class="chat-ooc-pane"
                    :style="oocPaneStyle"
                    @mousedown="onOocPaneFocus"
                >
                    <h2 class="chat-pane-title">Обсуждение</h2>
                    <ChatLog
                        ref="oocChatLog"
                        mode="ooc"
                        v-model:auto-scroll="isOocAutoScroll"
                        :messages="oocMessages"
                        :is-storyteller="isStoryteller"
                        :current-user-id="auth.user.value?.id ?? null"
                        :storyteller-user-ids="storytellerUserIds"
                        :is-typing="Boolean(remoteTypingOocLabel)"
                        :typing-label="remoteTypingOocLabel"
                        @toggle-ooc="toggleOoc"
                        @delete="deleteMessage"
                        @restore="restoreMessage"
                        @edit="onEditMessage"
                    />
                </section>
            </div>

            <footer v-if="gameSession" class="composer-anchor">
                <ChatComposer
                    v-model:body="body"
                    v-model:is-ooc="isOoc"
                    v-model:is-narration="isNarration"
                    v-model:show-deleted="showDeleted"
                    v-model:speaker-character-id="speakerCharacterId"
                    :can-post="canCompose"
                    :placeholder="composerPlaceholder"
                    :my-character-name="myCharacterName"
                    :is-storyteller="isStoryteller"
                    :my-character-id="myCharacterId"
                    :inventory-character-id="inventoryCharacterId"
                    :participants="currentParticipants"
                    :active-player-characters="playerCharactersOnScene"
                    :combat-active="combatActive"
                    :can-start-combat="selectedSceneId != null"
                    v-model:selected-pc-id="selectedPcId"
                    @send="send"
                    @start-combat="onStartCombat"
                />
            </footer>

            <CombatTracker
                v-if="selectedSceneId != null"
                ref="combatTrackerRef"
                v-model:open="isCombatOpen"
                :scene-id="selectedSceneId"
                :chronicle-id="chronicleId"
                :scene-participants="currentParticipants"
                @active="combatActive = $event"
                @encounter-id="combatEncounterId = $event"
                @turn="applyRemoteActiveCharacter"
            />
            <FloatingWindow
                v-if="isDiceOpen"
                title="Кубы"
                panel-class="floating-window--dice"
                :initial-left="96"
                :initial-top="120"
                @close="isDiceOpen = false"
            >
                <DiceRoller
                    :is-storyteller="isStoryteller"
                    :my-character-id="myCharacterId"
                    :roll-character-id="diceRollCharacterId"
                    :character-options="diceCharacterOptions"
                    :rolling="rolling"
                    :roll-error="rollError"
                    :last-result="lastResult"
                    :can-roll="canPost"
                    @close="isDiceOpen = false"
                    @roll="onDiceRoll"
                    @clear-result="clearLastResult"
                />
            </FloatingWindow>
            <FloatingWindow
                v-if="isNotesOpen"
                title="Заметки"
                :initial-left="440"
                :initial-top="140"
                @close="isNotesOpen = false"
            >
                <ChatNotesPanel
                    v-model:content="notesContent"
                    :loading="notesLoading"
                    :saving="notesSaving"
                    :error="notesError"
                    @close="isNotesOpen = false"
                    @save="saveNotes"
                />
            </FloatingWindow>
            <FloatingWindow
                v-if="isInventoryOpen"
                title="Инвентарь"
                :initial-left="360"
                :initial-top="160"
                @close="isInventoryOpen = false"
            >
                <InventoryPanel
                    ref="inventoryPanel"
                    :character-id="inventoryCharacterId"
                    :character-name="inventoryCharacterName"
                    :items="inventoryItems"
                    :loading="inventoryLoading"
                    :saving="inventorySaving"
                    :error="inventoryError"
                    @add="onAddInventoryItem"
                    @move="moveInventoryItem"
                    @remove="removeInventoryItem"
                />
            </FloatingWindow>
            <FloatingWindow
                v-if="isCharacterOpen"
                title="Чарлист"
                :initial-left="240"
                :initial-top="100"
                @close="isCharacterOpen = false"
            >
                <ChatCharacterDrawer
                    :is-storyteller="isStoryteller"
                    :participants="drawerParticipants"
                    :selected-character-id="drawerCharacterId"
                    :my-character-id="myCharacterId"
                    :scene-id="selectedSceneId"
                    @close="isCharacterOpen = false"
                    @select-character="drawerCharacterId = $event"
                />
            </FloatingWindow>
            <FloatingWindow
                v-if="isDiaryOpen && isStoryteller"
                title="Дневник"
                :initial-left="180"
                :initial-top="80"
                @close="isDiaryOpen = false"
            >
                <DiaryPanel v-if="diaryCharacterId" :character-id="diaryCharacterId" />
                <p v-else class="muted">Выберите NPC в списке справа или в поле «Писать от лица».</p>
            </FloatingWindow>

            <Teleport to="body" :disabled="!gameSession">
            <div
                v-if="!gameSession || sceneSettingsOpen"
                class="modal-overlay"
                :class="{ 'modal-overlay-static': !gameSession }"
                @click.self="sceneSettingsOpen = false"
            >
                <div class="modal-content" :class="{ 'modal-content-static': !gameSession }">
                    <header v-if="gameSession" class="modal-header">
                        <h2>Настройки локации</h2>
                        <button type="button" class="btn-sm" @click="sceneSettingsOpen = false">Закрыть</button>
                    </header>
                    <SceneToolbar
                        :game-session="gameSession"
                        :is-storyteller="isStoryteller"
                        v-model:selected-scene-id="selectedSceneId"
                        :selected-scene="selectedScene"
                        :scene-loading="sceneLoading"
                        :scene-error="sceneError"
                        v-model:scene-situation="sceneSituation"
                        v-model:environment-details="environmentDetails"
                        :scene-situation-saving="sceneSituationSaving"
                        :scene-situation-error="sceneSituationError"
                        :chronicle-id="chronicleId"
                        :scene-context-revision="sceneSituationRevision"
                        v-model:new-game-session-title="newGameSessionTitle"
                        :location-image-url="locationImageUrl"
                        :location-image-loading="locationImageLoading"
                        :location-image-error="locationImageError"
                        @create-session="onCreateGameSession"
                        @save-situation="saveSceneSituation"
                        @location-applied="onLocationApplied"
                        @upload-location-image="onUploadLocationImage"
                        @zoom-location="locationZoomOpen = true"
                    />
                </div>
            </div>
            </Teleport>

            <Teleport to="body">
                <div
                    v-if="gameSession && sceneManageOpen"
                    class="modal-overlay"
                    @click.self="sceneManageOpen = false"
                >
                    <div class="modal-content">
                        <header class="modal-header">
                            <h2>Управление сценой</h2>
                            <button type="button" class="btn-sm" @click="sceneManageOpen = false">Закрыть</button>
                        </header>
                        <SceneManagementModal
                            :is-storyteller="isStoryteller"
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
                            :active-character-id="activeCharacterId"
                            :turn-busy="participantBusy"
                            @create-scene="onCreateScene"
                            @activate="activateScene"
                            @close="closeScene"
                            @add="onAddToScene"
                            @remove="onRemoveFromScene"
                            @pass-turn="passTurn"
                        />
                    </div>
                </div>
            </Teleport>

            <Teleport to="body">
                <div
                    v-if="locationZoomOpen && locationImageUrl"
                    class="modal-overlay"
                    @click.self="locationZoomOpen = false"
                >
                    <button type="button" class="modal-close" @click="locationZoomOpen = false">×</button>
                    <img :src="locationImageUrl" alt="Фото локации" class="location-zoom-img" />
                </div>
            </Teleport>
        </main>

        <RightSidebar
            ref="rightSidebar"
            v-model:selected-npc-id="selectedNpcId"
            :participants="currentParticipants"
            :is-storyteller="isStoryteller"
            :scene-npcs="sceneNpcs"
            :selected-scene-id="selectedSceneId"
            :chronicle-id="chronicleId"
            :can-post="canPost"
            :logs="logs"
            :log-error="logError"
            :active-encounter-id="combatEncounterId"
            @select-participant="onSelectParticipant"
            @npc-sent="onNpcSent"
            @open-scene-manage="sceneManageOpen = true"
        />
    </div>
</template>


<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { isStoryteller, useAuth } from '../auth';
import AppNav from '../components/layout/AppNav.vue';
import ChatCharacterDrawer from '../components/chat/ChatCharacterDrawer.vue';
import CombatTracker from '../components/chat/CombatTracker.vue';
import ChatComposer from '../components/chat/ChatComposer.vue';
import ChatLog from '../components/chat/ChatLog.vue';
import ChatNotesPanel from '../components/chat/ChatNotesPanel.vue';
import InventoryPanel from '../components/chat/InventoryPanel.vue';
import DiaryPanel from '../components/chat/DiaryPanel.vue';
import DiceRoller from '../components/chat/DiceRoller.vue';
import FloatingWindow from '../components/chat/FloatingWindow.vue';
import LocationBanner from '../components/chat/LocationBanner.vue';
import RightSidebar from '../components/chat/RightSidebar.vue';
import TurnBadge from '../components/chat/TurnBadge.vue';
import { playPlayerTurnSound } from '../utils/chatMessageSound';
import SceneManagementModal from '../components/chat/SceneManagementModal.vue';
import SceneToolbar from '../components/chat/SceneToolbar.vue';
import { useCharacterInventory } from '../composables/useCharacterInventory';
import { useChatMessages } from '../composables/useChatMessages';
import { useChatPanels } from '../composables/useChatPanels';
import { useDiceRoll, useSceneBattleLog } from '../composables/useSceneBattleLog';
import { sceneStatusLabel, useSceneSession } from '../composables/useSceneSession';
import { useSceneContextImage } from '../composables/useSceneContextImage';
import { useConnectionStatus } from '../composables/useConnectionStatus';
import { useUserNotes } from '../composables/useUserNotes';

const auth = useAuth();
const route = useRoute();
const chronicleId = computed(() => route.params.chronicleId);
const OOC_PANE_WIDTH_KEY = 'chat-ooc-pane-width';
const DEFAULT_OOC_PANE_WIDTH = 400;
const MIN_OOC_PANE_WIDTH = 220;
const MIN_IC_PANE_WIDTH = 280;

const icChatLog = ref(null);
const oocChatLog = ref(null);
const splitContainer = ref(null);
const rightSidebar = ref(null);
const speakerCharacterId = ref(null);
const sceneSettingsOpen = ref(false);
const sceneManageOpen = ref(false);
const locationZoomOpen = ref(false);
const turnBadgeDismissed = ref(false);

function readOocPaneWidth() {
    const raw = localStorage.getItem(OOC_PANE_WIDTH_KEY);
    const parsed = raw ? Number.parseInt(raw, 10) : Number.NaN;

    if (Number.isFinite(parsed) && parsed >= MIN_OOC_PANE_WIDTH) {
        return parsed;
    }

    return DEFAULT_OOC_PANE_WIDTH;
}

const oocPaneWidth = ref(readOocPaneWidth());

const oocPaneStyle = computed(() => ({
    width: `${oocPaneWidth.value}px`,
    flex: `0 0 ${oocPaneWidth.value}px`,
}));
const drawerCharacterId = ref(null);
const {
    isDiceOpen,
    isNotesOpen,
    isInventoryOpen,
    isCharacterOpen,
    isDiaryOpen,
    isCombatOpen,
    closeAll,
} = useChatPanels();

const combatTrackerRef = ref(null);
const combatActive = ref(false);
const combatEncounterId = ref(null);

async function onStartCombat() {
    isCombatOpen.value = true;
    await combatTrackerRef.value?.startCombat();
}

const {
    gameSession,
    newGameSessionTitle,
    selectedSceneId,
    myCharacterId,
    myPlayerCharactersOnScene,
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
    applyRemoteActiveCharacter,
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
    isNarration,
    showDeleted,
    remoteTypingIcLabel,
    remoteTypingOocLabel,
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
    activePlayerCharacters,
    selectedPcId,
} = useChatMessages({
    selectedSceneId,
    canPost,
    getIcLog: () => icChatLog.value,
    getOocLog: () => oocChatLog.value,
    myCharacterId,
    speakerCharacterId,
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
    if (!isStoryteller.value && playerCharactersOnScene.value.length === 0) {
        return 'Выйдите на сцену в личном кабинете, чтобы писать в сюжет';
    }
    return isOoc.value
        ? 'Вопрос мастеру, мета-обсуждение...'
        : 'Ваши слова или действия...';
});

const playerCharactersOnScene = computed(() => {
    const onSceneIds = new Set(
        currentParticipants.value
            .filter((participant) => participant.is_current && participant.character_type === 'player')
            .map((participant) => Number(participant.character_id)),
    );

    return activePlayerCharacters.value.filter((row) => onSceneIds.has(Number(row.id)));
});

const canCompose = computed(() => {
    if (!canPost.value) {
        return false;
    }

    if (isStoryteller.value) {
        return true;
    }

    return playerCharactersOnScene.value.length > 0;
});

const myCharacterName = computed(() => {
    if (isStoryteller.value) {
        return null;
    }

    const selectedId = selectedPcId.value ?? myCharacterId.value;
    if (selectedId == null) {
        return null;
    }

    const fromProfile = activePlayerCharacters.value.find(
        (row) => Number(row.id) === Number(selectedId),
    );
    if (fromProfile?.canonical_name) {
        return fromProfile.canonical_name;
    }

    const onScene = currentParticipants.value.find(
        (participant) => Number(participant.character_id) === Number(selectedId),
    );

    return onScene?.character_name ?? null;
});

const inventoryCharacterId = computed(() => {
    if (isStoryteller.value) {
        return speakerCharacterId.value;
    }

    return selectedPcId.value ?? myCharacterId.value;
});

const inventoryCharacterName = computed(() => {
    if (isStoryteller.value) {
        const row = currentParticipants.value.find(
            (participant) => Number(participant.character_id) === Number(inventoryCharacterId.value),
        );

        return row?.character_name ?? '';
    }

    return myCharacterName.value ?? '';
});

const {
    items: inventoryItems,
    loading: inventoryLoading,
    saving: inventorySaving,
    error: inventoryError,
    addItem: addInventoryItem,
    moveItem: moveInventoryItem,
    removeItem: removeInventoryItem,
    open: openInventory,
    close: closeInventory,
} = useCharacterInventory(inventoryCharacterId);

const inventoryPanel = ref(null);

async function onAddInventoryItem(payload) {
    const saved = await addInventoryItem(payload);
    if (saved) {
        inventoryPanel.value?.resetForm();
    }
}

const diceRollCharacterId = computed(() => {
    if (isStoryteller.value) {
        return speakerCharacterId.value ?? null;
    }

    return selectedPcId.value ?? myCharacterId.value;
});

const diceCharacterOptions = computed(() => {
    if (isStoryteller.value) {
        return currentParticipants.value;
    }

    return myPlayerCharactersOnScene.value;
});

const drawerParticipants = computed(() => {
    if (isStoryteller.value) {
        return currentParticipants.value;
    }

    return myPlayerCharactersOnScene.value;
});

function initDrawerCharacter() {
    if (isStoryteller.value) {
        const first = currentParticipants.value[0];
        drawerCharacterId.value = first?.character_id ?? null;
    } else {
        const first = myPlayerCharactersOnScene.value[0];
        drawerCharacterId.value = first?.character_id ?? myCharacterId.value;
    }
}

watch(playerCharactersOnScene, (rows) => {
    if (isStoryteller.value) {
        return;
    }

    const ids = rows.map((row) => Number(row.id));
    if (selectedPcId.value != null && ids.includes(Number(selectedPcId.value))) {
        return;
    }

    selectedPcId.value = rows[0] ? Number(rows[0].id) : null;
}, { immediate: true });

watch(isNotesOpen, (open, wasOpen) => {
    if (open && !wasOpen) {
        openNotes();
    }
    if (!open && wasOpen) {
        closeNotes();
    }
});

watch(isInventoryOpen, (open, wasOpen) => {
    if (open && !wasOpen) {
        openInventory();
    }
    if (!open && wasOpen) {
        closeInventory();
    }
});

watch(isCharacterOpen, (open) => {
    if (open && drawerCharacterId.value == null) {
        initDrawerCharacter();
    }
});

let turnSoundReady = false;

const {
    imageUrl: locationImageUrl,
    loading: locationImageLoading,
    error: locationImageError,
    upload: uploadLocationImage,
    load: reloadSceneImage,
} = useSceneContextImage(selectedSceneId);

watch([isStoryteller, currentParticipants], () => {
    if (!isStoryteller.value) {
        speakerCharacterId.value = null;
        return;
    }

    const rows = currentParticipants.value.filter((row) => row.character_type === 'npc');
    const stillThere = rows.some(
        (row) => Number(row.character_id) === Number(speakerCharacterId.value),
    );

    if (!stillThere) {
        speakerCharacterId.value = rows[0]?.character_id ?? null;
    }
});

watch(activeCharacterId, (characterId, previousId) => {
    turnBadgeDismissed.value = false;

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

let pollTimer = null;
let polling = false;

const {
    isFallbackMode,
    onConnectionLost,
    onConnectionRestored,
    startMonitoring,
} = useConnectionStatus();

function startFallbackPolling() {
    if (pollTimer !== null) {
        return;
    }

    pollTimer = setInterval(poll, 3000);
    poll().catch(() => {});
}

function stopFallbackPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function syncAfterConnectionRestore() {
    await load(lastId());
    await pollLogs();
    await pollSceneSituation();
    await combatTrackerRef.value?.reload();
}

function resetCopilotDrafts() {
    rightSidebar.value?.resetDrafts();
}

function onSelectParticipant(characterId) {
    drawerCharacterId.value = characterId;
    isCharacterOpen.value = true;
}

async function onUploadLocationImage(file) {
    if (selectedSceneId.value == null) {
        return;
    }

    await uploadLocationImage(selectedSceneId.value, file);
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

        await combatTrackerRef.value?.reload();
    } catch {
        // Следующий цикл повторит запрос, если API на миг недоступен.
    } finally {
        polling = false;
    }
}

async function onLocationApplied(context) {
    sceneSituationRevision.value = Number(context?.revision ?? sceneSituationRevision.value);

    if (selectedSceneId.value != null) {
        await reloadSceneImage(selectedSceneId.value);
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

const diaryCharacterId = computed(() => {
    const selected = currentParticipants.value.find(
        (row) => Number(row.character_id) === Number(drawerCharacterId.value),
    );

    if (selected?.character_type === 'npc') {
        return selected.character_id;
    }

    return speakerCharacterId.value;
});

function clampOocPaneWidth(width, containerWidth) {
    const maxWidth = Math.max(
        MIN_OOC_PANE_WIDTH,
        containerWidth - MIN_IC_PANE_WIDTH,
    );

    return Math.min(maxWidth, Math.max(MIN_OOC_PANE_WIDTH, width));
}

function onSplitResizeStart(event) {
    const container = splitContainer.value;
    if (!container) {
        return;
    }

    const containerWidth = container.getBoundingClientRect().width;
    const startX = event.clientX;
    const startWidth = oocPaneWidth.value;

    function onMove(moveEvent) {
        const delta = startX - moveEvent.clientX;
        oocPaneWidth.value = clampOocPaneWidth(startWidth + delta, containerWidth);
    }

    function onUp() {
        localStorage.setItem(OOC_PANE_WIDTH_KEY, String(oocPaneWidth.value));
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

watch(sceneSituationRevision, async () => {
    if (selectedSceneId.value != null) {
        await reloadSceneImage(selectedSceneId.value);
    }
});

onMounted(async () => {
    await bootstrapChat();
    turnSoundReady = true;

    onConnectionLost(() => {
        startFallbackPolling();
    });
    onConnectionRestored(() => {
        stopFallbackPolling();
        syncAfterConnectionRestore().catch(() => {});
    });
    startMonitoring();

    if (splitContainer.value) {
        const containerWidth = splitContainer.value.getBoundingClientRect().width;
        oocPaneWidth.value = clampOocPaneWidth(oocPaneWidth.value, containerWidth);
    }
});

onUnmounted(() => {
    stopFallbackPolling();
    closeAll();
});
</script>
