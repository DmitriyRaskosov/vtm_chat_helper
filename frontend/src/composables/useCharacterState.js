import { onUnmounted, ref, unref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useCharacterState(characterId) {
    const bloodPool = ref(10);
    const temporaryWillpower = ref(5);
    const healthLevel = ref(0);
    const healthDamage = ref('bashing');
    const healthPenalty = ref(0);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');

    function applyState(state) {
        bloodPool.value = state?.blood_pool ?? 10;
        temporaryWillpower.value = state?.temporary_willpower ?? 5;
        healthLevel.value = state?.health_level ?? 0;
        healthDamage.value = state?.health_damage ?? 'bashing';
        healthPenalty.value = state?.health_penalty ?? 0;
    }

    async function load(id) {
        const numericId = Number(unref(id));

        if (!Number.isFinite(numericId) || numericId <= 0) {
            applyState(null);

            return null;
        }

        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get(`/characters/${numericId}`);
            applyState(data.character?.state);

            return data.character?.state ?? null;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить состояние.');
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function save(id) {
        const numericId = Number(unref(id));

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.put(`/characters/${numericId}/state`, {
                blood_pool: bloodPool.value,
                temporary_willpower: temporaryWillpower.value,
                health_level: healthLevel.value,
                health_damage: healthDamage.value,
            });
            applyState(data.state);

            return data.state;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось сохранить состояние.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    watch(
        () => unref(characterId),
        (id) => {
            load(id);
        },
        { immediate: true },
    );

    onUnmounted(() => {
        error.value = '';
    });

    return {
        bloodPool,
        temporaryWillpower,
        healthLevel,
        healthDamage,
        healthPenalty,
        loading,
        saving,
        error,
        load,
        save,
    };
}
