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
        <div v-if="selectedScene" class="scene-location-image">
            <button
                v-if="locationImageUrl"
                type="button"
                class="scene-location-thumb-btn"
                title="Открыть фото локации"
                @click="emit('zoom-location')"
            >
                <img :src="locationImageUrl" alt="Фото локации" class="scene-location-thumb" />
            </button>
            <label v-if="isStoryteller" class="scene-location-upload">
                <span class="secondary-btn">Загрузить фото</span>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sheet-image-file-input"
                    :disabled="locationImageLoading || selectedScene?.status === 'closed'"
                    @change="onLocationImagePick"
                />
            </label>
            <p v-if="locationImageError" class="error">{{ locationImageError }}</p>
        </div>

        <div v-if="isStoryteller" class="scene-location">
            <div class="scene-field-block">
                <label for="scene-situation">
                    Локация сцены
                    <textarea
                        id="scene-situation"
                        v-model="sceneSituation"
                        maxlength="2000"
                        rows="3"
                        :disabled="selectedScene?.status === 'closed'"
                        placeholder="что персонажи могут воспринять и чем располагают — темнота, что видно, что под рукой, чего они не знают."
                    />
                </label>
                <SceneTemplateToolsPanel
                    v-if="selectedScene?.status !== 'closed'"
                    v-model:apply-template-id="situationApplyTemplateId"
                    v-model:update-template-id="situationUpdateTemplateId"
                    focus="situation"
                    :locations="locations"
                    :location-saving="locationSaving"
                    :scene-situation-saving="sceneSituationSaving"
                    hint="Общая библиотека шаблонов: локация, окружение и фото в одной записи."
                    @open-library="libraryOpen = true"
                    @save-new="onSaveCurrentAsTemplate"
                    @apply-full="onApplyLocation"
                    @apply-situation="(id) => applySituationFromTemplate(id, true)"
                    @apply-environment="(id) => applyEnvironmentFromTemplate(id, true)"
                    @update-both="(id) => updateTemplateFromScene('both', id)"
                    @update-situation="(id) => updateTemplateFromScene('situation', id)"
                    @update-environment="(id) => updateTemplateFromScene('environment', id)"
                />
            </div>
            <div class="scene-field-block">
                <label for="scene-environment">
                    Детали окружения
                    <textarea
                        id="scene-environment"
                        v-model="environmentDetails"
                        maxlength="4000"
                        rows="3"
                        :disabled="selectedScene?.status === 'closed'"
                        placeholder="запахи, звуки, освещение, препятствия — для генератора событий и заметок мастера."
                    />
                </label>
                <SceneTemplateToolsPanel
                    v-if="selectedScene?.status !== 'closed'"
                    v-model:apply-template-id="environmentApplyTemplateId"
                    v-model:update-template-id="environmentUpdateTemplateId"
                    focus="environment"
                    :locations="locations"
                    :location-saving="locationSaving"
                    :scene-situation-saving="sceneSituationSaving"
                    hint="Тот же список шаблонов: можно подставить или сохранить только окружение, не трогая локацию."
                    @open-library="libraryOpen = true"
                    @save-new="onSaveCurrentAsTemplate"
                    @apply-full="onApplyLocation"
                    @apply-situation="(id) => applySituationFromTemplate(id, true)"
                    @apply-environment="(id) => applyEnvironmentFromTemplate(id, true)"
                    @update-both="(id) => updateTemplateFromScene('both', id)"
                    @update-situation="(id) => updateTemplateFromScene('situation', id)"
                    @update-environment="(id) => updateTemplateFromScene('environment', id)"
                />
            </div>
            <div
                v-if="selectedScene?.status !== 'closed'"
                class="scene-scene-save-row sheet-actions"
            >
                <button
                    type="button"
                    :disabled="sceneSituationSaving"
                    @click="emit('save-situation')"
                >
                    Сохранить на сцене
                </button>
            </div>
            <p v-if="sceneSituationError" class="error">{{ sceneSituationError }}</p>
            <p v-if="locationsError" class="error">{{ locationsError }}</p>
        </div>
        <p v-else-if="sceneSituation" class="muted scene-description">{{ sceneSituation }}</p>
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
                        <span
                            v-if="row.character_description"
                            class="muted scene-cast-description"
                            :title="row.character_description"
                        > · {{ row.character_description }}</span>
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
        <LocationManagerModal
            v-if="libraryOpen"
            :locations="locations"
            :loading="locationsLoading"
            :error="locationsError"
            :previews="locationPreviews"
            :saving="locationSaving"
            @close="libraryOpen = false"
            @apply="onApplyFromLibrary"
            @apply-situation="onApplySituationFromLibrary"
            @apply-environment="onApplyEnvironmentFromLibrary"
            @save="onLibrarySave"
            @delete="onLibraryDelete"
            @load-preview="loadLocationPreview"
        />

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
import { computed, ref, toRef, watch } from 'vue';
import { sceneStatusLabel } from '../../composables/useSceneSession';
import { useChronicleLocationImage } from '../../composables/useChronicleLocationImage';
import { useChronicleLocations } from '../../composables/useChronicleLocations';
import { characterTypeLabel } from '../../utils/labels';
import LocationManagerModal from './LocationManagerModal.vue';
import SceneTemplateToolsPanel from './SceneTemplateToolsPanel.vue';

