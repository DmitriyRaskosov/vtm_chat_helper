<template>
    <section v-if="gameSession" class="card scene-toolbar">
        <div class="scene-heading">
            <div>
                <span class="muted">Игровая сессия</span>
                <strong>{{ gameSession.title }}</strong>
            </div>
            <label for="scene-select">
                Сцена
                <select
                    id="scene-select"
                    v-model.number="selectedSceneId"
                    @change="emit('switch')"
                >
                    <option v-for="scene in scenes" :key="scene.id" :value="scene.id">
                        {{ scene.position }}. {{ scene.title }} · {{ sceneStatusLabel(scene.status) }}
                    </option>
                </select>
            </label>
        </div>

        <p v-if="selectedScene?.description" class="muted scene-description">
            {{ selectedScene.description }}
        </p>
        <p v-if="sceneError" class="error">{{ sceneError }}</p>

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
                v-if="selectedScene?.status === 'draft'"
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
            <button
                v-if="extractorEnabled && selectedScene"
                type="button"
                class="secondary"
                :disabled="sceneLoading || extracting"
                @click="emit('extract')"
            >
                {{ extracting ? 'Разбор…' : 'Разобрать' }}
            </button>
            <router-link
                v-if="inboxCount > 0"
                to="/world?tab=inbox"
                class="inbox-badge"
            >
                {{ inboxCount }} {{ inboxCount === 1 ? 'окно ждёт' : 'окон ждут' }}
            </router-link>
        </div>

        <div v-if="isStoryteller" class="scene-cast">
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
    </section>
    <section v-else class="card session-empty">
        <template v-if="isStoryteller">
            <h2>Нет активной игровой сессии</h2>
            <p class="muted">
                Создайте игровую сессию. Начальная активная сцена будет добавлена автоматически.
            </p>
            <form class="session-create" @submit.prevent="emit('create-session')">
                <input
                    v-model="newGameSessionTitle"
                    type="text"
                    maxlength="120"
                    required
                    placeholder="Название игровой сессии"
                />
                <button
                    type="submit"
                    :disabled="sceneLoading || !newGameSessionTitle.trim()"
                >
                    {{ sceneLoading ? 'Создание…' : 'Создать сессию' }}
                </button>
            </form>
            <p v-if="sceneError" class="error">{{ sceneError }}</p>
        </template>
        <template v-else>
            <h2>Нет активной игровой сессии</h2>
            <p class="muted">Рассказчик ещё не открыл игровую сессию.</p>
        </template>
    </section>
</template>

<script setup>
import { characterTypeLabel, sceneStatusLabel } from '../../composables/useSceneSession';

defineProps({
    gameSession: { type: Object, default: null },
    isStoryteller: { type: Boolean, required: true },
    scenes: { type: Array, required: true },
    selectedScene: { type: Object, default: null },
    sceneLoading: { type: Boolean, required: true },
    sceneError: { type: String, required: true },
    canEditParticipants: { type: Boolean, required: true },
    currentParticipants: { type: Array, required: true },
    availableToAdd: { type: Array, required: true },
    participantBusy: { type: Boolean, required: true },
    participantError: { type: String, required: true },
    participantFlash: { type: String, required: true },
    extractorEnabled: { type: Boolean, default: false },
    extracting: { type: Boolean, default: false },
    inboxCount: { type: Number, default: 0 },
});

const selectedSceneId = defineModel('selectedSceneId');
const newSceneTitle = defineModel('newSceneTitle', { type: String });
const addCharacterId = defineModel('addCharacterId');
const newGameSessionTitle = defineModel('newGameSessionTitle', { type: String });

const emit = defineEmits([
    'switch',
    'create-scene',
    'activate',
    'close',
    'add',
    'remove',
    'create-session',
    'extract',
]);
</script>
