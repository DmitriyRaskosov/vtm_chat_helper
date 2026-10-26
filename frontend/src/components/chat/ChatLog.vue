<template>
    <div class="chat-log-wrap" :class="`chat-log-wrap--${mode}`">
        <div ref="logEl" class="chat-log" aria-live="polite" @scroll="onScroll">
            <article
                v-for="message in visibleMessages"
                :key="message.id"
                :data-message-id="message.id"
                class="msg"
                :class="messageClasses(message)"
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
            <p v-if="!visibleMessages.length" class="muted">{{ emptyHint }}</p>
            <p v-if="isTyping && typingLabel" class="chat-typing-indicator muted">
                {{ typingLabel }} печатает…
            </p>
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
    mode: {
        type: String,
        required: true,
        validator: (value) => value === 'ic' || value === 'ooc',
    },
    isStoryteller: { type: Boolean, required: true },
    currentUserId: { type: Number, default: null },
    storytellerUserIds: { type: Array, default: () => [] },
    isTyping: { type: Boolean, default: false },
    typingLabel: { type: String, default: '' },
});

const autoScroll = defineModel('autoScroll', { type: Boolean, default: true });

const emit = defineEmits(['toggle-ooc', 'delete', 'restore', 'edit']);

const logEl = ref(null);
const editPanelEl = ref(null);
const editingId = ref(null);
const editBody = ref('');
const showScrollDown = ref(false);

const visibleMessages = computed(() => {
    if (props.mode === 'ooc') {
        return props.messages.filter((message) => message.is_ooc && !message.is_system);
    }

    return props.messages.filter((message) => !message.is_ooc || message.is_system);
});

const emptyHint = computed(() => (
    props.mode === 'ooc'
        ? 'Пока нет обсуждений вне сцены.'
        : 'Пока пусто. Напишите первое сообщение.'
));

const maxMessageId = computed(() => {
    let max = 0;
    for (const message of props.messages) {
        if (message.id > max) {
            max = message.id;
        }
    }
    return max;
});

function isStorytellerAuthor(message) {
    const userId = Number(message.user_id);

    if (!Number.isFinite(userId)) {
        return false;
    }

    return props.storytellerUserIds.some((id) => Number(id) === userId);
}

function messageClasses(message) {
    const classes = {
        'msg-deleted': message.is_deleted,
        'msg-system': message.is_system,
    };

    if (message.is_system) {
        return classes;
    }

    if (message.is_ooc) {
        classes['msg-ooc'] = true;

        return classes;
    }

    if (message.npc_name) {
        classes['msg-npc'] = true;

        return classes;
    }

    const userId = Number(message.user_id);
    const currentId = props.currentUserId == null ? null : Number(props.currentUserId);

    if (isStorytellerAuthor(message)) {
        classes['msg-storyteller'] = true;
    } else if (currentId !== null && userId === currentId) {
        classes['msg-player'] = true;
    }

    return classes;
}

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
    autoScroll.value = isNearBottom();
    showScrollDown.value = !autoScroll.value;
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
    autoScroll.value = true;
    showScrollDown.value = false;
}

watch(
    () => visibleMessages.value.length,
    async () => {
        if (!autoScroll.value) {
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
