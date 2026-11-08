import { ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

function promptPath(key) {
    return `/workshop/prompts/${encodeURIComponent(key)}`;
}

function replacePrompt(list, prompt) {
    const index = list.findIndex((row) => row.key === prompt.key);
    if (index === -1) {
        return [...list, prompt];
    }

    const next = list.slice();
    next[index] = prompt;

    return next;
}

export function usePrompts() {
    const prompts = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');

    async function load() {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get('/workshop/prompts');
            prompts.value = Array.isArray(data.prompts) ? data.prompts : [];
        } catch (err) {
            prompts.value = [];
            error.value = apiErrorMessage(err, 'Не удалось загрузить промпты.');
        } finally {
            loading.value = false;
        }
    }

    async function save(key, content) {
        if (saving.value) {
            return null;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.put(promptPath(key), { content });
            if (data.prompt) {
                prompts.value = replacePrompt(prompts.value, data.prompt);
            }

            return data.prompt ?? null;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось сохранить промпт.');

            return null;
        } finally {
            saving.value = false;
        }
    }

    async function reset(key) {
        if (saving.value) {
            return false;
        }

        saving.value = true;
        error.value = '';

        try {
            await api.delete(promptPath(key));
            await load();

            return true;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось сбросить промпт.');

            return false;
        } finally {
            saving.value = false;
        }
    }

    return {
        prompts,
        loading,
        saving,
        error,
        load,
        save,
        reset,
    };
}
