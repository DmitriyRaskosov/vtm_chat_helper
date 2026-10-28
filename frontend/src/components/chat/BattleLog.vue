<template>
    <section class="card battle-log">
        <div class="battle-log-heading">
            <h2>Лог боя</h2>
        </div>
        <p v-if="!selectedSceneId" class="muted">Выберите сцену, чтобы видеть броски кубиков.</p>
        <p v-else-if="logError" class="error">{{ logError }}</p>
        <div v-else class="battle-log-wrap">
            <ul ref="listEl" class="battle-log-list" @scroll="onScroll">
                <li
                    v-for="entry in logs"
                    :key="entry.id"
                    class="battle-log-item"
                    :class="{
                        'is-attack-source': attackLogId === entry.id,
                        'is-defense-source': defenseLogId === entry.id,
                    }"
                >
                    <span class="battle-log-time">{{ entry.created_at }}</span>
                    <span
                        v-if="entry.visibility === 'storyteller_only'"
                        class="battle-log-hidden"
                        title="Скрытый бросок"
                    >скрыто</span>
                    <span
                        v-if="entry.willpower_spent"
                        class="battle-log-wp"
                        title="Потрачена Сила Воли"
                    >WP</span>
                    <span class="battle-log-message">{{ entry.message }}</span>
                    <p v-if="entry.comment" class="battle-log-comment muted">{{ entry.comment }}</p>
                    <p v-if="isSumRoll(entry)" class="battle-log-sum">
                        Сумма: <strong>{{ entry.payload?.sum ?? entry.payload?.net }}</strong>
                    </p>
                    <span v-if="isStoryteller && canSeedFrom(entry)" class="battle-log-picks">
                        <button
                            type="button"
                            class="secondary battle-log-pick"
                            :aria-pressed="attackLogId === entry.id"
                            title="Подставить успехи, ботч и трату воли в атаку"
                            @click="pickLog('attack', entry.id)"
                        >
                            Атака
                        </button>
                        <button
                            type="button"
                            class="secondary battle-log-pick"
                            :aria-pressed="defenseLogId === entry.id"
                            title="Подставить успехи, ботч и трату воли в защиту"
                            @click="pickLog('defense', entry.id)"
                        >
                            Защита
                        </button>
                    </span>
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
    isStoryteller: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
});

const listEl = ref(null);
const showScrollDown = ref(false);
const stickToBottom = ref(true);
const emit = defineEmits(['seed-change']);

const attackLogId = ref(null);
const defenseLogId = ref(null);

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
    await nextTick();
    const el = listEl.value;
    if (!el) {
        return;
    }

    const pin = () => {
        el.scrollTop = el.scrollHeight;
    };

    pin();
    requestAnimationFrame(pin);
    stickToBottom.value = true;
    showScrollDown.value = false;
}

function canSeedFrom(entry) {
    const payload = entry?.payload;
    if (!payload || payload.mode === 'sum') {
        return false;
    }

    return payload.outcome != null || payload.successes != null || payload.net != null;
}

function pickLog(side, id) {
    if (side === 'attack') {
        attackLogId.value = attackLogId.value === id ? null : id;
    } else {
        defenseLogId.value = defenseLogId.value === id ? null : id;
    }

    emit('seed-change', {
        attack: attackLogId.value == null ? null : diceFacts(logById(attackLogId.value)),
        defense: defenseLogId.value == null ? null : diceFacts(logById(defenseLogId.value)),
    });
}

function logById(id) {
    if (id == null) {
        return null;
    }

    return props.logs.find((entry) => entry.id === id) ?? null;
}

// Чистый итог броска: единицы уже вычтены, потраченная воля уже входит в число.
function diceFacts(entry) {
    if (!canSeedFrom(entry)) {
        return { successes: 0, botch: false, willpower: false };
    }

    const payload = entry.payload;
    const botch = payload.outcome === 'botch';
    const net = Number(payload.net);
    const raw = Number(payload.successes);
    let successes = 0;

    if (!botch) {
        if (Number.isFinite(net)) {
            successes = Math.max(0, net);
        } else if (Number.isFinite(raw)) {
            successes = Math.max(0, raw);
        }
    }

    return {
        successes,
        botch,
        willpower: entry.willpower_spent === true || payload.willpower_spent === true,
    };
}

watch(
    () => props.selectedSceneId,
    () => {
        attackLogId.value = null;
        defenseLogId.value = null;
    },
);

watch(
    () => [props.logs.length, props.active],
    async () => {
        if (!props.active) {
            return;
        }

        if (!stickToBottom.value) {
            showScrollDown.value = true;

            return;
        }

        await scrollToBottom();
    },
);

defineExpose({
    scrollToBottom,
});
</script>

<style scoped>
.battle-log-picks {
    display: inline-flex;
    gap: 0.35rem;
    margin-left: auto;
}

.battle-log-pick {
    margin-top: 0;
    padding: 0.1rem 0.45rem;
    font-size: 0.75rem;
}

.battle-log-pick[aria-pressed='true'] {
    border-color: var(--accent, #c45c26);
    color: var(--accent, #c45c26);
}

.battle-log-item.is-attack-source,
.battle-log-item.is-defense-source {
    background: rgba(196, 92, 38, 0.08);
}
</style>
