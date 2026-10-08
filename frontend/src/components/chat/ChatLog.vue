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
            }"
        >
            <div class="meta">
                <span v-if="message.is_ooc" class="ooc-badge">OOC</span>
                <span v-if="message.is_deleted" class="deleted-badge">УДАЛЕНО</span>
                {{ message.author }} · {{ message.created_at }}
            </div>
            <div class="msg-body">{{ message.body }}</div>

            <div v-if="canManage(message)" class="msg-actions">
                <template v-if="!message.is_deleted">
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
        </article>
        <p v-if="!messages.length" class="muted">Пока пусто. Напишите первое сообщение.</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    messages: { type: Array, required: true },
    isStoryteller: { type: Boolean, required: true },
});

const emit = defineEmits(['toggle-ooc', 'delete', 'restore']);

const logEl = ref(null);

function canManage(message) {
    return props.isStoryteller || message.mine;
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