const props = defineProps({
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
    sceneSituationSaving: { type: Boolean, required: true },
    sceneSituationError: { type: String, required: true },
    chronicleId: { type: [Number, String], default: null },
    sceneContextRevision: { type: Number, default: 0 },
    activeCharacterId: { type: Number, default: null },
    turnBusy: { type: Boolean, default: false },
    locationImageUrl: { default: null },
    locationImageLoading: { type: Boolean, default: false },
    locationImageError: { type: String, default: '' },
});

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

const participantSystemMessage = defineModel('participantSystemMessage', { type: String, required: true });

const selectedSceneId = defineModel('selectedSceneId');
const sceneSituation = defineModel('sceneSituation', { type: String });
const environmentDetails = defineModel('environmentDetails', { type: String, default: '' });

const libraryOpen = ref(false);
const situationApplyTemplateId = ref(null);
const situationUpdateTemplateId = ref(null);
const environmentApplyTemplateId = ref(null);
const environmentUpdateTemplateId = ref(null);
const locationSaving = ref(false);

function findTemplateLocation(locationId) {
    return locations.value.find((item) => Number(item.id) === Number(locationId)) ?? null;
}

const chronicleIdRef = toRef(props, 'chronicleId');

const {
    locations,
    loading: locationsLoading,
    error: locationsError,
    create: createLocation,
    update: updateLocation,
    remove: removeLocation,
    uploadImage: uploadLocationTemplateImage,
    applyTemplate,
} = useChronicleLocations(chronicleIdRef);

const {
    previews: locationPreviews,
    loadPreview: loadLocationPreview,
    revokePreview,
} = useChronicleLocationImage();

function onLocationImagePick(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file || selectedSceneId.value == null) {
        return;
    }

    emit('upload-location-image', file);
}
const newSceneTitle = defineModel('newSceneTitle', { type: String });
const addCharacterId = defineModel('addCharacterId');
const newGameSessionTitle = defineModel('newGameSessionTitle', { type: String });

async function onApplyLocation(locationId) {
    const sceneId = selectedSceneId.value;
    if (!sceneId || locationId == null) {
        return;
    }

    locationSaving.value = true;
    try {
        const context = await applyTemplate(sceneId, locationId, props.sceneContextRevision);
        if (!context) {
            return;
        }

        sceneSituation.value = context.situation ?? '';
        environmentDetails.value = context.environment_details ?? '';
        emit('location-applied', context);
    } finally {
        locationSaving.value = false;
    }
}

async function onApplyFromLibrary(locationId) {
    await onApplyLocation(locationId);
    libraryOpen.value = false;
}

function onApplySituationFromLibrary(locationId) {
    applySituationFromTemplate(locationId, true);
    libraryOpen.value = false;
}

function onApplyEnvironmentFromLibrary(locationId) {
    applyEnvironmentFromTemplate(locationId, true);
    libraryOpen.value = false;
}

function applySituationFromTemplate(locationId, persistToScene = false) {
    const location = findTemplateLocation(locationId);
    if (!location) {
        return;
    }

    sceneSituation.value = location.description ?? '';
    if (persistToScene) {
        emit('save-situation');
    }
}

function applyEnvironmentFromTemplate(locationId, persistToScene = false) {
    const location = findTemplateLocation(locationId);
    if (!location) {
        return;
    }

    environmentDetails.value = location.environment_details ?? '';
    if (persistToScene) {
        emit('save-situation');
    }
}

async function updateTemplateFromScene(scope, locationId) {
    if (locationId == null || !Number.isFinite(Number(locationId))) {
        return;
    }

    const payload = {};
    if (scope === 'both' || scope === 'situation') {
        payload.description = sceneSituation.value.trim() || null;
    }
    if (scope === 'both' || scope === 'environment') {
        payload.environment_details = environmentDetails.value.trim() || null;
    }

    locationSaving.value = true;
    try {
        await updateLocation(locationId, payload);
    } finally {
        locationSaving.value = false;
    }
}

async function onSaveCurrentAsTemplate() {
    const sceneId = selectedSceneId.value;
    const name = window.prompt('Название шаблона локации');
    if (!name?.trim() || sceneId == null) {
        return;
    }

    locationSaving.value = true;
    try {
        await createLocation({
            name: name.trim(),
            description: sceneSituation.value,
            environment_details: environmentDetails.value,
            scene_id: sceneId,
        });
    } finally {
        locationSaving.value = false;
    }
}

async function onLibrarySave(payload) {
    locationSaving.value = true;
    try {
        let locationId = payload.id;
        if (locationId) {
            await updateLocation(locationId, {
                name: payload.name,
                description: payload.description,
                environment_details: payload.environment_details,
            });
        } else {
            const created = await createLocation({
                name: payload.name,
                description: payload.description,
                environment_details: payload.environment_details,
            });
            locationId = created.id;
        }

        if (payload.imageFile && locationId) {
            await uploadLocationTemplateImage(locationId, payload.imageFile);
            await loadLocationPreview(locationId);
        }

        payload.onDone?.();
    } finally {
        locationSaving.value = false;
    }
}

async function onLibraryDelete(locationId) {
    locationSaving.value = true;
    try {
        revokePreview(locationId);
        await removeLocation(locationId);
    } finally {
        locationSaving.value = false;
    }
}

const emit = defineEmits([
    'switch',
    'create-scene',
    'activate',
    'close',
    'add',
    'remove',
    'create-session',
    'save-situation',
    'pass-turn',
    'location-applied',
    'upload-location-image',
    'zoom-location',
]);
</script>
