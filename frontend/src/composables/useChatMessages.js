import { computed, nextTick, onUnmounted, ref, toValue, watch } from 'vue';
import { api } from '../auth';
import { playSoundForMessage } from '../utils/chatMessageSound';
import { CHRONICLE_NOT_SELECTED, chronicleCollectionPath } from './useCurrentChronicle';
import { useSceneChannelListener } from './useSceneChannel';

const COMPOSER_MODE_KEY = 'chat-composer-mode';
const SELECTED_PC_KEY = 'chat-selected-pc-id';
const TYPING_SEND_INTERVAL_MS = 2000;
const TYPING_HIDE_MS = 3000;

function readComposerOocFromStorage() {
    return localStorage.getItem(COMPOSER_MODE_KEY) === 'ooc';
}

function writeComposerOocToStorage(isOoc) {
    localStorage.setItem(COMPOSER_MODE_KEY, isOoc ? 'ooc' : 'ic');
}

function isNpcMessage(message) {
    return message.npc_name != null && message.npc_name !== '';
}

function withMineForViewer(message, viewerUserId) {
    const userId = Number(viewerUserId);

    return {
        ...message,
        mine: !message.is_system
            && !isNpcMessage(message)
            && Number.isFinite(userId)
            && Number(message.user_id) === userId,
    };
}

