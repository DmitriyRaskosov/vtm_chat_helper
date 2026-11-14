import { ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';
import { useFlash } from './useFlash';

export function useLibraryCharacters() {
    const templates = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');
    const successMessage = ref('');

    const { flashRef } = useFlash();

    function notifySuccess(message) {
        flashRef(successMessage, message, 'library-success');
    }

    async function fetchLibrary() {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get('/library-characters');
            const rows = data.library_characters;
            templates.value = Array.isArray(rows) ? rows : [];

            return templates.value;
        } catch (err) {
            templates.value = [];
            error.value = apiErrorMessage(err, 'Не удалось загрузить библиотеку.');
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createTemplate(payload) {
        const name = String(payload?.name ?? '').trim();

        if (name === '') {
            error.value = 'Укажите имя персонажа.';
            throw new Error(error.value);
        }

        saving.value = true;
        error.value = '';

        const body = {
            name,
            clan_id: Number(payload?.clan_id),
        };
        const concept = String(payload?.concept ?? '').trim();

        if (concept !== '') {
            body.concept = concept;
        }

        if (payload?.character_type) {
            body.character_type = payload.character_type;
        }

        try {
            const { data } = await api.post('/library-characters', body);
            const created = data.library_character ?? null;

            if (created) {
                templates.value = [created, ...templates.value.filter((row) => Number(row.id) !== Number(created.id))];
            } else {
                await fetchLibrary();
            }

            notifySuccess('Персонаж сохранён в библиотеку.');

            return created;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось создать персонажа.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function saveToLibrary(characterId) {
        const numericId = Number(characterId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.post('/library-characters', {
                character_id: numericId,
            });
            const created = data.library_character ?? null;

            if (created) {
                templates.value = [created, ...templates.value.filter((row) => Number(row.id) !== Number(created.id))];
            } else {
                await fetchLibrary();
            }

            notifySuccess('Персонаж сохранён в библиотеку.');

            return created;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось сохранить в библиотеку.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function deleteTemplate(templateId) {
        const numericId = Number(templateId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Шаблон не выбран.');
        }

        saving.value = true;
        error.value = '';

        try {
            await api.delete(`/library-characters/${numericId}`);
            templates.value = templates.value.filter((row) => Number(row.id) !== numericId);
            notifySuccess('Шаблон удалён.');
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось удалить шаблон.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function importTemplate(chronicleId, templateId, canonicalName) {
        const chronicle = Number(chronicleId);
        const template = Number(templateId);
        const name = String(canonicalName ?? '').trim();

        if (!Number.isFinite(chronicle) || chronicle <= 0) {
            throw new Error('Хроника не выбрана.');
        }

        if (!Number.isFinite(template) || template <= 0) {
            throw new Error('Шаблон не выбран.');
        }

        if (name === '') {
            throw new Error('Укажите имя персонажа.');
        }

        saving.value = true;

        try {
            const { data } = await api.post(`/chronicles/${chronicle}/characters/import`, {
                library_character_id: template,
                canonical_name: name,
            });
            notifySuccess('Персонаж импортирован в хронику.');

            return data.character ?? null;
        } catch (err) {
            throw err;
        } finally {
            saving.value = false;
        }
    }

    return {
        templates,
        loading,
        saving,
        error,
        successMessage,
        fetchLibrary,
        createTemplate,
        saveToLibrary,
        deleteTemplate,
        importTemplate,
    };
}
