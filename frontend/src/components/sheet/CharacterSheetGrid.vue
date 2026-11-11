<template>
    <div class="sheet-grid">
        <header class="sheet-grid-header">
            <h2>Чарлист</h2>
            <button
                type="button"
                class="secondary"
                :disabled="identitySaving"
                @click.stop="toggleEditing"
            >
                {{ isEditing ? 'Закончить редактирование' : 'Редактировать' }}
            </button>
        </header>

        <p v-if="error" class="error">{{ error }}</p>

        <div class="sheet-stack">
            <section class="sheet-block">
                <h3 class="sheet-block-heading">Шапка</h3>
                <div v-if="!isEditing" class="sheet-header-readonly">
                    <div class="sheet-header-field">
                        Имя
                        <span class="sheet-header-value-display">{{ display(sheet.canonical_name) }}</span>
                    </div>
                    <div class="sheet-header-field">
                        Клан
                        <span class="sheet-header-value-display">{{ display(sheet.clan?.name) }}</span>
                    </div>
                    <div class="sheet-header-field">
                        Поколение
                        <span class="sheet-header-value-display">{{ display(sheet.generation) }}</span>
                    </div>
                    <div class="sheet-header-field">
                        Натура
                        <span class="sheet-header-value-display">{{ display(sheet.nature) }}</span>
                    </div>
                    <div class="sheet-header-field">
                        Маска
                        <span class="sheet-header-value-display">{{ display(sheet.demeanor) }}</span>
                    </div>
                </div>
                <div v-else class="sheet-header-edit">
                    <label class="sheet-header-field">
                        Имя
                        <input v-model="localHeader.canonical_name" type="text" maxlength="120" :disabled="formLocked" />
                    </label>
                    <label class="sheet-header-field">
                        Клан
                        <select v-model="localHeader.clan_id" :disabled="formLocked">
                            <option value="">—</option>
                            <option v-for="c in clanOptions" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </option>
                        </select>
                    </label>
                    <label class="sheet-header-field">
                        Поколение
                        <input
                            v-model.number="localHeader.generation"
                            type="number"
                            min="4"
                            max="15"
                            :disabled="formLocked"
                        />
                    </label>
                    <label class="sheet-header-field">
                        Натура
                        <input v-model="localHeader.nature" type="text" maxlength="64" :disabled="formLocked" />
                    </label>
                    <label class="sheet-header-field">
                        Маска
                        <input v-model="localHeader.demeanor" type="text" maxlength="64" :disabled="formLocked" />
                    </label>
                </div>
            </section>

            <section class="sheet-block">
                <h3 class="sheet-block-heading">Атрибуты</h3>
                <div class="sheet-columns">
                    <section v-for="column in attributeColumns" :key="column.key">
                        <h4 class="sheet-subheading">{{ column.title }}</h4>
                        <StatDots
                            v-for="stat in column.stats"
                            :key="stat.key"
                            :label="stat.label"
                            :max="5"
                            :dots="track(stat.key, 5)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick(stat.key, $event, 5)"
                        />
                    </section>
                </div>
            </section>

            <section class="sheet-block">
                <h3 class="sheet-block-heading">Способности</h3>
                <div class="sheet-columns">
                    <section v-for="column in abilityColumns" :key="column.key">
                        <h4 class="sheet-subheading">{{ column.title }}</h4>
                        <StatDots
                            v-for="stat in column.stats"
                            :key="stat.key"
                            :label="stat.label"
                            :max="5"
                            :dots="track(stat.key, 5)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick(stat.key, $event, 5)"
                        />
                    </section>
                </div>
            </section>

            <section class="sheet-block">
                <h3 class="sheet-block-heading">Преимущества</h3>
                <div class="sheet-columns">
                    <section>
                        <h4 class="sheet-subheading">Дополнения</h4>
                        <div v-for="(row, index) in localSheet.backgrounds" :key="index" class="sheet-text-row">
                            <input
                                v-model="row.name"
                                type="text"
                                placeholder="Название"
                                :disabled="!isEditing || formLocked"
                                @change="commitText"
                            />
                            <input
                                v-model="row.value"
                                type="number"
                                min="0"
                                max="5"
                                :disabled="!isEditing || formLocked"
                                @change="commitText"
                            />
                            <button type="button" class="secondary" :disabled="!isEditing || formLocked" @click="removeBackground(index)">
                                Убрать
                            </button>
                        </div>
                        <button type="button" class="secondary" :disabled="!isEditing || formLocked" @click="addBackground">
                            Добавить дополнение
                        </button>
                    </section>

                    <section>
                        <h4 class="sheet-subheading">Дисциплины</h4>
                        <div class="sheet-discipline-add">
                            <select v-model="disciplineToAdd" :disabled="!isEditing || formLocked">
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
                            <button type="button" class="secondary" :disabled="!isEditing || formLocked || disciplineToAdd === ''" @click="addDiscipline">
                                Добавить
                            </button>
                        </div>
                        <div v-for="slug in Object.keys(localStats.disciplines ?? {})" :key="slug" class="sheet-discipline">
                            <div class="sheet-discipline-head">
                                <span>{{ disciplineLabel(slug) }}</span>
                                <button type="button" class="secondary" :disabled="!isEditing || formLocked" @click="removeDiscipline(slug)">
                                    Убрать
                                </button>
                            </div>
                            <StatDots
                                label=""
                                :max="5"
                                :dots="disciplineTrack(slug)"
                                :locked="!isEditing || formLocked"
                                @pick="onDisciplineDotClick(slug, $event)"
                            />
                        </div>
                    </section>

                    <section>
                        <h4 class="sheet-subheading">Добродетели</h4>
                        <StatDots
                            v-for="stat in virtueStats"
                            :key="stat.key"
                            :label="stat.label"
                            :max="5"
                            :dots="track(stat.key, 5)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick(stat.key, $event, 5)"
                        />
                    </section>
                </div>
            </section>

            <section class="sheet-block">
                <h3 class="sheet-block-heading">Статус</h3>
                <div class="sheet-status">
                    <section>
                        <h4 class="sheet-subheading">Пороки и достоинства</h4>
                        <div v-for="(row, index) in localSheet.merits_flaws" :key="index" class="sheet-text-row">
                            <input
                                v-model="row.name"
                                type="text"
                                placeholder="Название"
                                :disabled="!isEditing || formLocked"
                                @change="commitText"
                            />
                            <input
                                v-model.number="row.points"
                                type="number"
                                :disabled="!isEditing || formLocked"
                                @change="commitText"
                            />
                            <select v-model="row.type" :disabled="!isEditing || formLocked" @change="commitText">
                                <option value="merit">Достоинство</option>
                                <option value="flaw">Порок</option>
                            </select>
                            <button type="button" class="secondary" :disabled="!isEditing || formLocked" @click="removeMerit(index)">
                                Убрать
                            </button>
                        </div>
                        <button type="button" class="secondary" :disabled="!isEditing || formLocked" @click="addMerit">
                            Добавить
                        </button>
                    </section>

                    <section>
                        <h4 class="sheet-subheading">{{ statLabel('state', 'humanity', 'Человечность') }}</h4>
                        <StatDots
                            label=""
                            :max="10"
                            pool
                            :dots="track('humanity', 10)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick('humanity', $event, 10)"
                        />
                    </section>

                    <section>
                        <h4 class="sheet-subheading">Сила воли</h4>
                        <StatDots
                            :label="statLabel('state', 'willpower', 'Постоянная')"
                            :max="10"
                            pool
                            :dots="track('willpower', 10)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick('willpower', $event, 10)"
                        />
                        <StatDots
                            :label="statLabel('state', 'temporary_willpower', 'Временная')"
                            :max="10"
                            pool
                            :dots="track('temporary_willpower', 10)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick('temporary_willpower', $event, 10)"
                        />
                    </section>

                    <section>
                        <h4 class="sheet-subheading">{{ statLabel('state', 'blood_pool', 'Запас крови') }}</h4>
                        <StatDots
                            label=""
                            :max="10"
                            pool
                            :dots="track('blood_pool', 10)"
                            :locked="!isEditing || formLocked"
                            @pick="onDotClick('blood_pool', $event, 10)"
                        />
                    </section>

                    <section class="sheet-health">
                        <h4 class="sheet-subheading">Здоровье</h4>
                        <label>
                            Уровень
                            <select v-model.number="healthLevel" :disabled="!isEditing || formLocked" @change="onHealthChange">
                                <option v-for="option in healthLevels" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <fieldset class="sheet-damage" :disabled="!isEditing || formLocked">
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
                        <h4 class="sheet-subheading">Слабость</h4>
                        <textarea
                            v-model="localSheet.weakness"
                            rows="3"
                            :disabled="!isEditing || formLocked"
                            @change="commitText"
                        />
                    </section>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
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
    clans: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['saved']);

