<template>
    <div class="inventory-panel">
        <p v-if="!characterId" class="muted">Выберите персонажа, чтобы открыть инвентарь.</p>
        <template v-else>
            <p v-if="characterName" class="inventory-panel-name">{{ characterName }}</p>
            <p v-if="loading" class="muted">Загрузка…</p>
            <p v-if="error" class="error">{{ error }}</p>

            <section class="inventory-block">
                <h3>Экипировано</h3>
                <p v-if="!equipped.length" class="muted">Пусто</p>
                <ul v-else class="inventory-list">
                    <li v-for="item in equipped" :key="item.id">
                        <span class="inventory-item-name">{{ item.name }} <span class="muted">x{{ item.quantity }}</span></span>
                        <span class="inventory-item-actions">
                            <button type="button" class="btn-sm" :disabled="saving" @click="emit('move', item)">
                                Переместить
                            </button>
                            <button type="button" class="btn-sm" :disabled="saving" @click="emit('remove', item)">
                                Удалить
                            </button>
                        </span>
                    </li>
                </ul>
            </section>

            <section class="inventory-block">
                <h3>В рюкзаке</h3>
                <p v-if="!carried.length" class="muted">Пусто</p>
                <ul v-else class="inventory-list">
                    <li v-for="item in carried" :key="item.id">
                        <span class="inventory-item-name">{{ item.name }} <span class="muted">x{{ item.quantity }}</span></span>
                        <span class="inventory-item-actions">
                            <button type="button" class="btn-sm" :disabled="saving" @click="emit('move', item)">
                                Переместить
                            </button>
                            <button type="button" class="btn-sm" :disabled="saving" @click="emit('remove', item)">
                                Удалить
                            </button>
                        </span>
                    </li>
                </ul>
            </section>

            <form class="inventory-form" @submit.prevent="submit">
                <input
                    v-model="name"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="Название"
                    :disabled="saving"
                />
                <label class="inventory-qty">
                    Кол-во
                    <input v-model.number="quantity" type="number" min="1" required :disabled="saving" />
                </label>
                <label class="toggle">
                    <input v-model="isEquipped" type="checkbox" :disabled="saving" />
                    Надет на персонажа
                </label>
                <button type="submit" class="btn-sm" :disabled="saving || !name.trim()">Добавить</button>
            </form>
        </template>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    characterId: { type: [Number, String], default: null },
    characterName: { type: String, default: '' },
    items: { type: Array, required: true },
    loading: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
    error: { type: String, required: true },
});

const emit = defineEmits(['add', 'move', 'remove']);

const name = ref('');
const quantity = ref(1);
const isEquipped = ref(false);

const equipped = computed(() => props.items.filter((item) => item.is_equipped));
const carried = computed(() => props.items.filter((item) => !item.is_equipped));

function submit() {
    const trimmed = name.value.trim();
    if (!trimmed || props.saving) {
        return;
    }

    const amount = Number(quantity.value);
    emit('add', {
        name: trimmed,
        quantity: Number.isFinite(amount) && amount >= 1 ? amount : 1,
        isEquipped: isEquipped.value,
    });
}

function resetForm() {
    name.value = '';
    quantity.value = 1;
    isEquipped.value = false;
}

defineExpose({ resetForm });
</script>

<style scoped>
.inventory-panel-name {
    margin: 0 0 0.5rem;
    color: var(--gold);
    font-weight: 600;
}

.inventory-block h3 {
    margin: 0.6rem 0 0.35rem;
    font-size: 0.95rem;
}

.inventory-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.inventory-list li {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.4rem;
}

.inventory-item-name {
    min-width: 0;
    overflow-wrap: anywhere;
}

.inventory-item-actions {
    display: flex;
    flex-shrink: 0;
    gap: 0.25rem;
}

.inventory-form {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: 0.75rem;
}

.inventory-form input[type="text"],
.inventory-qty input {
    width: 100%;
}

.inventory-qty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.inventory-qty input {
    width: 5rem;
}
</style>
