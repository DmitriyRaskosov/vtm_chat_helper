<template>
    <article
        class="combat-card"
        :class="{
            'is-current': current,
            'is-out': participant.is_active === false,
            'is-drop-target': dropTarget,
        }"
        :draggable="draggable"
        @dragstart="onDragStart"
        @dragover="onDragOver"
        @drop="onDrop"
        @dragend="emit('drag-end')"
    >
        <img v-if="avatarUrl" class="combat-card-avatar" :src="avatarUrl" alt="" />
        <span v-else class="combat-card-fallback" aria-hidden="true">{{ fallback }}</span>
        <span class="combat-card-name">{{ name }}</span>
        <span v-if="participant.is_hidden" class="combat-card-badge">Скрыт</span>
        <span v-if="participant.initiative != null" class="combat-card-initiative">{{ participant.initiative }}</span>
        <div v-if="manage" class="combat-card-actions" @mousedown.stop>
            <button type="button" class="btn-sm" :disabled="busy" @click="emit('toggle-active')">
                {{ participant.is_active === false ? 'Вернуть' : 'Выбыл' }}
            </button>
            <button
                v-if="participant.is_hidden"
                type="button"
                class="btn-sm"
                :disabled="busy"
                @click="emit('reveal')"
            >
                Раскрыть
            </button>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { storagePublicUrl } from '../../utils/storagePublicUrl';

const props = defineProps({
    participant: { type: Object, required: true },
    index: { type: Number, required: true },
    current: { type: Boolean, default: false },
    draggable: { type: Boolean, default: false },
    dropTarget: { type: Boolean, default: false },
    manage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['drag-start', 'drag-over', 'drop', 'drag-end', 'toggle-active', 'reveal']);

const avatarUrl = computed(() => storagePublicUrl(props.participant.avatar_path));
const name = computed(() => {
    const value = props.participant.name;
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    return 'Без имени';
});
const fallback = computed(() => name.value.slice(0, 1).toUpperCase());

function onDragStart(event) {
    if (!props.draggable) {
        event.preventDefault();
        return;
    }

    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(props.index));
    emit('drag-start', props.index);
}

function onDragOver(event) {
    if (!props.draggable) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    emit('drag-over', props.index);
}

function onDrop(event) {
    event.preventDefault();
    emit('drop', props.index);
}

</script>

<style scoped>
.combat-card {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    width: 5.5rem;
    padding: 0.35rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-light);
    cursor: default;
}

.combat-card[draggable='true'] {
    cursor: grab;
}

.combat-card.is-current {
    box-shadow: 0 0 0 2px var(--gold);
}

.combat-card.is-out {
    filter: grayscale(1);
    opacity: 0.55;
}

.combat-card.is-out .combat-card-name {
    text-decoration: line-through;
}

.combat-card.is-drop-target {
    outline: 1px dashed var(--gold);
}

.combat-card-avatar,
.combat-card-fallback {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 4px;
    object-fit: cover;
}

.combat-card-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-main);
    color: var(--text-muted);
    font-weight: 700;
}

.combat-card-name {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.72rem;
}

.combat-card-badge,
.combat-card-initiative {
    font-size: 0.65rem;
    color: var(--text-muted);
}

.combat-card-badge {
    color: var(--warning);
}

.combat-card-actions {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    width: 100%;
}

.combat-card-actions .btn-sm {
    margin: 0;
    padding: 0.05rem 0.2rem;
    font-size: 0.65rem;
}
</style>
