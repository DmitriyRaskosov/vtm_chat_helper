import { computed, ref, toValue } from 'vue';
import { api, CHRONICLE_STORAGE_KEY, currentChronicleId, refreshUser } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export const STORAGE_KEY = CHRONICLE_STORAGE_KEY;
export const CHRONICLE_NOT_SELECTED = 'Хроника не выбрана';

const chronicles = ref([]);
const loading = ref(false);
const error = ref('');

export function readStoredChronicleId() {
    const raw = localStorage.getItem(STORAGE_KEY);
    const id = Number.parseInt(raw ?? '', 10);

    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    return id;
}

export function resolveChroniclePath(subpath) {
    const id = readStoredChronicleId();

    if (id) {
        return `/c/${id}/${subpath}`;
    }

    return '/chronicles';
}

export function resolveChronicleId(chronicleId) {
    const id = Number(toValue(chronicleId));

    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    return id;
}

export function chronicleCollectionPath(chronicleId, suffix) {
    const id = resolveChronicleId(chronicleId);

    if (id === null) {
        throw new Error(CHRONICLE_NOT_SELECTED);
    }

    const path = suffix.startsWith('/') ? suffix.slice(1) : suffix;

    return `/chronicles/${id}/${path}`;
}

function syncCurrentIdWithList() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) {
        currentChronicleId.value = null;

        return;
    }

    const id = Number.parseInt(raw, 10);
    const exists = chronicles.value.some((item) => item.id === id);

    if (!Number.isFinite(id) || !exists) {
        localStorage.removeItem(STORAGE_KEY);
        currentChronicleId.value = null;

        return;
    }

    currentChronicleId.value = id;
}

function select(id) {
    const numericId = Number(id);
    const exists = chronicles.value.some((item) => item.id === numericId);

    if (!exists) {
        return;
    }

    currentChronicleId.value = numericId;
    localStorage.setItem(STORAGE_KEY, String(numericId));
}

function syncFromRoute(routeChronicleId) {
    const numericId = Number(routeChronicleId);

    if (!Number.isFinite(numericId) || numericId <= 0) {
        return;
    }

    if (!chronicles.value.length) {
        currentChronicleId.value = numericId;
        localStorage.setItem(STORAGE_KEY, String(numericId));

        return;
    }

    select(numericId);
}

function isKnownChronicle(routeChronicleId) {
    const numericId = Number(routeChronicleId);

    if (!Number.isFinite(numericId)) {
        return false;
    }

    if (!chronicles.value.length) {
        return true;
    }

    return chronicles.value.some((item) => item.id === numericId);
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
        currentChronicleId.value = null;
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

    try {
        await refreshUser();
    } catch {
        // Сеть: хроника уже в списке, select и переход возможны; роль подтянется после F5.
    }

    select(chronicle.id);

    return chronicle;
}

const currentChronicle = computed(() =>
    chronicles.value.find((item) => item.id === currentChronicleId.value) ?? null,
);

export function useCurrentChronicle() {
    return {
        chronicles,
        currentId: currentChronicleId,
        currentChronicle,
        loading,
        error,
        load,
        select,
        syncFromRoute,
        isKnownChronicle,
        create,
    };
}
