<template>
    <section class="card sheet-grid">
        <header class="sheet-grid-header">
            <h2>Чарлист</h2>
            <button type="button" class="secondary" @click="toggleEditing">
                {{ isEditing ? 'Закончить редактирование' : 'Редактировать' }}
            </button>
        </header>

        <p v-if="error" class="error">{{ error }}</p>

        <nav class="sheet-tabs" aria-label="Разделы чарлиста">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="secondary"
                :class="{ 'is-active': activeTab === tab.id }"
                :aria-selected="activeTab === tab.id"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
        </nav>

        <div v-if="activeTab === 'header'" class="sheet-header-fields">
            <p><span>Имя</span> {{ display(sheet.canonical_name) }}</p>
            <p><span>Клан</span> {{ display(sheet.clan?.name) }}</p>
            <p><span>Поколение</span> {{ display(sheet.generation) }}</p>
            <p><span>Натура</span> {{ display(sheet.nature) }}</p>
            <p><span>Маска</span> {{ display(sheet.demeanor) }}</p>
        </div>

        <div v-else-if="activeTab === 'attributes'" class="sheet-columns">
            <section v-for="column in attributeColumns" :key="column.key">
                <h3>{{ column.title }}</h3>
                <StatDots
                    v-for="stat in column.stats"
                    :key="stat.key"
                    :label="stat.label"
                    :max="5"
                    :dots="track(stat.key, 5)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick(stat.key, $event, 5)"
                />
            </section>
        </div>

        <div v-else-if="activeTab === 'abilities'" class="sheet-columns">
            <section v-for="column in abilityColumns" :key="column.key">
                <h3>{{ column.title }}</h3>
                <StatDots
                    v-for="stat in column.stats"
                    :key="stat.key"
                    :label="stat.label"
                    :max="5"
                    :dots="track(stat.key, 5)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick(stat.key, $event, 5)"
                />
            </section>
        </div>

        <div v-else-if="activeTab === 'advantages'" class="sheet-columns">
            <section>
                <h3>Дополнения</h3>
                <div v-for="(row, index) in localSheet.backgrounds" :key="index" class="sheet-text-row">
                    <input
                        v-model="row.name"
                        type="text"
                        placeholder="Название"
                        :disabled="!isEditing || saving"
                        @change="commitText"
                    />
                    <input
                        v-model="row.value"
                        type="number"
                        min="0"
                        max="5"
                        :disabled="!isEditing || saving"
                        @change="commitText"
                    />
                    <button type="button" class="secondary" :disabled="!isEditing || saving" @click="removeBackground(index)">
                        Убрать
                    </button>
                </div>
                <button type="button" class="secondary" :disabled="!isEditing || saving" @click="addBackground">
                    Добавить дополнение
                </button>
            </section>

            <section>
                <h3>Дисциплины</h3>
                <div class="sheet-discipline-add">
                    <select v-model="disciplineToAdd" :disabled="!isEditing || saving">
                        <option value="">Выберите дисциплину</option>
                        <optgroup v-if="clanDisciplineOptions.length" label="Клановые">
                            <option v-for="item in clanDisciplineOptions" :key="item.slug" :value="item.slug">
                                {{ item.name }}
                            </option>
                        </optgroup>
                        <optgroup v-if="otherDisciplineOptions.length" label="Остальные">
                            <option v-for="item in otherDisciplineOptions" :key="item.slug" :value="item.slug">
                                {{ item.name }}
                            </option>
                        </optgroup>
                    </select>
                    <button type="button" class="secondary" :disabled="!isEditing || saving || disciplineToAdd === ''" @click="addDiscipline">
                        Добавить
                    </button>
                </div>
                <div v-for="slug in Object.keys(localStats.disciplines ?? {})" :key="slug" class="sheet-discipline">
                    <div class="sheet-discipline-head">
                        <span>{{ disciplineLabel(slug) }}</span>
                        <button type="button" class="secondary" :disabled="!isEditing || saving" @click="removeDiscipline(slug)">
                            Убрать
                        </button>
                    </div>
                    <StatDots
                        label=""
                        :max="5"
                        :dots="disciplineTrack(slug)"
                        :locked="!isEditing || saving"
                        @pick="onDisciplineDotClick(slug, $event)"
                    />
                </div>
            </section>

            <section>
                <h3>Добродетели</h3>
                <StatDots
                    v-for="stat in virtueStats"
                    :key="stat.key"
                    :label="stat.label"
                    :max="5"
                    :dots="track(stat.key, 5)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick(stat.key, $event, 5)"
                />
            </section>
        </div>

        <div v-else class="sheet-status">
            <section>
                <h3>Пороки и достоинства</h3>
                <div v-for="(row, index) in localSheet.merits_flaws" :key="index" class="sheet-text-row">
                    <input
                        v-model="row.name"
                        type="text"
                        placeholder="Название"
                        :disabled="!isEditing || saving"
                        @change="commitText"
                    />
                    <input
                        v-model.number="row.points"
                        type="number"
                        :disabled="!isEditing || saving"
                        @change="commitText"
                    />
                    <select v-model="row.type" :disabled="!isEditing || saving" @change="commitText">
                        <option value="merit">Достоинство</option>
                        <option value="flaw">Порок</option>
                    </select>
                    <button type="button" class="secondary" :disabled="!isEditing || saving" @click="removeMerit(index)">
                        Убрать
                    </button>
                </div>
                <button type="button" class="secondary" :disabled="!isEditing || saving" @click="addMerit">
                    Добавить
                </button>
            </section>

            <section>
                <h3>{{ statLabel('state', 'humanity', 'Человечность') }}</h3>
                <StatDots
                    label=""
                    :max="10"
                    pool
                    :dots="track('humanity', 10)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick('humanity', $event, 10)"
                />
            </section>

            <section>
                <h3>Сила воли</h3>
                <StatDots
                    :label="statLabel('state', 'willpower', 'Постоянная')"
                    :max="10"
                    pool
                    :dots="track('willpower', 10)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick('willpower', $event, 10)"
                />
                <StatDots
                    :label="statLabel('state', 'temporary_willpower', 'Временная')"
                    :max="10"
                    pool
                    :dots="track('temporary_willpower', 10)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick('temporary_willpower', $event, 10)"
                />
            </section>

            <section>
                <h3>{{ statLabel('state', 'blood_pool', 'Запас крови') }}</h3>
                <StatDots
                    label=""
                    :max="10"
                    pool
                    :dots="track('blood_pool', 10)"
                    :locked="!isEditing || saving"
                    @pick="onDotClick('blood_pool', $event, 10)"
                />
            </section>

            <section class="sheet-health">
                <h3>Здоровье</h3>
                <label>
                    Уровень
                    <select v-model.number="healthLevel" :disabled="!isEditing || saving" @change="onHealthChange">
                        <option v-for="option in healthLevels" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <fieldset class="sheet-damage" :disabled="!isEditing || saving">
                    <legend>Тип урона</legend>
                    <label>
                        <input v-model="healthDamage" type="radio" value="bashing" @change="onHealthChange" />
                        Ушибы
                    </label>
                    <label>
                        <input v-model="healthDamage" type="radio" value="lethal" @change="onHealthChange" />
                        Летальный
                    </label>
                    <label>
                        <input v-model="healthDamage" type="radio" value="aggravated" @change="onHealthChange" />
                        Аггравированный
                    </label>
                </fieldset>
                <p class="sheet-penalty">Штраф к кубам: {{ healthPenalty }}</p>
            </section>

            <section>
                <h3>Слабость</h3>
                <textarea
                    v-model="localSheet.weakness"
                    rows="3"
                    :disabled="!isEditing || saving"
                    @change="commitText"
                />
            </section>
        </div>
    </section>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { healthLevels, useCharacterSheetGrid } from '../../composables/useCharacterSheetGrid';
