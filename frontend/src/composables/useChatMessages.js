import { nextTick, ref } from 'vue';
import { api } from '../auth';

export function useChatMessages({ selectedSceneId, canPost, getLog }) {
    const messages = ref([]);
    const body = ref('');

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

    async function scrollDown() {
        await nextTick();
        getLog()?.scrollToBottom?.();
    }

    async function load(afterId = 0) {
        if (!selectedSceneId.value) {
            return;
        }

        const sceneIdAtStart = selectedSceneId.value;
        const { data } = await api.get('/messages', {
            params: {
                scene_id: sceneIdAtStart,
                after_id: afterId,
            },
        });

        if (selectedSceneId.value !== sceneIdAtStart) {
            return;
        }

        merge(data.messages);
        await scrollDown();
    }

    async function send() {
        const text = body.value.trim();
        if (!text || !canPost.value) {
            return;
        }
        const { data } = await api.post('/messages', {
            body: text,
            scene_id: selectedSceneId.value,
        });
        merge([data.message]);
        body.value = '';
        await scrollDown();
    }

    return {
        messages,
        body,
        lastId,
        merge,
        scrollDown,
        load,
        send,
    };
}
