import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { api } from '../auth';
import { playSoundForMessage } from '../utils/chatMessageSound';
import { CHRONICLE_NOT_SELECTED, chronicleCollectionPath } from './useCurrentChronicle';

const COMPOSER_MODE_KEY = 'chat-composer-mode';

function readComposerOocFromStorage() {
    return localStorage.getItem(COMPOSER_MODE_KEY) === 'ooc';
}

function writeComposerOocToStorage(isOoc) {
    localStorage.setItem(COMPOSER_MODE_KEY, isOoc ? 'ooc' : 'ic');
}

export function useChatMessages({
    selectedSceneId,
    canPost,
    getIcLog,
    getOocLog,
    myCharacterId,
    chronicleId,
    auth,
    isStoryteller,
}) {
    const messages = ref([]);
    const body = ref('');
    const isOoc = ref(readComposerOocFromStorage());
    const showDeleted = ref(false);
    const isTyping = ref(false);
    const storytellerUserIds = ref([]);
    const isIcAutoScroll = ref(true);
    const isOocAutoScroll = ref(true);

    const icMessages = computed(() => messages.value.filter((message) => !message.is_ooc));
    const oocMessages = computed(() => messages.value.filter(
        (message) => message.is_ooc && !message.is_system,
    ));

    let typingIdleTimer = null;

    function clearTypingTimer() {
        if (typingIdleTimer !== null) {
            clearTimeout(typingIdleTimer);
            typingIdleTimer = null;
        }
    }

    watch(body, (value) => {
        if (!value.trim()) {
            isTyping.value = false;
            clearTypingTimer();

            return;
        }

        isTyping.value = true;
        clearTypingTimer();
        typingIdleTimer = setTimeout(() => {
            isTyping.value = false;
            typingIdleTimer = null;
        }, 2000);
    });

    onUnmounted(() => {
        clearTypingTimer();
    });

    watch(isOoc, (value) => {
        writeComposerOocToStorage(value);
    });

    watch(showDeleted, async () => {
        messages.value = [];
        await load();
    });

    function lastId() {
        return messages.value.at(-1)?.id ?? 0;
    }

    function merge(incoming, { playSounds = false } = {}) {
        const added = [];

        for (const message of incoming) {
            if (!messages.value.some((item) => item.id === message.id)) {
                messages.value.push(message);
                added.push(message);
            }
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

        merge(data.messages ?? [], { playSounds: afterId > 0 });
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
    
        if (myCharacterId?.value) {
            payload.character_id = myCharacterId.value;
        }
    
        let path;
        try {
            path = chronicleCollectionPath(chronicleId, 'messages');
        } catch {
            return;
        }

        const { data } = await api.post(path, payload);
        merge([data.message]);
        body.value = '';
        isTyping.value = false;
        clearTypingTimer();
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
    };
}