import StatDots from './StatDots.vue';

const props = defineProps({
    characterId: {
        type: [Number, String],
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    schema: {
        type: Object,
        default: () => ({}),
    },
    sheet: {
        type: Object,
        default: () => ({}),
    },
    sheetData: {
        type: Object,
        default: null,
    },
    availableDisciplines: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['saved']);

const {
    tabs,
    activeTab,
    isEditing,
    saving,
    error,
    localStats,
    localSheet,
    disciplineToAdd,
    attributeColumns,
    abilityColumns,
    virtueStats,
    clanDisciplineOptions,
    otherDisciplineOptions,
    healthLevel,
    healthDamage,
    healthPenalty,
    disciplineLabel,
    statLabel,
    track,
    disciplineTrack,
    refresh,
    toggleEditing,
    onDotClick,
    onDisciplineDotClick,
    addDiscipline,
    removeDiscipline,
    addBackground,
    removeBackground,
    addMerit,
    removeMerit,
    onHealthChange,
    commitText,
} = useCharacterSheetGrid(props, emit);

function display(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
}

watch(() => props.characterId, () => {
    refresh();
});
onMounted(() => {
    refresh();
});
</script>

<style scoped>
.sheet-grid-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.sheet-grid-header h2,
.sheet-columns h3,
.sheet-status h3 {
    margin: 0 0 0.75rem;
}

.sheet-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin: 0.75rem 0 1rem;
}

.sheet-tabs button.is-active {
    border-color: var(--text, #1c140f);
    color: var(--text, #1c140f);
}

.sheet-columns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem 1.25rem;
}

.sheet-header-fields p {
    display: grid;
    grid-template-columns: 8rem 1fr;
    gap: 0.75rem;
    margin: 0 0 0.4rem;
}

.sheet-header-fields span {
    color: var(--text-muted, #8a8078);
}

.sheet-text-row,
.sheet-discipline-add,
.sheet-discipline-head {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    align-items: center;
    margin-bottom: 0.4rem;
}

.sheet-text-row input[type='text'] {
    flex: 1 1 8rem;
}

.sheet-text-row input[type='number'] {
    width: 4.5rem;
}

.sheet-status {
    display: grid;
    gap: 1.25rem;
}

.sheet-health {
    display: grid;
    gap: 0.75rem;
}

.sheet-health select {
    display: block;
    margin-top: 0.25rem;
}

.sheet-damage {
    margin: 0;
    padding: 0;
    border: none;
}

.sheet-damage label {
    margin-right: 0.75rem;
}

.sheet-penalty {
    margin: 0;
}

@media (max-width: 900px) {
    .sheet-columns {
        grid-template-columns: 1fr;
    }
}
</style>
