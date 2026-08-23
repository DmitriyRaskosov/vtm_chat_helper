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
                <p class="muted">Здесь позже появятся варианты реплик НПС: короткий промпт, три черновика, правка и отправка в чат от имени персонажа. Пока пусто — игроки эту панель не видят.</p>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, useAuth } from '../auth';

const router = useRouter();
const auth = useAuth();
const messages = ref([]);
const body = ref('');
const logEl = ref(null);
let timer;

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
