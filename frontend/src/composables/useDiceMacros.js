import { ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';
import { canonicalStatKey } from '../utils/sheetStatCatalog';

export function calculatePool(macroStats, characterStats) {
    const stats = characterStats && typeof characterStats === 'object' ? characterStats : {};
    let total = 0;

    for (const name of statNames(macroStats)) {
        total += countableDots(statTrack(stats, canonicalStatKey(name)));
    }

    return total;
}

function statNames(macroStats) {
    const source = Array.isArray(macroStats)
        ? macroStats
        : (typeof macroStats === 'string' ? [macroStats] : []);
    const names = [];

    for (const name of source) {
        if (typeof name !== 'string') {
            continue;
        }
        for (const part of name.split(',')) {
            const trimmed = part.trim();
            if (trimmed !== '') {
                names.push(trimmed);
            }
        }
    }

    return names;
}

function statTrack(stats, key) {
    if (key === '' || stats[key] === undefined) {
        const disciplines = stats.disciplines;
        if (disciplines && typeof disciplines === 'object' && Array.isArray(disciplines[key])) {
            return disciplines[key];
        }

        return null;
    }

    return stats[key];
}

function countableDots(value) {
    if (!Array.isArray(value)) {
        return 0;
    }

    let count = 0;

    for (const dot of value) {
        const state = Number(dot);
        if (state === 1 || state === 2) {
            count += 1;
        }
    }

    return count;
}

export function useDiceMacros() {
    const macros = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');

    async function load() {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get('/dice-macros');
            macros.value = Array.isArray(data.macros) ? data.macros : [];
        } catch (err) {
            macros.value = [];
            error.value = apiErrorMessage(err, 'Не удалось загрузить макросы.');
        } finally {
            loading.value = false;
        }
    }

    function replaceMacro(macro) {
        const index = macros.value.findIndex((row) => row.id === macro.id);
        if (index === -1) {
            macros.value = [...macros.value, macro];

            return;
        }

        const next = macros.value.slice();
        next[index] = macro;
        macros.value = next;
    }

    async function createMacro(payload) {
        if (saving.value) {
            return false;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.post('/dice-macros', payload);
            if (data.macro) {
                replaceMacro(data.macro);
            }

            return true;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось создать макрос.');

            return false;
        } finally {
            saving.value = false;
        }
    }

    async function updateMacro(id, payload) {
        if (saving.value) {
            return false;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.patch(`/dice-macros/${id}`, payload);
            if (data.macro) {
                replaceMacro(data.macro);
            }

            return true;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось сохранить макрос.');

            return false;
        } finally {
            saving.value = false;
        }
    }

    async function deleteMacro(id) {
        if (saving.value) {
            return false;
        }

        saving.value = true;
        error.value = '';

        try {
            await api.delete(`/dice-macros/${id}`);
            macros.value = macros.value.filter((row) => row.id !== id);

            return true;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось удалить макрос.');

            return false;
        } finally {
            saving.value = false;
        }
    }

    return {
        macros,
        loading,
        saving,
        error,
        load,
        createMacro,
        updateMacro,
        deleteMacro,
    };
}
