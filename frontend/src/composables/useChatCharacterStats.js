import { computed, ref, unref, watch } from 'vue';
import { apiErrorMessage } from '../utils/apiError';
import {
    cycleDot,
    dotTrack,
    healthPenaltyFor,
    normalizeStats,
    useCharacterStats,
    useCharacterStatsBroadcast,
} from './useCharacterStats';

export const drawerHealthLevels = [
    { value: 0, label: '0 - Healthy' },
    { value: 1, label: '1 - Bruised' },
    { value: 2, label: '2 - Hurt' },
    { value: 3, label: '3 - Injured' },
    { value: 4, label: '4 - Wounded' },
    { value: 5, label: '5 - Mauled' },
    { value: 6, label: '6 - Crippled' },
    { value: 7, label: '7 - Incapacitated' },
];

export function useChatCharacterStats(characterId, canEdit, selectedSceneId = null) {
    const localStats = ref(normalizeStats(null));
    const loading = ref(false);
    const { saving, error, load, save } = useCharacterStats(characterId);
    let epoch = 0;

    const healthLevel = computed({
        get() {
            return Number(localStats.value.health?.level ?? 0);
        },
        set(value) {
            writeHealth(Number(value), healthDamage.value);
        },
    });

    const healthDamage = computed({
        get() {
            return localStats.value.health?.damage ?? 'bashing';
        },
        set(value) {
            writeHealth(healthLevel.value, value);
        },
    });

    const healthPenalty = computed(() => healthPenaltyFor(healthLevel.value));

    watch(resolveCharacterId, () => {
        refresh();
    }, { immediate: true });

    if (selectedSceneId != null) {
        useCharacterStatsBroadcast(selectedSceneId, characterId, (stats) => {
            localStats.value = stats;
        });
    }

    function resolveCharacterId() {
        const source = typeof characterId === 'function' ? characterId() : unref(characterId);

        return Number(unref(source));
    }

    function track(key, max) {
        return dotTrack(localStats.value[key], max);
    }

    function writeHealth(level, damage) {
        localStats.value = {
            ...localStats.value,
            health: {
                level,
                damage,
                penalty: healthPenaltyFor(level),
            },
        };
    }

    async function refresh() {
        const current = ++epoch;
        const numericId = resolveCharacterId();

        if (!Number.isFinite(numericId) || numericId <= 0) {
            localStats.value = normalizeStats(null);

            return;
        }

        loading.value = true;

        try {
            const loaded = await load(numericId);
            if (current !== epoch) {
                return;
            }
            localStats.value = loaded.stats;
            error.value = '';
        } catch (err) {
            if (current !== epoch) {
                return;
            }
            error.value = apiErrorMessage(err, 'Не удалось загрузить состояние.');
        } finally {
            if (current === epoch) {
                loading.value = false;
            }
        }
    }

    async function onDotClick(key, index, max) {
        if (!unref(canEdit) || saving.value || loading.value) {
            return;
        }

        const position = Number(index) - 1;
        if (position < 0 || position >= max) {
            return;
        }

        localStats.value = {
            ...localStats.value,
            [key]: cycleDot(dotTrack(localStats.value[key], max), position),
        };
        await persist();
    }

    async function onHealthChange() {
        if (!unref(canEdit) || saving.value || loading.value) {
            return;
        }

        writeHealth(healthLevel.value, healthDamage.value);
        await persist();
    }

    async function persist() {
        const current = ++epoch;

        try {
            const saved = await save(localStats.value);
            if (current !== epoch) {
                return;
            }
            localStats.value = saved.stats;
        } catch {
            const message = error.value;
            if (current !== epoch) {
                return;
            }
            await refresh();
            error.value = message;
        }
    }

    return {
        localStats,
        loading,
        saving,
        error,
        healthLevel,
        healthDamage,
        healthPenalty,
        track,
        onDotClick,
        onHealthChange,
    };
}
