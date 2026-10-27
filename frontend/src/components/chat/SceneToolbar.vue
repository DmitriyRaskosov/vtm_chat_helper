<template>
    <section v-if="gameSession" class="card scene-toolbar">
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
            <div class="scene-location-cards">
                <section class="card scene-location-card">
                    <h3>Описание локации (для игроков и Копилота)</h3>
                    <label for="scene-situation">
                        Текст на баннере
                        <textarea
                            id="scene-situation"
                            v-model="sceneSituation"
                            maxlength="2000"
                            rows="4"
                            :disabled="selectedScene?.status === 'closed'"
                            placeholder="что персонажи могут воспринять и чем располагают — темнота, что видно, что под рукой, чего они не знают."
                        />
                    </label>
                </section>
                <section class="card scene-location-card">
                    <h3>Детали локации (для Генератора событий)</h3>
                    <label for="scene-environment">
                        Физика места
                        <textarea
                            id="scene-environment"
                            v-model="environmentDetails"
                            maxlength="4000"
                            rows="4"
                            :disabled="selectedScene?.status === 'closed'"
                            placeholder="пол, высота, препятствия, материалы — только то, обо что можно удариться."
                        />
                    </label>
                </section>
            </div>
            <SceneTemplateToolsPanel
                v-if="selectedScene?.status !== 'closed'"
                class="scene-template-block"
                v-model:apply-template-id="situationApplyTemplateId"
                v-model:update-template-id="situationUpdateTemplateId"
                focus="situation"
                :locations="locations"
                :location-saving="locationSaving"
                :scene-situation-saving="sceneSituationSaving"
                hint="Шаблоны отдельно от полей. Одна запись хранит описание, детали и фото."
                @open-library="libraryOpen = true"
                @save-new="onSaveCurrentAsTemplate"
                @apply-full="onApplyLocation"
                @apply-situation="(id) => applySituationFromTemplate(id, true)"
                @apply-environment="(id) => applyEnvironmentFromTemplate(id, true)"
                @update-both="(id) => updateTemplateFromScene('both', id)"
                @update-situation="(id) => updateTemplateFromScene('situation', id)"
                @update-environment="(id) => updateTemplateFromScene('environment', id)"
            />
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
import { ref, toRef } from 'vue';
import { useChronicleLocationImage } from '../../composables/useChronicleLocationImage';
import { useChronicleLocations } from '../../composables/useChronicleLocations';
import LocationManagerModal from './LocationManagerModal.vue';
import SceneTemplateToolsPanel from './SceneTemplateToolsPanel.vue';

const props = defineProps({
    gameSession: { type: Object, default: null },
    isStoryteller: { type: Boolean, required: true },
    selectedScene: { type: Object, default: null },
    sceneLoading: { type: Boolean, required: true },
    sceneError: { type: String, required: true },
    sceneSituationSaving: { type: Boolean, required: true },
    sceneSituationError: { type: String, required: true },
    chronicleId: { type: [Number, String], default: null },
    sceneContextRevision: { type: Number, default: 0 },
    locationImageUrl: { default: null },
    locationImageLoading: { type: Boolean, default: false },
    locationImageError: { type: String, default: '' },
});

const selectedSceneId = defineModel('selectedSceneId');
const sceneSituation = defineModel('sceneSituation', { type: String });
const environmentDetails = defineModel('environmentDetails', { type: String, default: '' });

const libraryOpen = ref(false);
const situationApplyTemplateId = ref(null);
const situationUpdateTemplateId = ref(null);
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
    'create-session',
    'save-situation',
    'location-applied',
    'upload-location-image',
    'zoom-location',
]);
</script>
