import { computed, ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

const STORAGE_KEY = 'game-chat-chronicle';

const chronicles = ref([]);
const currentId = ref(null);
const loading = ref(false);
const error = ref('');

function syncCurrentIdWithList() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) {
        currentId.value = null;

        return;
    }

    const id = Number.parseInt(raw, 10);
    const exists = chronicles.value.some((item) => item.id === id);

    if (!Number.isFinite(id) || !exists) {
        localStorage.removeItem(STORAGE_KEY);
        currentId.value = null;

        return;
    }

    currentId.value = id;
}

function select(id) {
    const numericId = Number(id);
    const exists = chronicles.value.some((item) => item.id === numericId);

    if (!exists) {
        return;
    }

    currentId.value = numericId;
    localStorage.setItem(STORAGE_KEY, String(numericId));
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/chronicles');
        chronicles.value = data.chronicles ?? [];
        syncCurrentIdWithList();
    } catch (err) {
        chronicles.value = [];
        currentId.value = null;
        error.value = apiErrorMessage(err, 'Не удалось загрузить хроники.');
    } finally {
        loading.value = false;
    }
}

async function create({ title, description, setting }) {
    error.value = '';

    const { data } = await api.post('/chronicles', {
        title,
        description: description || null,
        setting: setting || null,
    });

    const chronicle = data.chronicle;
    chronicles.value = [...chronicles.value, chronicle];
    select(chronicle.id);

    return chronicle;
}

const currentChronicle = computed(() =>
    chronicles.value.find((item) => item.id === currentId.value) ?? null,
);

export function useCurrentChronicle() {
    return {
        chronicles,
        currentId,
        currentChronicle,
        loading,
        error,
        load,
        select,
        create,
    };
}
