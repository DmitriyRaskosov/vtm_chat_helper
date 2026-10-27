<template>
    <div class="diary-backdrop" @click.self="emit('close')">
        <div class="diary-modal card location-manager-modal">
            <header class="diary-modal-header">
                <h2>Библиотека локаций</h2>
                <button type="button" class="link" @click="emit('close')">Закрыть</button>
            </header>

            <p v-if="error" class="error">{{ error }}</p>
            <p v-if="loading" class="muted">Загрузка…</p>

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
                            <span class="location-library-snippet-label">Локация:</span>
                            {{ location.description }}
                        </p>
                        <p v-if="location.environment_details" class="muted location-library-snippet">
                            <span class="location-library-snippet-label">Окружение:</span>
                            {{ location.environment_details }}
                        </p>
                    </div>
                    <div class="location-library-actions">
                        <button type="button" @click="emit('apply', location.id)">Всё на сцену</button>
                        <button type="button" class="secondary" @click="emit('apply-situation', location.id)">
                            Локацию
                        </button>
                        <button type="button" class="secondary" @click="emit('apply-environment', location.id)">
                            Окружение
                        </button>
                        <button type="button" class="secondary" @click="startEdit(location)">Редактировать</button>
                        <button type="button" class="secondary danger" @click="onDelete(location)">Удалить</button>
                    </div>
                </li>
            </ul>
            <p v-else-if="!loading" class="muted">Пока нет сохранённых локаций.</p>

            <hr class="location-library-divider" />

            <h3>{{ editingId ? 'Редактирование' : 'Новая локация' }}</h3>
            <form class="location-library-form" @submit.prevent="onSubmit">
                <label>
                    Название
                    <input v-model="form.name" type="text" maxlength="120" required />
                </label>
                <label>
                    Описание (локация сцены)
                    <textarea v-model="form.description" rows="3" maxlength="2000" />
                </label>
                <label>
                    Детали окружения
                    <textarea v-model="form.environmentDetails" rows="3" maxlength="4000" />
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
                    <button type="submit" :disabled="saving || !form.name.trim()">
                        {{ saving ? 'Сохранение…' : (editingId ? 'Сохранить' : 'Создать') }}
                    </button>
                    <button v-if="editingId" type="button" class="secondary" @click="resetForm">Отмена</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    locations: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    previews: { type: Object, default: () => ({}) },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits([
    'close',
    'apply',
    'apply-situation',
    'apply-environment',
    'save',
    'delete',
    'load-preview',
]);

const editingId = ref(null);
const pendingImageFile = ref(null);
const pendingImageName = ref('');

const form = reactive({
    name: '',
    description: '',
    environmentDetails: '',
});

function resetForm() {
    editingId.value = null;
    form.name = '';
    form.description = '';
    form.environmentDetails = '';
    pendingImageFile.value = null;
    pendingImageName.value = '';
}

function startEdit(location) {
    editingId.value = location.id;
    form.name = location.name ?? '';
    form.description = location.description ?? '';
    form.environmentDetails = location.environment_details ?? '';
    pendingImageFile.value = null;
    pendingImageName.value = '';
    emit('load-preview', location.id);
}

function onImagePick(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    pendingImageFile.value = file ?? null;
    pendingImageName.value = file?.name ?? '';
}

function onSubmit() {
    emit('save', {
        id: editingId.value,
        name: form.name.trim(),
        description: form.description.trim() || null,
        environment_details: form.environmentDetails.trim() || null,
        imageFile: pendingImageFile.value,
        onDone: resetForm,
    });
}

async function onDelete(location) {
    if (!window.confirm(`Удалить локацию «${location.name}»?`)) {
        return;
    }
    emit('delete', location.id);
}

watch(
    () => props.locations,
    (items) => {
        for (const location of items) {
            if (location.image_path) {
                emit('load-preview', location.id);
            }
        }
    },
    { immediate: true },
);
</script>
