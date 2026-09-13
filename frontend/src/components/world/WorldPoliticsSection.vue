<template>
    <section class="card">
        <h2>Политика фракций</h2>
        <p class="muted">Секты и кланы враждуют или союзны целиком, не парами НПС.</p>
        <div class="character-create-grid">
            <label>
                Фракция
                <select v-model="politics.source_entity_id">
                    <option value="">—</option>
                    <option v-for="row in factions" :key="row.id" :value="String(row.id)">
                        {{ row.canonical_name }}
                    </option>
                </select>
            </label>
            <label>
                Отношение
                <select v-model="politics.relation_key">
                    <option value="hostile_to">враждебна к</option>
                    <option value="allied_with">в союзе с</option>
                </select>
            </label>
            <label>
                К кому
                <select v-model="politics.target_entity_id">
                    <option value="">—</option>
                    <option v-for="row in factions" :key="row.id" :value="String(row.id)">
                        {{ row.canonical_name }}
                    </option>
                </select>
            </label>
        </div>
        <button
            type="button"
            :disabled="savingPolitics || !politics.source_entity_id || !politics.target_entity_id"
            @click="emit('add')"
        >
            Добавить
        </button>
        <p v-if="!relations.length" class="muted">Политических рёбер пока нет.</p>
        <ul v-else class="character-tree world-politics">
            <li v-for="row in relations" :key="row.id">
                <div class="character-row">
                    <span>{{ row.source_name }}</span>
                    <span class="muted">{{ politicsLabel(row.relation_key) }}</span>
                    <span>{{ row.target_name }}</span>
                    <button class="link" type="button" @click="emit('end', row.id)">Снять</button>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { politicsLabel } from '../../composables/useWorldPolitics';

defineProps({
    politics: { type: Object, required: true },
    factions: { type: Array, required: true },
    relations: { type: Array, required: true },
    savingPolitics: { type: Boolean, required: true },
});

const emit = defineEmits(['add', 'end']);
</script>
