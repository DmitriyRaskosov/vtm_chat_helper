<template>
    <div class="with-sidebar">
        <AppNav current="chronicles" />
        <div class="page-main">

        <p v-if="loading" class="muted">Загрузка хроник…</p>
        <p v-if="error" class="error">{{ error }}</p>

        <section class="card">
            <h2>Мои хроники</h2>
            <p v-if="!loading && !chronicles.length" class="muted">Хроник пока нет.</p>
            <ul v-if="chronicles.length" class="chronicle-list">
                <li v-for="item in chronicles" :key="item.id" class="chronicle-list-row">
                    <span>
                        <strong>{{ item.title }}</strong>
                        <span class="muted"> · {{ chronicleRoleLabel(item.role) }}</span>
                    </span>
                    <span class="chronicle-list-actions">
                        <button
                            v-if="item.role === 'storyteller'"
                            type="button"
                            class="secondary"
                            @click="openInvitationChronicleId = item.id"
                        >
                            Пригласить
                        </button>
                        <button
                            type="button"
                            :disabled="item.id === currentId"
                            @click="chooseChronicle(item.id)"
                        >
                            {{ item.id === currentId ? 'Выбрана' : 'Выбрать' }}
                        </button>
                    </span>
                </li>
            </ul>
        </section>

        <section v-if="isStoryteller" class="card">
            <h2>Новая хроника</h2>
            <form @submit.prevent="submitCreate">
                <label for="chronicle-title">Название</label>
                <input id="chronicle-title" v-model="form.title" type="text" required maxlength="120" />

                <label for="chronicle-description">Описание</label>
                <textarea id="chronicle-description" v-model="form.description" rows="3" />

                <label for="chronicle-setting">Сеттинг</label>
                <input id="chronicle-setting" v-model="form.setting" type="text" maxlength="120" />

                <p v-if="createError" class="error">{{ createError }}</p>

                <button type="submit" :disabled="creating || !form.title.trim()">Создать</button>
            </form>
        </section>

        <InvitationManager
            v-if="openInvitationChronicle"
            :chronicle="openInvitationChronicle"
            @close="openInvitationChronicleId = null"
        />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import InvitationManager from '../components/chronicle/InvitationManager.vue';
import AppNav from '../components/layout/AppNav.vue';
import { isStoryteller } from '../auth.js';
import { useCurrentChronicle } from '../composables/useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

const router = useRouter();
const { chronicles, currentId, loading, error, load, select, create } = useCurrentChronicle();

const form = reactive({
    title: '',
    description: '',
    setting: '',
});
const creating = ref(false);
const createError = ref('');
const openInvitationChronicleId = ref(null);

const openInvitationChronicle = computed(() => {
    if (openInvitationChronicleId.value === null) {
        return null;
    }

    return chronicles.value.find((item) => item.id === openInvitationChronicleId.value) ?? null;
});

function chronicleRoleLabel(role) {
    if (role === 'storyteller') {
        return 'рассказчик';
    }

    if (role === 'player') {
        return 'игрок';
    }

    return role;
}

function goToChatWithChronicle(chronicleId) {
    router.push({ name: 'chronicle-chat', params: { chronicleId: String(chronicleId) } });
}

function chooseChronicle(id) {
    select(id);
    goToChatWithChronicle(id);
}

async function submitCreate() {
    createError.value = '';
    creating.value = true;

    try {
        const chronicle = await create({
            title: form.title.trim(),
            description: form.description.trim(),
            setting: form.setting.trim(),
        });
        form.title = '';
        form.description = '';
        form.setting = '';
        goToChatWithChronicle(chronicle.id);
    } catch (err) {
        createError.value = apiErrorMessage(err, 'Не удалось создать хронику.');
    } finally {
        creating.value = false;
    }
}

onMounted(() => {
    load();
});
</script>

<style scoped>
.chronicle-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.chronicle-list-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--line);
}

.chronicle-list-row:last-child {
    border-bottom: none;
}

.chronicle-list-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
</style>
