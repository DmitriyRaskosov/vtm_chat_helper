import { ref, unref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useCharacterInventory(characterId) {
    const items = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');
    const active = ref(false);
    let requestSeq = 0;

    function currentId() {
        const id = Number(unref(characterId));

        return Number.isFinite(id) && id > 0 ? id : null;
    }

    async function load() {
        const id = currentId();
        const seq = ++requestSeq;

        if (id === null) {
            items.value = [];
            error.value = '';
            loading.value = false;

            return;
        }

        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get(`/characters/${id}/items`);
            if (seq !== requestSeq || currentId() !== id) {
                return;
            }
            items.value = Array.isArray(data.items) ? data.items : [];
        } catch (err) {
            if (seq !== requestSeq) {
                return;
            }
            error.value = apiErrorMessage(err, 'Не удалось загрузить инвентарь.');
        } finally {
            if (seq === requestSeq) {
                loading.value = false;
            }
        }
    }

    function replaceItem(item) {
        const index = items.value.findIndex((row) => row.id === item.id);
        if (index === -1) {
            items.value = [...items.value, item];

            return;
        }

        const next = items.value.slice();
        next[index] = item;
        items.value = next;
    }

    async function addItem(payload) {
        const id = currentId();
        if (id === null || saving.value) {
            return;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.post(`/characters/${id}/items`, {
                name: String(payload.name ?? '').trim(),
                quantity: payload.quantity,
                is_equipped: Boolean(payload.isEquipped),
            });
            if (currentId() === id && data.item) {
                replaceItem(data.item);
            }

            return true;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось добавить предмет.');

            return false;
        } finally {
            saving.value = false;
        }
    }

    async function updateItem(item, patch) {
        const id = currentId();
        if (id === null || saving.value) {
            return;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.patch(`/characters/${id}/items/${item.id}`, patch);
            if (currentId() === id && data.item) {
                replaceItem(data.item);
            }
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось изменить предмет.');
        } finally {
            saving.value = false;
        }
    }

    async function removeItem(item) {
        const id = currentId();
        if (id === null || saving.value) {
            return;
        }

        saving.value = true;
        error.value = '';

        try {
            await api.delete(`/characters/${id}/items/${item.id}`);
            if (currentId() === id) {
                items.value = items.value.filter((row) => row.id !== item.id);
            }
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось удалить предмет.');
        } finally {
            saving.value = false;
        }
    }

    function moveItem(item) {
        return updateItem(item, { is_equipped: !item.is_equipped });
    }

    function open() {
        active.value = true;

        return load();
    }

    function close() {
        active.value = false;
    }

    watch(() => unref(characterId), () => {
        if (active.value) {
            load();
        }
    });

    return {
        items,
        loading,
        saving,
        error,
        load,
        addItem,
        updateItem,
        removeItem,
        moveItem,
        open,
        close,
    };
}
