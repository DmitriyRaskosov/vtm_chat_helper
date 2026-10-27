<template>
    <div class="with-sidebar">
        <AppNav current="workshop" />
        <div class="page-main workshop-page">
            <h1 class="scene-title">Мастерская</h1>
            <div class="workshop-tabs" role="tablist">
                <button
                    type="button"
                    class="tab"
                    :class="{ active: tab === 'characters' }"
                    @click="tab = 'characters'"
                >
                    Персонажи
                </button>
                <button
                    type="button"
                    class="tab"
                    :class="{ active: tab === 'locations' }"
                    @click="tab = 'locations'"
                >
                    Локации
                </button>
                <button
                    type="button"
                    class="tab"
                    :class="{ active: tab === 'diaries' }"
                    @click="tab = 'diaries'"
                >
                    Дневники
                </button>
            </div>

            <section v-if="tab === 'characters'" class="workshop-panel">
                <p v-if="characterError" class="error">{{ characterError }}</p>
                <div class="sheet-actions">
                    <button type="button" @click="createOpen = !createOpen">Создать персонажа</button>
                </div>
                <section v-if="createOpen" class="card character-create">
                    <div class="character-create-grid">
                        <label>
                            Имя
                            <input v-model="form.canonical_name" type="text" maxlength="120" />
                        </label>
                        <label>
                            Тип
                            <select v-model="form.character_type">
                                <option value="npc">НПС</option>
                                <option value="player">Игрок</option>
                            </select>
                        </label>
                        <label v-if="form.character_type === 'player'">
                            User ID владельца
                            <input v-model.number="form.user_id" type="number" min="1" />
                        </label>
                    </div>
                    <button
                        type="button"
                        :disabled="characterSaving || !form.canonical_name.trim()"
                        @click="createCharacter"
                    >
                        {{ characterSaving ? 'Создание…' : 'Создать' }}
                    </button>
                </section>
                <p v-if="!characters.length && !archived.length" class="muted">Пока нет персонажей.</p>
                <ul v-else class="character-tree">
                    <li v-for="row in characters" :key="row.id">
                        <div class="character-row">
                            <strong>{{ row.canonical_name }}</strong>
                            <span class="muted"> · {{ characterTypeLabel(row.character_type) }}</span>
                            <RouterLink class="secondary workshop-edit-link" :to="characterLink(row.id)">
                                Редактировать
                            </RouterLink>
                        </div>
                    </li>
                    <li v-for="row in archived" :key="`archived-${row.id}`">
                        <div class="character-row">
                            <strong>{{ row.canonical_name }}</strong>
                            <span class="muted"> · скрыт · {{ characterTypeLabel(row.character_type) }}</span>
                            <RouterLink class="secondary workshop-edit-link" :to="characterLink(row.id)">
                                Редактировать
                            </RouterLink>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-else-if="tab === 'locations'" class="workshop-panel">
                <p v-if="locationError || locationActionError" class="error">{{ locationActionError || locationError }}</p>
                <p v-if="locationsLoading" class="muted">Загрузка…</p>
                <ul v-if="locations.length" class="location-library-list">
                    <li v-for="location in locations" :key="location.id" class="location-library-item">
                        <div class="location-library-preview">
                            <img
                                v-if="previews[location.id]"
                                :src="previews[location.id]"
                                alt=""
                                class="location-library-thumb"
                            />
                            <span v-else class="muted location-library-thumb-placeholder">нет фото</span>
                        </div>
                        <div class="location-library-body">
                            <strong>{{ location.name }}</strong>
                            <p v-if="location.description" class="muted location-library-snippet">
                                {{ location.description }}
                            </p>
                        </div>
                        <div class="location-library-actions">
                            <button type="button" class="secondary" @click="startEdit(location)">Редактировать</button>
                            <button type="button" class="secondary" @click="onDelete(location)">Удалить</button>
                        </div>
                    </li>
                </ul>
                <p v-else-if="!locationsLoading" class="muted">Пока нет сохранённых локаций.</p>

                <form class="card location-library-form" @submit.prevent="onSave">
                    <h2>{{ editingId ? 'Редактирование локации' : 'Новая локация' }}</h2>
                    <label>
                        Название
                        <input v-model="locationForm.name" type="text" maxlength="120" required />
                    </label>
                    <label>
                        Описание
                        <textarea v-model="locationForm.description" rows="3" maxlength="2000" />
                    </label>
                    <label>
                        Детали окружения
                        <textarea v-model="locationForm.environmentDetails" rows="3" maxlength="4000" />
                    </label>
                    <label class="scene-location-upload">
                        <span class="secondary-btn">Фото локации</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sheet-image-file-input"
                            @change="onImagePick"
                        />
                    </label>
                    <p v-if="pendingImageName" class="muted">Файл: {{ pendingImageName }}</p>
                    <div class="sheet-actions">
                        <button type="submit" :disabled="locationSaving || !locationForm.name.trim()">
                            {{ locationSaving ? 'Сохранение…' : (editingId ? 'Сохранить' : 'Создать локацию') }}
                        </button>
                        <button v-if="editingId" type="button" class="secondary" @click="resetLocationForm">
                            Отмена
                        </button>
                    </div>
                </form>
            </section>

            <DiaryManagement v-else :npcs="npcCharacters" />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import DiaryManagement from '../components/workshop/DiaryManagement.vue';
