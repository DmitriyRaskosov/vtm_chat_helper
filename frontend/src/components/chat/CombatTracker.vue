<template>
    <FloatingWindow
        v-if="open"
        :title="windowTitle"
        panel-class="floating-window--combat"
        :initial-left="24"
        :initial-top="88"
        @close="open = false"
    >
        <template #tools>
            <button
                v-if="isStoryteller && encounter"
                type="button"
                class="btn-sm floating-window-tool"
                :disabled="busy"
                @click="rollInitiative"
            >
                Рассчитать инициативу
            </button>
        </template>
        <div class="combat-tracker-body">
                <p v-if="loading" class="muted">Загрузка…</p>
                <p v-if="error" class="error">{{ error }}</p>

                <div v-if="!encounter" class="combat-tracker-empty">
                    <p class="muted">{{ isStoryteller ? 'Бой ещё не начат.' : 'Сейчас нет боя.' }}</p>
                    <button
                        v-if="isStoryteller"
                        type="button"
                        :disabled="busy || !sceneId"
                        @click="startCombat"
                    >
                        Начать бой
                    </button>
                </div>

                <template v-else>
                    <div class="combat-tracker-toolbar">
                        <button
                            v-if="isStoryteller"
                            type="button"
                            class="btn-sm"
                            :disabled="busy"
                            @click="nextTurn"
                        >
                            Следующий ход
                        </button>
                        <button
                            v-if="isStoryteller"
                            type="button"
                            class="btn-sm"
                            :disabled="busy"
                            @click="addOpen = !addOpen"
                        >
                            Добавить
                        </button>
                        <button
                            v-if="isStoryteller"
                            type="button"
                            class="btn-sm"
                            :disabled="busy"
                            @click="finishCombat"
                        >
                            Завершить
                        </button>
                    </div>

                    <form v-if="isStoryteller && addOpen" class="combat-tracker-add" @submit.prevent="submitAdd">
                        <select v-model="characterId" :disabled="busy">
                            <option :value="null">Кого добавить</option>
                            <option
                                v-for="row in available"
                                :key="row.character_id"
                                :value="row.character_id"
                            >
                                {{ row.character_name || 'Без имени' }}
                            </option>
                        </select>
                        <label class="combat-tracker-hidden">
                            <input v-model="addHidden" type="checkbox" :disabled="busy" />
                            Скрытый
                        </label>
                        <button type="submit" class="btn-sm" :disabled="busy || characterId == null">
                            В бой
                        </button>
                    </form>

                    <p v-if="!participants.length" class="muted">В очереди никого.</p>
                    <div v-else class="combat-tracker-strip">
                        <template v-for="(participant, index) in participants" :key="cardKey(participant, index)">
                            <CombatParticipantPlaceholder
                                v-if="anonymous(participant)"
                                :current="index === currentTurnIndex"
                                :active="participant.is_active !== false"
                            />
                            <CombatParticipantCard
                                v-else
                                :participant="participant"
                                :index="index"
                                :current="index === currentTurnIndex"
                                :draggable="isStoryteller"
                                :drop-target="dragOver === index && dragFrom !== index"
                                :manage="isStoryteller"
                                :busy="busy"
                                @drag-start="onDragStart"
                                @drag-over="onDragOver"
                                @drop="onDrop"
                                @drag-end="onDragEnd"
                                @toggle-active="setActive(participant, participant.is_active === false)"
                                @reveal="reveal(participant)"
                            />
                        </template>
                    </div>
                </template>
        </div>
    </FloatingWindow>
</template>

<script setup>
import { computed, onUnmounted, ref, toRef, watch } from 'vue';
import { charactersAvailableToAdd, isAnonymousParticipant } from '../../composables/combatTrackerOrder';
import { useCombatTracker } from '../../composables/useCombatTracker';
import CombatParticipantCard from './CombatParticipantCard.vue';
import CombatParticipantPlaceholder from './CombatParticipantPlaceholder.vue';
import FloatingWindow from './FloatingWindow.vue';

const props = defineProps({
    sceneId: { type: Number, default: null },
    sceneParticipants: { type: Array, default: () => [] },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['active']);

const sceneId = toRef(props, 'sceneId');
const {
    encounter,
    participants,
    currentTurnIndex,
    loading,
    busy,
    error,
    isStoryteller,
    startCombat,
    nextTurn,
    setActive,
    reveal,
    addParticipant,
    finishCombat,
    moveParticipant,
    rollInitiative,
} = useCombatTracker(sceneId);

const windowTitle = computed(() => (
    encounter.value ? `Бой · раунд ${encounter.value.current_round}` : 'Бой'
));

watch(encounter, (value, previous) => {
    emit('active', value != null);
    if (value != null && previous == null) {
        open.value = true;
    }
}, { immediate: true });

onUnmounted(() => {
    emit('active', false);
});

defineExpose({ startCombat });

const addOpen = ref(false);
const characterId = ref(null);
const addHidden = ref(false);
const dragFrom = ref(null);
const dragOver = ref(null);

const available = computed(() => charactersAvailableToAdd(props.sceneParticipants, participants.value));

function anonymous(participant) {
    return isAnonymousParticipant(participant);
}

function cardKey(participant, index) {
    if (participant.id != null) {
        return `p-${participant.id}`;
    }

    return `hidden-${participant.position ?? index}`;
}

async function submitAdd() {
    const id = characterId.value;
    if (id == null) {
        return;
    }

    const created = await addParticipant(id, addHidden.value);
    if (created) {
        characterId.value = null;
        addHidden.value = false;
        addOpen.value = false;
    }
}

function onDragStart(index) {
    dragFrom.value = index;
}

function onDragOver(index) {
    dragOver.value = index;
}

async function onDrop(index) {
    const from = dragFrom.value;
    dragFrom.value = null;
    dragOver.value = null;
    if (from == null || from === index) {
        return;
    }

    await moveParticipant(from, index);
}

function onDragEnd() {
    dragFrom.value = null;
    dragOver.value = null;
}
</script>

<style scoped>
.combat-tracker-toolbar,
.combat-tracker-add {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.6rem;
}

.combat-tracker-add select {
    margin: 0;
}

.combat-tracker-hidden {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin: 0;
    font-size: 0.8rem;
}

.combat-tracker-strip {
    display: flex;
    gap: 0.45rem;
    overflow-x: auto;
    padding-bottom: 0.2rem;
}
</style>
