<template>
    <details ref="root" class="chronicle-switcher">
        <summary class="chronicle-switcher-summary">
            {{ summaryLabel }}
        </summary>
        <div class="chronicle-switcher-menu card">
            <p v-if="loading" class="muted">Загрузка…</p>
            <p v-else-if="!chronicles.length" class="muted">Хроник пока нет.</p>
            <ul v-else class="chronicle-switcher-list">
                <li v-for="item in chronicles" :key="item.id">
                    <button
                        type="button"
                        class="link chronicle-switcher-item"
                        :class="{ 'chronicle-switcher-item-current': item.id === currentId }"
                        @click="pickChronicle(item.id)"
                    >
                        {{ item.title }}
                        <span class="muted"> · {{ chronicleRoleLabel(item.role) }}</span>
                    </button>
                </li>
            </ul>
            <RouterLink class="chronicle-switcher-create" to="/chronicles" @click="closeMenu">
                Создать новую
            </RouterLink>
        </div>
    </details>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useCurrentChronicle } from '../../composables/useCurrentChronicle';

const router = useRouter();
const root = ref(null);
const { chronicles, currentId, currentChronicle, loading, load, select } = useCurrentChronicle();

const summaryLabel = computed(() => {
    if (currentChronicle.value) {
        return currentChronicle.value.title;
    }

    if (chronicles.value.length === 1) {
        return chronicles.value[0].title;
    }

    return 'Хроника';
});

function chronicleRoleLabel(role) {
    if (role === 'storyteller') {
        return 'рассказчик';
    }

    if (role === 'player') {
        return 'игрок';
    }

    return role;
}

function closeMenu() {
    if (root.value) {
        root.value.open = false;
    }
}

function pickChronicle(id) {
    select(id);
    closeMenu();
    router.push({ name: 'chronicle-chat', params: { chronicleId: String(id) } });
}

onMounted(() => {
    if (!chronicles.value.length) {
        load();
    }
});
</script>

<style scoped>
.chronicle-switcher {
    display: inline-block;
    position: relative;
    margin-right: 0.5rem;
}

.chronicle-switcher-summary {
    cursor: pointer;
    color: var(--accent);
    list-style: none;
}

.chronicle-switcher-summary::-webkit-details-marker {
    display: none;
}

.chronicle-switcher-menu {
    position: absolute;
    z-index: 20;
    left: 0;
    top: calc(100% + 0.35rem);
    min-width: 14rem;
    padding: 0.75rem;
    margin: 0;
}

.chronicle-switcher-list {
    list-style: none;
    margin: 0 0 0.5rem;
    padding: 0;
}

.chronicle-switcher-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.35rem 0;
}

.chronicle-switcher-item-current {
    font-weight: 700;
}

.chronicle-switcher-create {
    display: inline-block;
    margin-top: 0.25rem;
}
</style>
