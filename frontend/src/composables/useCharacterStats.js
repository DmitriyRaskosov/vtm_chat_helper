import { ref, unref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function dotTrack(value, max) {
    const length = Math.max(0, Number(max) || 0);

    if (Array.isArray(value)) {
        const dots = value.slice(0, length).map((dot) => {
            const state = Number(dot);

            return state === 1 || state === 2 ? state : 0;
        });

        while (dots.length < length) {
            dots.push(0);
        }

        return dots;
    }

    let current = 0;
    let temporary = false;

    if (typeof value === 'number') {
        current = value;
    } else if (value && typeof value === 'object') {
        current = Number(value.current ?? 0);
        temporary = value.is_temporary === true;
    }

    const filled = Math.max(0, Math.min(length, current));
    const dots = Array(length).fill(0);

    for (let index = 0; index < filled; index += 1) {
        dots[index] = temporary && index === filled - 1 ? 2 : 1;
    }

    return dots;
}

export function normalizeStats(stats) {
    const source = stats && typeof stats === 'object' ? stats : {};
    const next = {};

    for (const [key, value] of Object.entries(source)) {
        if (key === 'health') {
            const health = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
            next.health = {
                level: Number(health.level ?? 0),
                damage: health.damage ?? 'bashing',
                penalty: Number(health.penalty ?? 0),
            };
            continue;
        }

        if (Array.isArray(value)) {
            next[key] = value.map((dot) => {
                const state = Number(dot);

                return state === 1 || state === 2 ? state : 0;
            });
            continue;
        }

        next[key] = value;
    }

    return next;
}

function resolveId(source) {
    const value = typeof source === 'function' ? source() : unref(source);

    return Number(unref(value));
}

export function useCharacterStats(characterId) {
    const saving = ref(false);
    const error = ref('');

    async function load(id = characterId) {
        const numericId = resolveId(id);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            return normalizeStats(null);
        }

        const { data } = await api.get(`/characters/${numericId}`);
        const stats = normalizeStats(data.character?.stats);

        console.log('[StatsGrid] stats loaded', stats);

        return stats;
    }

    async function save(stats) {
        const numericId = resolveId(characterId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        const payload = normalizeStats(stats);
        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.patch(`/characters/${numericId}/stats`, { stats: payload });

            return normalizeStats(data.stats ?? payload);
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось сохранить статы.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    return {
        saving,
        error,
        load,
        save,
    };
}
