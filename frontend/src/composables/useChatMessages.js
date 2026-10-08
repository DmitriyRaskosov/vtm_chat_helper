import { nextTick, ref, watch } from 'vue';
import { api } from '../auth';

export function useChatMessages({ selectedSceneId, canPost, getLog }) {
    const messages = ref([]);
    const body = ref('');
    const isOoc = ref(false);
    const showDeleted = ref(false);

    watch(showDeleted, async () => {
        messages.value = [];
        await load();
    });

    function lastId() {
        return messages.value.at(-1)?.id ?? 0;
    }

    function merge(incoming) {
        for (const message of incoming) {
            if (!messages.value.some((item) => item.id === message.id)) {
                messages.value.push(message);
            }
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

        const { data } = await api.get('/messages', { params });

        if (selectedSceneId.value !== sceneIdAtStart) {
            return;
        }

        const stick = afterId === 0 || (getLog()?.isAtBottom?.() ?? true);
        merge(data.messages);
        if (stick) {
            await scrollDown();
        }
    }

    async function send() {
        const text = body.value.trim();
        if (!text || !canPost.value) {
            return;
        }
        const { data } = await api.post('/messages', {
            body: text,
            scene_id: selectedSceneId.value,
            is_ooc: isOoc.value,
        });
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
    };
}