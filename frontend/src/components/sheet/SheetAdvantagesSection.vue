<template>
    <section class="card">
        <h2>Дисциплины</h2>
        <div class="trait-presets">
            <div v-for="row in disciplineRows" :key="row.discipline_id" class="character-row">
                <span>{{ row.name }}</span>
                <TraitDots
                    :value="row.level"
                    :max="5"
                    @pick="emit('set-discipline', row.discipline_id, $event)"
                />
                <button type="button" class="link" @click="emit('remove-discipline', row.discipline_id)">убрать</button>
            </div>
        </div>
        <label>
            Добавить
            <select v-model.number="newDisciplineId" @change="emit('add-discipline')">
                <option :value="null">—</option>
                <option
                    v-for="item in unusedDisciplines"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.name }}
                </option>
            </select>
        </label>
    </section>
</template>

<script setup>

defineProps({
    disciplineRows: { type: Array, required: true },
    unusedDisciplines: { type: Array, required: true },
});

const newDisciplineId = defineModel('newDisciplineId');

const emit = defineEmits(['set-discipline', 'add-discipline', 'remove-discipline']);
</script>
