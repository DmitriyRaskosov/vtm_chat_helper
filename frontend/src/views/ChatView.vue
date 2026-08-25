<template>
    <div>
        <div class="top">
            <h1>Чат</h1>
            <span class="muted">
                {{ auth.user.value?.name }}
                <template v-if="auth.user.value?.is_storyteller"> · рассказчик</template>
                ·
                <button class="link" type="button" @click="logout">Выйти</button>
            </span>
        </div>

        <div :class="{ stage: auth.user.value?.is_storyteller }">
            <div>
                <div ref="logEl" class="chat-log" aria-live="polite">
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="msg"
                        :class="{ mine: message.mine }"
                    >
                        <div class="meta">{{ message.author }} · {{ message.created_at }}</div>
                        <div>{{ message.body }}</div>
                    </article>
                    <p v-if="!messages.length" class="muted">Пока пусто. Напишите первое сообщение.</p>
                </div>

                <form class="composer" @submit.prevent="send">
                    <textarea v-model="body" maxlength="4000" required placeholder="Сообщение…"></textarea>
                    <button type="submit">Отправить</button>
                </form>
            </div>

            <aside v-if="auth.user.value?.is_storyteller" class="card storyteller-panel">
                <h2>Панель рассказчика</h2>
                <p class="muted panel-hint">Черновики реплики НПС: промпт, три варианта, правка и отправка в чат.</p>

                <label for="npc-name">Имя НПС</label>
                <input id="npc-name" v-model="npcName" type="text" maxlength="64" placeholder="Виктория" />

                <label for="copilot-prompt">Ситуация / промпт</label>
                <textarea
                    id="copilot-prompt"
                    v-model="copilotPrompt"
                    maxlength="2000"
                    placeholder="Что происходит, тон, на что ответить…"
                />

                <button type="button" :disabled="copilotLoading || !canGenerate" @click="generateDrafts">
                    {{ copilotLoading ? 'Генерация…' : 'Сгенерировать черновики' }}
                </button>

                <p v-if="copilotError" class="error">{{ copilotError }}</p>

                <div v-if="drafts.length" class="draft-list">
                    <button
                        v-for="(draft, index) in drafts"
                        :key="index"
                        type="button"
                        class="draft-card"
                        :class="{ selected: selectedDraftIndex === index }"
                        @click="selectDraft(index)"
                    >
                        <span class="draft-label">Вариант {{ index + 1 }}</span>
                        <span class="draft-text">{{ draft }}</span>
                    </button>
                </div>

                <template v-if="selectedDraft !== null">
                    <label for="draft-edit">Редактирование</label>
                    <textarea id="draft-edit" v-model="editedDraft" maxlength="4000" />
                    <button type="button" :disabled="!canSendNpc" @click="sendAsNpc">Отправить в чат от НПС</button>
                </template>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, useAuth } from '../auth';

const router = useRouter();
const auth = useAuth();
const messages = ref([]);
const body = ref('');
const logEl = ref(null);
const npcName = ref('');
const copilotPrompt = ref('');
const drafts = ref([]);
const selectedDraftIndex = ref(null);
const editedDraft = ref('');
const copilotLoading = ref(false);
const copilotError = ref('');
let timer;

const canGenerate = computed(() => npcName.value.trim() !== '' && copilotPrompt.value.trim() !== '');
const selectedDraft = computed(() =>
    selectedDraftIndex.value === null ? null : drafts.value[selectedDraftIndex.value] ?? null,
);
const canSendNpc = computed(() => npcName.value.trim() !== '' && editedDraft.value.trim() !== '');

function lastId() {
    return messages.value.at(-1)?.id ?? 0;
}

function merge(incoming) {
    for (const message of incoming) {
        if (!messages.value.some((item) => item.id === message.id)) {
            messages.value.push(message);
        }
    }
}

async function scrollDown() {
    await nextTick();
    if (logEl.value) {
        logEl.value.scrollTop = logEl.value.scrollHeight;
    }
}

async function load(afterId = 0) {
    const { data } = await api.get('/messages', { params: { after_id: afterId } });
    merge(data.messages);
    await scrollDown();
}

async function send() {
    const text = body.value.trim();
    if (!text) {
        return;
    }
    const { data } = await api.post('/messages', { body: text });
    merge([data.message]);
    body.value = '';
    await scrollDown();
}

function selectDraft(index) {
    selectedDraftIndex.value = index;
    editedDraft.value = drafts.value[index] ?? '';
}

async function generateDrafts() {
    copilotError.value = '';
    copilotLoading.value = true;
    drafts.value = [];
    selectedDraftIndex.value = null;
    editedDraft.value = '';

    try {
        const { data } = await api.post('/copilot/drafts', {
            npc_name: npcName.value.trim(),
            prompt: copilotPrompt.value.trim(),
        });
        drafts.value = data.drafts ?? [];
        if (drafts.value.length > 0) {
            selectDraft(0);
        }
    } catch (error) {
        copilotError.value =
            error.response?.data?.message ?? 'Не удалось сгенерировать черновики. Проверьте Ollama.';
    } finally {
        copilotLoading.value = false;
    }
}

async function sendAsNpc() {
    const text = editedDraft.value.trim();
    const name = npcName.value.trim();
    if (!text || !name) {
        return;
    }

    const { data } = await api.post('/messages', {
        body: text,
        npc_name: name,
    });
    merge([data.message]);
    await scrollDown();
}

async function logout() {
    await auth.logout();
    await router.push('/login');
}

onMounted(async () => {
    await load();
    timer = setInterval(() => load(lastId()), 3000);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>
