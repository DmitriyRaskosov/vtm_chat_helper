<template>
    <div class="library-panel">
        <p v-if="loading" class="muted">Загрузка библиотеки…</p>
        <p v-else-if="error" class="error">{{ error }}</p>
        <p v-else-if="templates.length === 0" class="muted">В библиотеке пока нет шаблонов.</p>

        <div v-else class="profile-grid">
            <article
                v-for="row in templates"
                :key="row.id"
                class="card profile-card library-card"
            >
                <div class="profile-avatar" aria-hidden="true">
                    <span class="muted">Нет аватара</span>
                </div>
                <h3 class="profile-name">{{ row.name }}</h3>
                <p v-if="clanLabel(row)" class="muted">{{ clanLabel(row) }}</p>
                <p class="muted">{{ characterTypeLabel(row.character_type) }}</p>
                <div class="profile-actions">
                    <button type="button" class="secondary" @click="openImport(row)">
                        Импортировать в хронику
                    </button>
                    <button
                        type="button"
                        class="secondary"
                        :disabled="saving"
                        @click="askDelete(row)"
                    >
                        Удалить
                    </button>
                </div>
            </article>
        </div>

        <ImportCharacterModal
            v-model="importOpen"
            :template-id="importTarget?.id ?? null"
            :template-name="importTarget?.name ?? ''"
            :saving="saving"
            :error="importError"
            @confirm="onImportConfirm"
        />

        <div v-if="pendingDelete" class="diary-backdrop" @click.self="pendingDelete = null">
            <div class="diary-modal card">
                <h2>Удалить шаблон?</h2>
                <p>{{ pendingDelete.name }} будет удалён из библиотеки без возможности восстановления.</p>
                <div class="profile-confirm-actions">
                    <button type="button" :disabled="saving" @click="confirmDelete">Удалить</button>
                    <button type="button" class="secondary" @click="pendingDelete = null">Отмена</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import ImportCharacterModal from './ImportCharacterModal.vue';
import { api } from '../../auth';
import { characterTypeLabel } from '../../utils/labels';
import { apiErrorMessage } from '../../utils/apiError';

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    importHandler: {
        type: Function,
        required: true,
    },
    deleteHandler: {
        type: Function,
        required: true,
    },
});

const clanById = ref({});
const importOpen = ref(false);
const importTarget = ref(null);
const importError = ref('');
const pendingDelete = ref(null);

onMounted(() => {
    loadClans().catch(() => {});
});

async function loadClans() {
    try {
        const { data } = await api.get('/character-sheet/catalog');
        const clans = Array.isArray(data?.clans) ? data.clans : [];
        const map = {};

        for (const clan of clans) {
            map[Number(clan.id)] = clan.name;
        }

        clanById.value = map;
    } catch (err) {
        clanById.value = {};
        throw err;
    }
}

function clanLabel(row) {
    const id = Number(row?.clan_id);

    if (!Number.isFinite(id) || id <= 0) {
        return '';
    }

    return clanById.value[id] ?? `Клан #${id}`;
}

watch(importOpen, (visible) => {
    if (!visible) {
        importError.value = '';
    }
});

function openImport(row) {
    importTarget.value = row;
    importError.value = '';
    importOpen.value = true;
}

async function onImportConfirm(chronicleId, canonicalName) {
    if (!importTarget.value) {
        return;
    }

    importError.value = '';

    try {
        await props.importHandler(chronicleId, importTarget.value.id, canonicalName);
        importOpen.value = false;
        importTarget.value = null;
    } catch (err) {
        importError.value = apiErrorMessage(err, 'Не удалось импортировать.');
    }
}

function askDelete(row) {
    pendingDelete.value = row;
}

async function confirmDelete() {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await props.deleteHandler(pendingDelete.value.id);
        pendingDelete.value = null;
    } catch {
        // ошибка в composable
    }
}
</script>

<style scoped>
.library-card {
    cursor: default;
}

.library-panel .profile-avatar {
    cursor: default;
}
</style>
