<template>
    <section class="card battle-log">
        <h2>Лог боя</h2>
        <p v-if="!selectedSceneId" class="muted">Выберите сцену, чтобы видеть броски кубиков.</p>
        <p v-else-if="logError" class="error">{{ logError }}</p>
        <div v-else class="battle-log-wrap">
            <ul ref="listEl" class="battle-log-list" @scroll="onScroll">
                <li v-for="entry in logs" :key="entry.id" class="battle-log-item">
                    <span class="battle-log-time">{{ entry.created_at }}</span>
                    <span
                        v-if="entry.visibility === 'storyteller_only'"
                        class="battle-log-hidden"
                        title="Скрытый бросок"
                    >скрыто</span>
                    <span class="battle-log-message">{{ entry.message }}</span>
                    <p v-if="entry.comment" class="battle-log-comment muted">{{ entry.comment }}</p>
                    <p v-if="isSumRoll(entry)" class="battle-log-sum">
                        Сумма: <strong>{{ entry.payload?.sum ?? entry.payload?.net }}</strong>
                    </p>
                </li>
                <li v-if="!logs.length" class="muted">Пока нет бросков.</li>
            </ul>
            <button
                v-if="showScrollDown"
                type="button"
                class="battle-log-scroll-down"
                title="Вниз"
                @click="scrollToBottom"
            >
                ↓
            </button>
        </div>
    </section>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    logs: { type: Array, required: true },
    logError: { type: String, default: '' },
    selectedSceneId: { type: Number, default: null },
});

const listEl = ref(null);
const showScrollDown = ref(false);
const stickToBottom = ref(true);

function isSumRoll(entry) {
    return entry?.payload?.mode === 'sum';
}

function isNearBottom() {
    const el = listEl.value;
    if (!el) {
        return true;
    }

    return el.scrollHeight - el.scrollTop - el.clientHeight < 48;
}

function onScroll() {
    stickToBottom.value = isNearBottom();
    showScrollDown.value = !stickToBottom.value;
}

async function scrollToBottom() {
    const el = listEl.value;
    if (!el) {
        return;
    }

    el.scrollTop = el.scrollHeight;
    stickToBottom.value = true;
    showScrollDown.value = false;
}

watch(
    () => props.logs.length,
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
    scrollToBottom,
});
</script>
