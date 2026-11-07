import { ref, toValue, unref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';
import { useSceneChannelListener } from './useSceneChannel';

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

export function cycleDot(dots, position) {
    const next = dots.slice();
    const state = next[position];

    if (state === 0) {
        for (let cursor = 0; cursor <= position; cursor += 1) {
            if (next[cursor] === 0) {
                next[cursor] = 2;
            }
        }
    } else if (state === 2) {
        next[position] = 1;
    } else if (state === 1) {
        for (let cursor = position; cursor < next.length; cursor += 1) {
            next[cursor] = 0;
        }
    }

    return next;
}

export function healthPenaltyFor(level) {
    if (level <= 1) {
        return 0;
    }
    if (level <= 3) {
        return -1;
    }
    if (level <= 5) {
        return -2;
    }
    if (level === 6) {
        return -5;
    }
    if (level >= 7) {
        return -100;
    }

    return 0;
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

        if (key === 'disciplines' && value && typeof value === 'object' && !Array.isArray(value)) {
            const disciplines = {};

            for (const [slug, dots] of Object.entries(value)) {
                disciplines[slug] = dotTrack(dots, 5);
            }

            next.disciplines = disciplines;
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
            return {
                stats: normalizeStats(null),
                sheet_data: null,
            };
        }

        const { data } = await api.get(`/characters/${numericId}`);
        const stats = normalizeStats(data.character?.stats);

        return {
            stats,
            sheet_data: data.character?.sheet_data ?? null,
        };
    }

    async function save(stats, sheetData = null) {
        const numericId = resolveId(characterId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        const payload = { stats: normalizeStats(stats) };
        if (sheetData !== null) {
            payload.sheet_data = sheetData;
        }
        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.patch(`/characters/${numericId}/stats`, payload);

            return {
                stats: normalizeStats(data.stats ?? payload.stats),
                sheet_data: data.sheet_data ?? sheetData,
            };
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

/**
 * @param {import('vue').MaybeRefOrGetter<number|null|undefined>} selectedSceneId
 * @param {import('vue').MaybeRefOrGetter<number|null|undefined>} characterId
 * @param {(stats: ReturnType<typeof normalizeStats>, sheetData: object|null) => void} onUpdate
 */
export function useCharacterStatsBroadcast(selectedSceneId, characterId, onUpdate) {
    useSceneChannelListener(selectedSceneId, '.CharacterStatsUpdated', (payload) => {
        const targetId = Number(payload?.character_id);
        const openId = Number(toValue(characterId));

        if (!Number.isFinite(targetId) || !Number.isFinite(openId) || targetId !== openId) {
            return;
        }

        const statsBlock = payload?.stats;
        const stats = normalizeStats(statsBlock?.stats ?? statsBlock);
        const sheetData = statsBlock?.sheet_data ?? null;
        onUpdate(stats, sheetData);
    });
}
