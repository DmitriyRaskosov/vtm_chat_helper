import { nextTick, ref, watch } from 'vue';
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
    getLog,
    myCharacterId,
    chronicleId,
    auth,
    isStoryteller,
}) {
    const messages = ref([]);
    const body = ref('');
    const isOoc = ref(readComposerOocFromStorage());
    const showDeleted = ref(false);

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

    async function scrollDown() {
        await nextTick();
        getLog()?.scrollToBottom?.();
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

        const stick = afterId === 0 || (getLog()?.isAtBottom?.() ?? true);
        merge(data.messages ?? [], { playSounds: afterId > 0 });
        if (stick) {
            await scrollDown();
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
        await scrollDown();
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
        body,
        isOoc,
        showDeleted,
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