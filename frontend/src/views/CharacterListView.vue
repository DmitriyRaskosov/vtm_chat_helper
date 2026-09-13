<template>
    <div>
        <div class="top">
            <h1>
                <RouterLink to="/chat">Чат</RouterLink>
                <span class="nav-sep">·</span>
                <RouterLink to="/characters" class="nav-current">Персонажи</RouterLink>
            </h1>
            <span class="muted">
                {{ auth.user.value?.name }}
                <template v-if="auth.user.value?.is_storyteller"> · рассказчик</template>
                ·
                <button class="link" type="button" @click="logout">Выйти</button>
            </span>
        </div>

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
                    <RouterLink :to="`/characters/${row.id}`">{{ row.canonical_name }}</RouterLink>
                    <span class="muted"> · {{ typeLabel(row.character_type) }}</span>
                    <ul v-if="row.ghouls?.length">
                        <li v-for="ghoul in row.ghouls" :key="ghoul.id">
                            <RouterLink :to="`/characters/${ghoul.id}`">{{ ghoul.canonical_name }}</RouterLink>
                            <span class="muted"> · гуль</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, useAuth } from '../auth';

const auth = useAuth();
const router = useRouter();
const characters = ref([]);
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

async function logout() {
    await auth.logout();
    await router.push('/login');
}

onMounted(load);
</script>