import AppNav from '../components/layout/AppNav.vue';
import { useCharacterList } from '../composables/useCharacterList';
import { useChronicleLocationImage } from '../composables/useChronicleLocationImage';
import { useChronicleLocations } from '../composables/useChronicleLocations';
import { apiErrorMessage } from '../utils/apiError';
import { characterTypeLabel } from '../utils/labels';

const route = useRoute();
const chronicleId = computed(() => route.params.chronicleId);
const tab = ref('characters');
const createOpen = ref(false);

const {
    characters,
    archived,
    error: characterError,
    saving: characterSaving,
    form,
    load: loadCharacters,
    createCharacter,
} = useCharacterList(chronicleId);

const npcCharacters = computed(() => {
    const rows = [...characters.value, ...archived.value];
    return rows.filter((row) => row.character_type === 'npc');
});

function characterLink(id) {
    return `/c/${chronicleId.value}/characters/${id}`;
}

const {
    locations,
    loading: locationsLoading,
    error: locationError,
    create: createLocation,
    update: updateLocation,
    remove: removeLocation,
    uploadImage,
} = useChronicleLocations(chronicleId);

const {
    previews,
    loadPreview,
    revokePreview,
} = useChronicleLocationImage();

const locationSaving = ref(false);
const locationActionError = ref('');
const editingId = ref(null);
const pendingImageFile = ref(null);
const pendingImageName = ref('');
const locationForm = reactive({
    name: '',
    description: '',
    environmentDetails: '',
});

function resetLocationForm() {
    editingId.value = null;
    locationForm.name = '';
    locationForm.description = '';
    locationForm.environmentDetails = '';
    pendingImageFile.value = null;
    pendingImageName.value = '';
}

function startEdit(location) {
    editingId.value = location.id;
    locationForm.name = location.name ?? '';
    locationForm.description = location.description ?? '';
    locationForm.environmentDetails = location.environment_details ?? '';
    pendingImageFile.value = null;
    pendingImageName.value = '';
    loadPreview(location.id);
}

function onImagePick(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    pendingImageFile.value = file ?? null;
    pendingImageName.value = file?.name ?? '';
}

async function onSave() {
    const name = locationForm.name.trim();
    if (!name) {
        return;
    }

    locationSaving.value = true;
    locationActionError.value = '';
    try {
        const payload = {
            name,
            description: locationForm.description.trim() || null,
            environment_details: locationForm.environmentDetails.trim() || null,
        };
        let locationId = editingId.value;
        if (locationId) {
            await updateLocation(locationId, payload);
        } else {
            const created = await createLocation(payload);
            locationId = created.id;
            editingId.value = locationId;
        }

        if (pendingImageFile.value && locationId) {
            await uploadImage(locationId, pendingImageFile.value);
            await loadPreview(locationId);
        }

        resetLocationForm();
    } catch (err) {
        locationActionError.value = apiErrorMessage(err, 'Не удалось сохранить локацию.');
    } finally {
        locationSaving.value = false;
    }
}

async function onDelete(location) {
    if (!window.confirm(`Удалить локацию «${location.name}»?`)) {
        return;
    }

    locationSaving.value = true;
    locationActionError.value = '';
    try {
        revokePreview(location.id);
        await removeLocation(location.id);
        if (Number(editingId.value) === Number(location.id)) {
            resetLocationForm();
        }
    } catch (err) {
        locationActionError.value = apiErrorMessage(err, 'Не удалось удалить локацию.');
    } finally {
        locationSaving.value = false;
    }
}

watch(locations, (items) => {
    for (const location of items) {
        if (location.image_path) {
            loadPreview(location.id);
        }
    }
});

onMounted(() => {
    loadCharacters();
});
</script>
