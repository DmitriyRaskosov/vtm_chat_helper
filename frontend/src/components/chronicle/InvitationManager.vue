<template>
    <div class="diary-backdrop" @click.self="emit('close')">
        <div class="diary-modal card invitation-manager">
            <header class="diary-modal-header">
                <h2>Приглашения · {{ chronicle.title }}</h2>
                <button type="button" class="link" @click="emit('close')">Закрыть</button>
            </header>

            <form class="invitation-create" @submit.prevent="submitCreate">
                <label for="invitation-role">Роль</label>
                <select id="invitation-role" v-model="createRole">
                    <option value="player">Игрок</option>
                    <option value="storyteller">Рассказчик</option>
                </select>
                <button type="submit" :disabled="creating">
                    {{ creating ? 'Создаём…' : 'Создать ссылку' }}
                </button>
            </form>

            <div v-if="createdLink" class="invitation-created">
                <label for="invitation-new-link">Новая ссылка</label>
                <div class="invitation-link-row">
                    <input id="invitation-new-link" type="text" readonly :value="createdLink" />
                    <button type="button" @click="copyText(createdLink)">Скопировать</button>
                </div>
            </div>

            <p v-if="loading" class="muted">Загрузка…</p>
            <p v-if="error" class="error">{{ error }}</p>

            <section v-if="!loading && invitations.length" class="invitation-list">
                <h3>Активные приглашения</h3>
                <ul>
                    <li v-for="item in invitations" :key="item.id" class="invitation-list-row">
                        <div class="invitation-list-meta">
                            <span>{{ roleLabel(item.role) }}</span>
                            <span class="muted">до {{ formatExpires(item.expires_at) }}</span>
                            <span v-if="item.max_uses !== null" class="muted">
                                · лимит {{ item.max_uses }}
                            </span>
                        </div>
                        <div class="invitation-list-actions">
                            <button
                                type="button"
                                :disabled="!tokenById[item.id]"
                                :title="tokenById[item.id] ? '' : 'Ссылка доступна только сразу после создания'"
                                @click="copyInvitation(item)"
                            >
                                Скопировать
                            </button>
                            <button type="button" class="secondary" :disabled="revokingId === item.id" @click="revokeItem(item)">
                                Отозвать
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
            <p v-else-if="!loading" class="muted">Активных приглашений нет.</p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { invitationUrl, useChronicleInvitations } from '../../composables/useChronicleInvitations';

const props = defineProps({
    chronicle: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const { invitations, loading, error, load, create, revoke } = useChronicleInvitations();

const createRole = ref('player');
const creating = ref(false);
const createdLink = ref('');
const revokingId = ref(null);
const tokenById = reactive({});

function roleLabel(role) {
    if (role === 'storyteller') {
        return 'Рассказчик';
    }

    if (role === 'player') {
        return 'Игрок';
    }

    return role;
}

function formatExpires(iso) {
    return new Date(iso).toLocaleString('ru-RU', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

async function copyText(text) {
    await navigator.clipboard.writeText(text);
}

function copyInvitation(item) {
    const token = tokenById[item.id];

    if (!token) {
        return;
    }

    copyText(invitationUrl(token));
}

async function submitCreate() {
    creating.value = true;
    createdLink.value = '';

    try {
        const created = await create(props.chronicle.id, { role: createRole.value });
        createdLink.value = created.url;
        tokenById[created.id] = created.token;
    } catch {
        // error ref уже заполнен
    } finally {
        creating.value = false;
    }
}

async function revokeItem(item) {
    revokingId.value = item.id;

    try {
        await revoke(props.chronicle.id, item.id);
        delete tokenById[item.id];

        if (createdLink.value && !invitations.value.some((row) => row.id === item.id)) {
            createdLink.value = '';
        }
    } catch {
        // error ref уже заполнен
    } finally {
        revokingId.value = null;
    }
}

watch(
    () => props.chronicle.id,
    (id) => {
        if (id) {
            load(id);
        }
    },
    { immediate: true },
);
</script>

<style scoped>
.invitation-create {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.invitation-create label {
    width: 100%;
}

.invitation-link-row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.25rem;
}

.invitation-link-row input {
    flex: 1;
    min-width: 0;
}

.invitation-created {
    margin-bottom: 1rem;
}

.invitation-list ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.invitation-list-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--line);
}

.invitation-list-row:last-child {
    border-bottom: none;
}

.invitation-list-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.invitation-list-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
</style>
