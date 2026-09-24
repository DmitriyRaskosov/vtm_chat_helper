<template>
    <div>
        <AppNav current="characters" />

        <p v-if="error" class="error">{{ error }}</p>

        <section v-if="auth.user.value?.is_storyteller" class="card character-create">
            <h2>Новый персонаж</h2>
            <div class="character-create-grid">
                <label>
                    Имя
                    <input v-model="form.canonical_name" type="text" maxlength="120" />
                </label>
                <label>
                    Тип
                    <select v-model="form.character_type">
                        <option value="player">Игрок</option>
                        <option value="npc">НПС</option>
                        <option value="ghoul">Гуль</option>
                    </select>
                </label>
                <label v-if="form.character_type === 'player'">
                    User ID владельца
                    <input v-model.number="form.user_id" type="number" min="1" />
                </label>
                <label v-if="form.character_type === 'ghoul'">
                    Домитор
                    <select v-model.number="form.domitor_character_id">
                        <option :value="null">—</option>
                        <option v-for="row in vampires" :key="row.id" :value="row.id">
                            {{ row.canonical_name }}
                        </option>
                    </select>
                </label>
            </div>
            <button type="button" :disabled="saving || !form.canonical_name.trim()" @click="createCharacter">
                Создать
            </button>
        </section>

        <section class="card">
            <h2>Листы</h2>
            <p v-if="!characters.length" class="muted">Пока нет персонажей.</p>
            <ul class="character-tree">
                <li v-for="row in characters" :key="row.id">
                    <div class="character-row">
                        <RouterLink :to="`/characters/${row.id}`">{{ row.canonical_name }}</RouterLink>
                        <span class="muted"> · {{ typeLabel(row.character_type) }}</span>
                        <button
                            v-if="auth.user.value?.is_storyteller"
                            class="link"
                            type="button"
                            @click="hideCharacter(row.id)"
                        >
                            Скрыть
                        </button>
                    </div>
                    <ul v-if="row.ghouls?.length">
                        <li v-for="ghoul in row.ghouls" :key="ghoul.id">
                            <div class="character-row">
                                <RouterLink :to="`/characters/${ghoul.id}`">{{ ghoul.canonical_name }}</RouterLink>
                                <span class="muted"> · гуль</span>
                                <button
                                    v-if="auth.user.value?.is_storyteller"
                                    class="link"
                                    type="button"
                                    @click="hideCharacter(ghoul.id)"
                                >
                                    Скрыть
                                </button>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>

        <section v-if="auth.user.value?.is_storyteller" class="card">
            <h2>Скрытые</h2>
            <p v-if="!archived.length" class="muted">Скрытых персонажей нет.</p>
            <ul v-else class="character-tree">
                <li v-for="row in archived" :key="row.id">
                    <div class="character-row">
                        <RouterLink :to="`/characters/${row.id}`">{{ row.canonical_name }}</RouterLink>
                        <span class="muted"> · {{ typeLabel(row.character_type) }}</span>
                        <button class="link" type="button" @click="restoreCharacter(row.id)">Вернуть</button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, useAuth } from '../auth';
import AppNav from '../components/layout/AppNav.vue';

const auth = useAuth();
const router = useRouter();
const characters = ref([]);
const archived = ref([]);
const error = ref('');
const saving = ref(false);
const form = reactive({
    canonical_name: '',
    character_type: 'npc',
    user_id: null,
    domitor_character_id: null,
});

const vampires = computed(() =>
    characters.value.filter((row) => row.character_type !== 'ghoul'),
);

function typeLabel(type) {
    if (type === 'player') {
        return 'игрок';
    }
    if (type === 'ghoul') {
        return 'гуль';
    }
    return 'НПС';
}

async function load() {
    error.value = '';
    try {
        const { data } = await api.get('/characters');
        characters.value = data.characters ?? [];
        archived.value = data.archived ?? [];
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить персонажей.';
    }
}

async function createCharacter() {
    saving.value = true;
    error.value = '';
    try {
        const payload = {
            canonical_name: form.canonical_name.trim(),
            character_type: form.character_type,
        };
        if (form.character_type === 'player' && form.user_id) {
            payload.user_id = form.user_id;
        }
        if (form.character_type === 'ghoul' && form.domitor_character_id) {
            payload.domitor_character_id = form.domitor_character_id;
        }
        const { data } = await api.post('/characters', payload);
        await router.push(`/characters/${data.character.id}`);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось создать персонажа.';
    } finally {
        saving.value = false;
    }
}

async function hideCharacter(id) {
    error.value = '';
    try {
        await api.post(`/characters/${id}/archive`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось скрыть персонажа.';
    }
}

async function restoreCharacter(id) {
    error.value = '';
    try {
        await api.post(`/characters/${id}/restore`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось вернуть персонажа.';
    }
}

onMounted(load);
</script>
