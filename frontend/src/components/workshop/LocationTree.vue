<template>
    <ul class="character-tree location-node-tree">
        <li v-for="node in nodes" :key="node.id">
            <div class="location-node-row" :style="rowStyle">
                <template v-if="editingId === node.id">
                    <input
                        v-model="draft.name"
                        type="text"
                        maxlength="120"
                        class="location-node-input"
                    />
                    <button type="button" :disabled="disabled || !draft.name.trim()" @click="saveEdit(node.id)">
                        Сохранить
                    </button>
                    <button type="button" class="secondary" :disabled="disabled" @click="cancelEdit">Отмена</button>
                </template>
                <template v-else>
                    <strong>{{ node.name }}</strong>
                    <span v-if="node.description" class="muted"> · {{ node.description }}</span>
                    <div class="location-node-actions">
                        <button type="button" class="secondary" :disabled="disabled" @click="startEdit(node)">
                            Переименовать
                        </button>
                        <button type="button" class="secondary" :disabled="disabled" @click="emit('delete', node.id)">
                            Удалить
                        </button>
                    </div>
                </template>
            </div>
            <LocationTree
                v-if="node.children?.length"
                :nodes="node.children"
                :depth="depth + 1"
                :disabled="disabled"
                @update="(payload) => emit('update', payload)"
                @delete="(nodeId) => emit('delete', nodeId)"
            />
        </li>
    </ul>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';

defineOptions({ name: 'LocationTree' });

const props = defineProps({
    nodes: { type: Array, required: true },
    depth: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'delete']);

const editingId = ref(null);
const draft = reactive({
    name: '',
});

const rowStyle = computed(() => ({
    paddingLeft: `${props.depth * 1.25}rem`,
}));

function startEdit(node) {
    editingId.value = node.id;
    draft.name = node.name ?? '';
}

function cancelEdit() {
    editingId.value = null;
    draft.name = '';
}

function saveEdit(nodeId) {
    const name = draft.name.trim();
    if (!name) {
        return;
    }

    emit('update', { id: nodeId, name });
    cancelEdit();
}
</script>
