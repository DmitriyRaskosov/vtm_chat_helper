<template>
    <div ref="logEl" class="chat-log" aria-live="polite">
        <article
            v-for="message in messages"
            :key="message.id"
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
                <div v-else class="msg-edit">
                    <textarea v-model="editBody" rows="3" maxlength="10000" />
                    <div class="msg-actions">
                        <button type="button" class="msg-action" @click="submitEdit(message)">Сохранить</button>
                        <button type="button" class="msg-action" @click="cancelEdit">Отмена</button>
                    </div>
                </div>

                <div v-if="canManage(message)" class="msg-actions">
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
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    messages: { type: Array, required: true },
    isStoryteller: { type: Boolean, required: true },
});

const emit = defineEmits(['toggle-ooc', 'delete', 'restore', 'edit']);

const logEl = ref(null);
const editingId = ref(null);
const editBody = ref('');

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

function startEdit(message) {
    editingId.value = message.id;
    editBody.value = message.body;
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

defineExpose({
    isAtBottom() {
        const el = logEl.value;
        if (!el) {
            return true;
        }
        return el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    },
    scrollToBottom() {
        if (logEl.value) {
            logEl.value.scrollTop = logEl.value.scrollHeight;
        }
    },
});
</script>
