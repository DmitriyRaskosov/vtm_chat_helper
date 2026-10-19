<template>
    <div v-if="open" class="chat-character-drawer-scrim" aria-hidden="true"></div>
    <aside v-if="open" class="chat-character-drawer card">
            <header class="chat-character-drawer-header">
                <h2>{{ title }}</h2>
                <button type="button" class="link" @click="emit('close')">Закрыть</button>
            </header>

            <div v-if="isStoryteller" class="chat-character-drawer-select">
                <label for="chat-character-select">Персонаж в сцене</label>
                <select
                    id="chat-character-select"
                    :value="selectedCharacterId ?? ''"
                    @change="onSelectChange"
                >
                    <option v-for="row in participants" :key="row.character_id" :value="row.character_id">
                        {{ row.character_name }}
                    </option>
                </select>
            </div>

            <div v-if="selectedCharacterId" class="chat-character-drawer-body">
                <SheetImageSection :character-id="selectedCharacterId" :can-upload="false" />
                <SheetStateSection :character-id="selectedCharacterId" :can-edit="canEditState" />
            </div>
            <p v-else class="muted">Выберите персонажа.</p>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import SheetImageSection from '../sheet/SheetImageSection.vue';
import SheetStateSection from '../sheet/SheetStateSection.vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    isStoryteller: { type: Boolean, required: true },
    participants: { type: Array, default: () => [] },
    selectedCharacterId: { type: [Number, String], default: null },
    myCharacterId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'select-character']);

const title = computed(() => {
    if (!props.selectedCharacterId) {
        return 'Персонаж';
    }

    const row = props.participants.find(
        (item) => Number(item.character_id) === Number(props.selectedCharacterId),
    );

    return row?.character_name ?? 'Персонаж';
});

const canEditState = computed(() => {
    if (props.isStoryteller) {
        return true;
    }

    if (props.myCharacterId === null || props.selectedCharacterId === null) {
        return false;
    }

    return Number(props.myCharacterId) === Number(props.selectedCharacterId);
});

function onSelectChange(event) {
    const value = Number(event.target.value);

    emit('select-character', Number.isFinite(value) ? value : null);
}
</script>
