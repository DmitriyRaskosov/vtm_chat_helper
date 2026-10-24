<template>
    <div class="chat-log-wrap">
        <div ref="logEl" class="chat-log" aria-live="polite" @scroll="onScroll">
            <article
                v-for="message in messages"
                :key="message.id"
                :data-message-id="message.id"
                class="msg"
                :class="{
                    mine: message.mine,
                    'msg-ooc': message.is_ooc,
                    'msg-deleted': message.is_deleted,
                    'msg-system': message.is_system,
                }"
            >
                <template v-if="message.is_system">
                    <div class="msg-system-body">{{ message.body }}</div>
                </template>
                <template v-else>
                    <div class="meta">
                        <span v-if="message.is_ooc" class="ooc-badge">OOC</span>
                        <span v-if="message.is_deleted" class="deleted-badge">УДАЛЕНО</span>
                        {{ authorLabel(message) }} · {{ message.created_at }}
                    </div>
                    <div v-if="editingId !== message.id" class="msg-body">{{ message.body }}</div>
                    <div v-else ref="editPanelEl" class="msg-edit">
                        <textarea v-model="editBody" rows="3" maxlength="10000" />
                        <div class="msg-actions">
                            <button type="button" class="msg-action" @click="submitEdit(message)">Сохранить</button>
                            <button type="button" class="msg-action" @click="cancelEdit">Отмена</button>
                        </div>
                    </div>

                    <div v-if="canManage(message) && editingId !== message.id" class="msg-actions">
                        <template v-if="!message.is_deleted">
                            <button
                                v-if="canEdit(message)"
                                type="button"
                                class="msg-action"
                                @click="startEdit(message)"
                            >
                                Редактировать
                            </button>
                            <button type="button" class="msg-action" @click="emit('toggle-ooc', message)">
                                {{ message.is_ooc ? '→ In Character' : '→ Out Of Character' }}
                            </button>
                            <button type="button" class="msg-action danger" @click="emit('delete', message)">
                                Удалить
                            </button>
                        </template>
                        <template v-else>
                            <button type="button" class="msg-action" @click="emit('restore', message)">
                                Восстановить
                            </button>
                        </template>
                    </div>
                </template>
            </article>
            <p v-if="!messages.length" class="muted">Пока пусто. Напишите первое сообщение.</p>
        </div>
        <button
            v-if="showScrollDown"
            type="button"
            class="chat-log-scroll-down"
            title="Вниз"
            @click="scrollToBottom"
        >
            ↓
        </button>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    messages: { type: Array, required: true },
    isStoryteller: { type: Boolean, required: true },
});

const emit = defineEmits(['toggle-ooc', 'delete', 'restore', 'edit']);

const logEl = ref(null);
const editPanelEl = ref(null);
const editingId = ref(null);
const editBody = ref('');
const showScrollDown = ref(false);
const stickToBottom = ref(true);

const maxMessageId = computed(() => {
    let max = 0;
    for (const message of props.messages) {
        if (message.id > max) {
            max = message.id;
        }
    }
    return max;
});

function canManage(message) {
    if (message.is_system) {
        return false;
    }

    return props.isStoryteller || message.mine;
}

function canEdit(message) {
    if (message.is_system || message.is_deleted) {
        return false;
    }

    if (props.isStoryteller) {
        return true;
    }

    if (!message.mine) {
        return false;
    }

    return message.id > maxMessageId.value - 10;
}

function authorLabel(message) {
    if (message.is_ooc) {
        return message.user_name ?? message.author;
    }

    return message.author;
}

function isNearBottom() {
    const el = logEl.value;
    if (!el) {
        return true;
    }

    return el.scrollHeight - el.scrollTop - el.clientHeight < 40;
}

function onScroll() {
    stickToBottom.value = isNearBottom();
    showScrollDown.value = !stickToBottom.value;
}

async function startEdit(message) {
    editingId.value = message.id;
    editBody.value = message.body;
    await nextTick();
    const article = logEl.value?.querySelector(`[data-message-id="${message.id}"]`);
    article?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    await nextTick();
    editPanelEl.value?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function cancelEdit() {
    editingId.value = null;
    editBody.value = '';
}

function submitEdit(message) {
    const body = editBody.value.trim();
    if (!body || body === message.body) {
        cancelEdit();
        return;
    }

    emit('edit', { message, body });
    cancelEdit();
}

async function scrollToBottom() {
    const el = logEl.value;
    if (!el) {
        return;
    }

    el.scrollTop = el.scrollHeight;
    stickToBottom.value = true;
    showScrollDown.value = false;
}

watch(
    () => props.messages.length,
    async () => {
        if (!stickToBottom.value) {
            showScrollDown.value = true;
            return;
        }

        await nextTick();
        await scrollToBottom();
    },
);

defineExpose({
    isAtBottom() {
        return isNearBottom();
    },
    scrollToBottom,
});
</script>
