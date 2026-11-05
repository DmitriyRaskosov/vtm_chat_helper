import { computed, ref, unref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function buildLocationNodeTree(flatNodes) {
    const nodes = (flatNodes ?? []).map((node) => ({
        ...node,
        children: [],
    }));
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const roots = [];

    for (const node of nodes) {
        const parentId = node.parent_id;
        if (parentId == null) {
            roots.push(node);
            continue;
        }
        const parent = byId.get(parentId);
        if (parent) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
}

export function useLocationNodes(chronicleLocationId) {
    const nodes = ref([]);
    const loading = ref(false);
    const parsing = ref(false);
    const saving = ref(false);
    const error = ref('');

    const tree = computed(() => buildLocationNodeTree(nodes.value));

    async function load() {
        const id = unref(chronicleLocationId);
        if (id == null) {
            nodes.value = [];
            return;
        }

        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get(`/locations/${id}/location-nodes`);
            nodes.value = data.location_nodes ?? [];
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить объекты локации.');
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function parse(environmentDetails) {
        const id = unref(chronicleLocationId);
        const text = environmentDetails?.trim() ?? '';
        if (id == null || text === '') {
            return;
        }

        parsing.value = true;
        error.value = '';

        try {
            const { data } = await api.post(`/locations/${id}/location-nodes/parse`, {
                environment_details: text,
            });
            nodes.value = data.location_nodes ?? [];
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось разобрать текст на объекты.');
            throw err;
        } finally {
            parsing.value = false;
        }
    }

    async function update(nodeId, payload) {
        const id = unref(chronicleLocationId);
        if (id == null) {
            return null;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.patch(`/locations/${id}/location-nodes/${nodeId}`, payload);
            await load();
            return data.location_node;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось обновить объект.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function remove(nodeId) {
        const id = unref(chronicleLocationId);
        if (id == null) {
            return;
        }

        saving.value = true;
        error.value = '';

        try {
            await api.delete(`/locations/${id}/location-nodes/${nodeId}`);
            await load();
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось удалить объект.');
            throw err;
        } finally {
            saving.value = false;
        }
    }

    watch(
        () => unref(chronicleLocationId),
        (id) => {
            if (id == null) {
                nodes.value = [];
                return;
            }
            load();
        },
    );

    return {
        nodes,
        tree,
        loading,
        parsing,
        saving,
        error,
        load,
        parse,
        update,
        remove,
    };
}
