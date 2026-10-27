<template>
    <div class="scene-manage">
        <p v-if="selectedScene" class="muted">
            {{ selectedScene.title }}
            <template v-if="selectedScene.status"> · {{ sceneStatusLabel(selectedScene.status) }}</template>
        </p>

        <div v-if="isStoryteller" class="scene-management">
            <input
                v-model="newSceneTitle"
                type="text"
                maxlength="120"
                placeholder="Название новой сцены"
            />
            <button
                type="button"
                :disabled="sceneLoading || !newSceneTitle.trim()"
                @click="emit('create-scene')"
            >
                Создать и открыть
            </button>
            <button
                v-if="selectedScene?.status === 'draft' || selectedScene?.status === 'closed'"
                type="button"
                class="secondary"
                :disabled="sceneLoading"
                @click="emit('activate')"
            >
                Сделать активной
            </button>
            <button
                v-if="selectedScene?.status === 'active'"
                type="button"
                class="secondary"
                :disabled="sceneLoading"
                @click="emit('close')"
            >
                Закрыть сцену
            </button>
        </div>
        <p v-if="sceneError" class="error">{{ sceneError }}</p>

        <div v-if="isStoryteller && selectedScene?.status === 'active'" class="scene-turn">
            <label for="scene-turn-character">Чей ход (игрок)</label>
            <select
                id="scene-turn-character"
                v-model="turnCharacterId"
                :disabled="turnBusy || !playerParticipants.length"
            >
                <option :value="null">Никого</option>
                <option
                    v-for="row in playerParticipants"
                    :key="row.character_id"
                    :value="row.character_id"
                >
                    {{ row.character_name }}
                </option>
            </select>
            <button type="button" :disabled="turnBusy" @click="emit('pass-turn', turnCharacterId)">
                Передать ход
            </button>
        </div>

        <div class="scene-cast">
            <div>
                <span class="muted">На сцене</span>
                <ul v-if="currentParticipants.length" class="scene-cast-list">
                    <li v-for="row in currentParticipants" :key="row.character_id">
                        {{ row.character_name }}
                        <span class="muted"> · {{ characterTypeLabel(row.character_type) }}</span>
                        <button
                            v-if="canEditParticipants"
                            type="button"
                            class="link"
                            :disabled="participantBusy"
                            @click="emit('remove', row.character_id)"
                        >
                            убрать
                        </button>
                    </li>
                </ul>
                <p v-else class="muted">Пока никого. Добавьте персонажа из списка ниже.</p>
            </div>
            <div v-if="canEditParticipants" class="scene-cast-add">
                <label for="scene-participant-event">Что происходит?</label>
                <input
                    id="scene-participant-event"
                    v-model="participantSystemMessage"
                    type="text"
                    maxlength="500"
                    placeholder="Необязательно — иначе стандартная фраза в чат"
                />
                <label for="scene-add-character">Добавить на сцену</label>
                <select
                    id="scene-add-character"
                    v-model.number="addCharacterId"
                    :disabled="participantBusy || !availableToAdd.length"
                >
                    <option :value="null">{{ availableToAdd.length ? 'Выберите персонажа' : 'Все уже на сцене' }}</option>
                    <option
                        v-for="row in availableToAdd"
                        :key="row.id"
                        :value="row.id"
                    >
                        {{ row.canonical_name }} · {{ characterTypeLabel(row.character_type) }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="participantBusy || !addCharacterId"
                    @click="emit('add')"
                >
                    Добавить
                </button>
                <span v-if="participantFlash" class="saved-flash">{{ participantFlash }}</span>
            </div>
            <p v-if="participantError" class="error">{{ participantError }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { sceneStatusLabel } from '../../composables/useSceneSession';
import { characterTypeLabel } from '../../utils/labels';

const props = defineProps({
    isStoryteller: { type: Boolean, required: true },
    selectedScene: { type: Object, default: null },
    sceneLoading: { type: Boolean, required: true },
    sceneError: { type: String, required: true },
    canEditParticipants: { type: Boolean, required: true },
    currentParticipants: { type: Array, required: true },
    availableToAdd: { type: Array, required: true },
    participantBusy: { type: Boolean, required: true },
    participantError: { type: String, required: true },
    participantFlash: { type: String, required: true },
    activeCharacterId: { type: Number, default: null },
    turnBusy: { type: Boolean, default: false },
});

const newSceneTitle = defineModel('newSceneTitle', { type: String });
const addCharacterId = defineModel('addCharacterId');
const participantSystemMessage = defineModel('participantSystemMessage', { type: String, required: true });

const emit = defineEmits(['create-scene', 'activate', 'close', 'add', 'remove', 'pass-turn']);

const turnCharacterId = ref(props.activeCharacterId);

const playerParticipants = computed(() =>
    props.currentParticipants.filter((row) => row.character_type === 'player'),
);

watch(
    () => props.activeCharacterId,
    (value) => {
        const isPlayerOnScene = value == null
            || playerParticipants.value.some((row) => Number(row.character_id) === Number(value));
        turnCharacterId.value = isPlayerOnScene ? value : null;
    },
);
</script>
