<template>
    <div>
        <div class="top">
            <h1>Game Chat</h1>
            <span class="muted">приглашение</span>
        </div>
        <div class="card">
            <p v-if="loading" class="muted">Принимаем приглашение…</p>
            <template v-else-if="errorMessage">
                <p class="error">{{ errorMessage }}</p>
                <p class="muted">
                    <RouterLink to="/chronicles">К списку хроник</RouterLink>
                </p>
            </template>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useCurrentChronicle } from '../composables/useCurrentChronicle';
import { useInvitation } from '../composables/useInvitation';
import { apiErrorMessage } from '../utils/apiError';

const ALREADY_MEMBER_MESSAGE = 'You are already a member of this chronicle.';

const route = useRoute();
const router = useRouter();
const { accept } = useInvitation();
const chronicle = useCurrentChronicle();

const loading = ref(true);
const errorMessage = ref('');

async function goToChronicleChat(chronicleId) {
    await chronicle.load();
    chronicle.select(chronicleId);

    if (!chronicle.currentId.value) {
        chronicle.syncFromRoute(chronicleId);
    }

    await router.push({
        name: 'chronicle-chat',
        params: { chronicleId: String(chronicleId) },
    });
}

function isAlreadyMemberConflict(err) {
    const status = err.response?.status;
    const message = err.response?.data?.message;

    return status === 409 && message === ALREADY_MEMBER_MESSAGE;
}

function chronicleIdFromError(err) {
    const raw = err.response?.data?.chronicle_id;

    if (raw === undefined || raw === null) {
        return null;
    }

    const id = Number(raw);

    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    return id;
}

async function runAccept() {
    const token = route.params.token;

    if (!token || typeof token !== 'string') {
        errorMessage.value = 'Приглашение не найдено.';
        loading.value = false;

        return;
    }

    try {
        const data = await accept(token);
        await goToChronicleChat(data.chronicle_id);
    } catch (err) {
        const status = err.response?.status;

        if (status === 404) {
            errorMessage.value = 'Приглашение не найдено.';
            loading.value = false;

            return;
        }

        if (isAlreadyMemberConflict(err)) {
            const chronicleId = chronicleIdFromError(err);

            if (chronicleId !== null) {
                await goToChronicleChat(chronicleId);

                return;
            }
        }

        if (status === 409) {
            errorMessage.value = apiErrorMessage(err, 'Приглашение недоступно.');
            loading.value = false;

            return;
        }

        errorMessage.value = apiErrorMessage(err, 'Не удалось принять приглашение.');
        loading.value = false;
    }
}

onMounted(() => {
    runAccept();
});
</script>
