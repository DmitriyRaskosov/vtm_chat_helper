<template>
    <section class="card">
        <h2>Advantages</h2>
        <div class="sheet-cols">
            <div>
                <h3>Disciplines</h3>
                <div v-for="row in disciplineRows" :key="row.discipline_id" class="trait-row">
                    <span>{{ row.name }}</span>   
                    <TraitDots
                        :value="row.level"
                        :max="5"
                        @pick="emit('set-discipline', row.discipline_id, $event)"
                    />
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
            </div>
            <div>
                <h3>Backgrounds</h3>
                <div v-for="trait in backgroundTraits" :key="trait.key" class="trait-row">
                    <span>{{ trait.display_name }}</span>
                    <TraitDots
                        :value="traitValue('background', trait.key)"
                        :max="5"
                        @pick="emit('set-trait', 'background', trait, $event)"
                    />
                </div>
            </div>
            <div>
                <h3>Virtues</h3>
                <div v-for="trait in virtueTraits" :key="trait.key" class="trait-row">
                    <span>{{ trait.display_name }}</span>
                    <TraitDots
                        :value="traitValue('virtue', trait.key)"
                        :max="5"
                        @pick="emit('set-trait', 'virtue', trait, $event)"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import TraitDots from './TraitDots.vue';

defineProps({
    disciplineRows: { type: Array, required: true },
    unusedDisciplines: { type: Array, required: true },
    backgroundTraits: { type: Array, required: true },
    virtueTraits: { type: Array, required: true },
    traitValue: { type: Function, required: true },
});

const newDisciplineId = defineModel('newDisciplineId');

const emit = defineEmits(['set-discipline', 'add-discipline', 'set-trait']);
</script>