const clanOptions = computed(() => {
    const list = Array.isArray(props.clans) ? props.clans : [];

    return list
        .slice()
        .sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ru'));
});

const {
    isEditing,
    saving,
    identitySaving,
    error,
    localStats,
    localSheet,
    localHeader,
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

const formLocked = computed(() => identitySaving.value || saving.value);

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

.sheet-grid-header h2 {
    margin: 0;
}

.sheet-stack {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    margin-top: 0.25rem;
}

.sheet-block {
    padding: 0.75rem 0.85rem;
    border: 1px solid var(--border, #c9bfb3);
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.03);
}

.sheet-block-heading {
    margin: 0 0 0.55rem;
    padding-bottom: 0.3rem;
    font-size: 1rem;
    font-weight: 600;
    border-bottom: 1px solid var(--border, #c9bfb3);
    color: var(--text, #1c140f);
}

.sheet-subheading {
    margin: 0 0 0.45rem;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-muted, #5c534a);
}

.sheet-columns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem 0.85rem;
}

.sheet-columns > section > .sheet-subheading:first-child {
    margin-top: 0;
}

/* Шапка: пять колонок; в каждой подпись сверху и значение/поле снизу (одинаково в просмотре и редактировании) */
.sheet-header-readonly,
.sheet-header-edit {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.5rem 0.75rem;
    align-items: start;
}

.sheet-header-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    font-size: 0.85rem;
    color: var(--text-muted, #8a8078);
}

.sheet-header-field input,
.sheet-header-field select {
    width: 100%;
    box-sizing: border-box;
}

.sheet-header-value-display {
    font-size: 0.9rem;
    line-height: 1.35;
    color: var(--text, #1c140f);
    word-break: break-word;
    min-width: 0;
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
    gap: 0.85rem;
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

    .sheet-header-readonly,
    .sheet-header-edit {
        grid-template-columns: 1fr;
    }
}
</style>