export function useChatMessages({
    selectedSceneId,
    canPost,
    getIcLog,
    getOocLog,
    myCharacterId,
    speakerCharacterId,
    chronicleId,
    auth,
    isStoryteller,
}) {
    const messages = ref([]);
    const body = ref('');
    const isOoc = ref(readComposerOocFromStorage());
    const showDeleted = ref(false);
    const remoteTypingIcLabel = ref('');
    const remoteTypingOocLabel = ref('');
    const storytellerUserIds = ref([]);
    const isIcAutoScroll = ref(true);
    const isOocAutoScroll = ref(true);
    const activePlayerCharacters = ref([]);
    const selectedPcId = ref(null);

    const icMessages = computed(() => messages.value.filter((message) => !message.is_ooc));
    const oocMessages = computed(() => messages.value.filter(
        (message) => message.is_ooc && !message.is_system,
    ));

    let typingHideIcTimer = null;
    let typingHideOocTimer = null;
    let lastTypingSentAt = 0;

    function clearTypingHideTimer(kind) {
        const timer = kind === 'ooc' ? typingHideOocTimer : typingHideIcTimer;

        if (timer !== null) {
            clearTimeout(timer);
        }

        if (kind === 'ooc') {
            typingHideOocTimer = null;
        } else {
            typingHideIcTimer = null;
        }
    }

    function showRemoteTyping(userName, isOocChannel) {
        if (!userName) {
            return;
        }

        const kind = isOocChannel ? 'ooc' : 'ic';

        if (kind === 'ooc') {
            remoteTypingOocLabel.value = userName;
            clearTypingHideTimer('ooc');
            typingHideOocTimer = setTimeout(() => {
                remoteTypingOocLabel.value = '';
                typingHideOocTimer = null;
            }, TYPING_HIDE_MS);
        } else {
            remoteTypingIcLabel.value = userName;
            clearTypingHideTimer('ic');
            typingHideIcTimer = setTimeout(() => {
                remoteTypingIcLabel.value = '';
                typingHideIcTimer = null;
            }, TYPING_HIDE_MS);
        }
    }

    function maybeSendTypingSignal() {
        if (!canPost.value || !selectedSceneId.value) {
            return;
        }

        const text = body.value.trim();
        if (!text) {
            return;
        }

        const now = Date.now();
        if (now - lastTypingSentAt < TYPING_SEND_INTERVAL_MS) {
            return;
        }

        lastTypingSentAt = now;
        api.post(`/scenes/${selectedSceneId.value}/typing`, { is_ooc: isOoc.value }).catch(() => {});
    }

    watch(body, () => {
        maybeSendTypingSignal();
    });

    onUnmounted(() => {
        clearTypingHideTimer('ic');
        clearTypingHideTimer('ooc');
    });

    useSceneChannelListener(selectedSceneId, '.MessageSent', (payload) => {
        const message = payload?.message;
        if (!message?.id) {
            return;
        }

        const last = lastId();
        if (Number(message.id) <= last) {
            return;
        }

        const normalized = withMineForViewer(message, auth.user.value?.id);
        merge([normalized], { playSounds: true });
        scrollDown({
            ic: !normalized.is_ooc,
            ooc: Boolean(normalized.is_ooc),
        });
    });

    useSceneChannelListener(selectedSceneId, '.UserTyping', (payload) => {
        console.log('UserTyping', payload);

        const remoteUserId = Number(payload?.user_id);
        const viewerId = Number(auth.user.value?.id);

        if (Number.isFinite(remoteUserId) && Number.isFinite(viewerId) && remoteUserId === viewerId) {
            return;
        }

        const userName = typeof payload?.user_name === 'string' ? payload.user_name : '';
        showRemoteTyping(userName, Boolean(payload?.is_ooc));
    });

    watch(isOoc, (value) => {
        writeComposerOocToStorage(value);
    });

    function readStoredPcId() {
        const id = Number(localStorage.getItem(SELECTED_PC_KEY));

        return Number.isFinite(id) && id > 0 ? id : null;
    }

    function rememberPc(id) {
        if (id == null) {
            localStorage.removeItem(SELECTED_PC_KEY);

            return;
        }

        localStorage.setItem(SELECTED_PC_KEY, String(id));
    }

    function applyStoredPc(rows) {
        const stored = readStoredPcId();
        const match = rows.find((row) => Number(row.id) === stored);
        const nextId = match ? Number(match.id) : (rows[0] ? Number(rows[0].id) : null);
        selectedPcId.value = nextId;
        rememberPc(nextId);
    }

    async function loadActivePlayerCharacters() {
        if (isStoryteller.value) {
            activePlayerCharacters.value = [];
            selectedPcId.value = null;

            return;
        }

        const chronicle = Number(toValue(chronicleId));
        if (!Number.isFinite(chronicle) || chronicle <= 0) {
            activePlayerCharacters.value = [];
            selectedPcId.value = null;

            return;
        }

        const { data } = await api.get('/profile/characters');
        const rows = (Array.isArray(data?.data) ? data.data : []).filter((row) => (
            row.is_active === true && Number(row.chronicle_id) === chronicle
        ));
        activePlayerCharacters.value = rows;
        applyStoredPc(rows);
    }

    watch(
        () => [isStoryteller.value, Number(toValue(chronicleId))],
        () => {
            loadActivePlayerCharacters().catch(() => {
                activePlayerCharacters.value = [];
            });
        },
        { immediate: true },
    );

    watch(selectedPcId, (id) => {
        if (isStoryteller.value) {
            return;
        }

        rememberPc(id);
    });

    watch(showDeleted, async () => {
        messages.value = [];
        await load();
    });

    function lastId() {
        return messages.value.at(-1)?.id ?? 0;
    }

    function merge(incoming, { playSounds = false } = {}) {
        const tailId = lastId();
        const added = [];

        for (const message of incoming) {
            const messageId = Number(message.id);
            if (!Number.isFinite(messageId)) {
                continue;
            }

            if (messages.value.some((item) => item.id === messageId)) {
                continue;
            }

            if (messageId <= tailId) {
                continue;
            }

            messages.value.push(message);
            added.push(message);
        }

        if (!playSounds || !added.length) {
            return;
        }

        const context = {
            myCharacterId: myCharacterId?.value ?? null,
            myUserId: auth.user.value?.id ?? null,
            isStoryteller: isStoryteller.value,
        };

        for (const message of added) {
            playSoundForMessage(message, context);
        }
    }

    function updateMessage(updated) {
        const idx = messages.value.findIndex((m) => m.id === updated.id);
        if (idx !== -1) {
            messages.value[idx] = { ...messages.value[idx], ...updated };
        }
    }

    async function scrollDown({ ic = true, ooc = true } = {}) {
        await nextTick();
        if (ic && isIcAutoScroll.value) {
            getIcLog()?.scrollToBottom?.();
        }
        if (ooc && isOocAutoScroll.value) {
            getOocLog()?.scrollToBottom?.();
        }
    }

    async function load(afterId = 0) {
        if (!selectedSceneId.value) {
            return;
        }

        const sceneIdAtStart = selectedSceneId.value;
        const params = {
            scene_id: sceneIdAtStart,
            after_id: afterId,
        };
        if (showDeleted.value) {
            params.include_deleted = true;
        }

        let path;
        try {
            path = chronicleCollectionPath(chronicleId, 'messages');
        } catch {
            return;
        }

        const { data } = await api.get(path, { params });

        if (selectedSceneId.value !== sceneIdAtStart) {
            return;
        }

        const stickIc = afterId === 0 || isIcAutoScroll.value;
        const stickOoc = afterId === 0 || isOocAutoScroll.value;
        if (Array.isArray(data.storyteller_user_ids)) {
            storytellerUserIds.value = data.storyteller_user_ids;
        }

        const viewerId = auth.user.value?.id;
        const incoming = (data.messages ?? []).map((row) => withMineForViewer(row, viewerId));
        merge(incoming, { playSounds: afterId > 0 });
        if (stickIc || stickOoc) {
            await scrollDown({ ic: stickIc, ooc: stickOoc });
        }
    }

    async function send() {
        const text = body.value.trim();
        if (!text || !canPost.value) {
            return;
        }
    
        const payload = {
            body: text,
            scene_id: selectedSceneId.value,
            is_ooc: isOoc.value,
        };
    
        const chosenSpeaker = speakerCharacterId?.value;
        let characterId = null;

        if (isStoryteller.value) {
            characterId = chosenSpeaker != null && chosenSpeaker !== ''
                ? chosenSpeaker
                : myCharacterId?.value;
        } else if (activePlayerCharacters.value.length > 1 && selectedPcId.value) {
            characterId = selectedPcId.value;
        } else {
            characterId = myCharacterId?.value;
        }

        if (characterId) {
            payload.character_id = characterId;
        }
    
        let path;
        try {
            path = chronicleCollectionPath(chronicleId, 'messages');
        } catch {
            return;
        }

        const { data } = await api.post(path, payload);
        merge([withMineForViewer(data.message, auth.user.value?.id)]);
        body.value = '';
        await scrollDown({ ic: !isOoc.value, ooc: isOoc.value });
    }

    async function toggleOoc(message) {
        const { data } = await api.patch(`/messages/${message.id}/ooc`, {
            is_ooc: !message.is_ooc,
        });
        updateMessage(data.message);
    }

    async function deleteMessage(message) {
        await api.delete(`/messages/${message.id}`);
        if (showDeleted.value) {
            updateMessage({ ...message, is_deleted: true });
        } else {
            messages.value = messages.value.filter((m) => m.id !== message.id);
        }
    }

    async function restoreMessage(message) {
        const { data } = await api.post(`/messages/${message.id}/restore`);
        updateMessage(data.message);
    }

    async function editMessage(message, body) {
        const { data } = await api.patch(`/messages/${message.id}`, { body });
        updateMessage(data.message);
    }

    return {
        messages,
        icMessages,
        oocMessages,
        body,
        isOoc,
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
    };
}