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
            v-model:new-game-session-title="newGameSessionTitle"
            @switch="switchScene"
            @create-scene="onCreateScene"
            @activate="activateScene"
            @close="closeScene"
            @add="addToScene"
            @remove="removeFromScene"
            @create-session="onCreateGameSession"
        />

        <div :class="{ stage: isStoryteller }">
            <div>
                <ChatLog ref="chatLog" :messages="messages" />
                <ChatComposer
                    v-model:body="body"
                    :can-post="canPost"
                    :placeholder="composerPlaceholder"
                    @send="send"
                />
            </div>

            <StorytellerCopilotPanel
                v-if="isStoryteller"
                ref="copilotPanel"
                v-model:selected-npc-id="selectedNpcId"
                :scene-npcs="sceneNpcs"
                :selected-scene-id="selectedSceneId"
                :can-post="canPost"
                @sent="onNpcSent"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useAuth } from '../auth';
import AppNav from '../components/layout/AppNav.vue';
import ChatComposer from '../components/chat/ChatComposer.vue';
import ChatLog from '../components/chat/ChatLog.vue';
import SceneToolbar from '../components/chat/SceneToolbar.vue';
import StorytellerCopilotPanel from '../components/chat/StorytellerCopilotPanel.vue';
import { useChatMessages } from '../composables/useChatMessages';
import { useSceneSession } from '../composables/useSceneSession';

const auth = useAuth();
const chatLog = ref(null);
const copilotPanel = ref(null);
const isStoryteller = computed(() => auth.user.value?.is_storyteller === true);

const {
    gameSession,
    newGameSessionTitle,
    selectedSceneId,
    newSceneTitle,
    sceneLoading,
    sceneError,
    addCharacterId,
    participantBusy,
    participantError,
    participantFlash,
    selectedNpcId,
    scenes,
    selectedScene,
    canPost,
    canEditParticipants,
    currentParticipants,
    availableToAdd,
    sceneNpcs,
    bindMessages,
    loadGameSession,
    loadParticipants,
    addToScene,
    removeFromScene,
    createGameSession,
    createScene,
    activateScene,
    closeScene,
} = useSceneSession({ auth });

const {
    messages,
    body,
    lastId,
    merge,
    scrollDown,
    load,
    send,
} = useChatMessages({
    selectedSceneId,
    canPost,
    getLog: () => chatLog.value,
});

bindMessages(messages);

const composerPlaceholder = computed(() => {
    if (!gameSession.value) {
        return 'Нет активной игровой сессии';
    }

    return canPost.value ? 'Сообщение…' : 'Сцена доступна только для чтения';
});

let timer;
let polling = false;

function resetCopilotDrafts() {
    copilotPanel.value?.resetDrafts();
}

async function switchScene() {
    messages.value = [];
    resetCopilotDrafts();
    await loadParticipants();
    await load();
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
        const preferredSceneId = auth.user.value?.is_storyteller
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
        }
    } catch {
        // A later poll retries transient API failures.
    } finally {
        polling = false;
    }
}

async function onNpcSent(message) {
    merge([message]);
    await scrollDown();
}

onMounted(async () => {
    await loadGameSession();
    await loadParticipants();
    await load();
    timer = setInterval(poll, 3000);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>
