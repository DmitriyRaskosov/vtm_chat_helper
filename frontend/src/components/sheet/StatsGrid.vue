<template>
    <section class="card stats-grid">
        <header class="stats-grid-header">
            <h2>Чарлист</h2>
            <button type="button" class="secondary" @click="toggleEditing">
                {{ isEditing ? 'Закончить редактирование' : 'Редактировать' }}
            </button>
        </header>

        <p v-if="error" class="error">{{ error }}</p>

        <div v-for="group in groups" :key="group.key" class="stats-group">
            <h3>{{ group.title }}</h3>
            <div :class="group.key === 'state' ? 'stats-pool' : 'stats-dots'">
                <div v-for="stat in group.stats" :key="stat.key" class="stats-row">
                    <span class="stats-label">{{ stat.label }}</span>
                    <div class="stats-track" :class="{ 'stats-track-pool': group.key === 'state' }">
                        <button
                            v-for="index in group.max"
                            :key="index"
                            type="button"
                            class="stats-dot"
                            :class="[dotClass(stat.key, index, group.max), { 'is-locked': !isEditing }]"
                            :aria-disabled="!isEditing || saving"
                            :aria-label="`${stat.label} ${index}`"
                            @click.prevent="onDotClick(stat.key, index, group.max)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-health">
            <h3>Здоровье</h3>
            <label>
                Уровень
                <select v-model.number="healthLevel" :disabled="!isEditing || saving" @change="onHealthChange">
                    <option v-for="option in healthLevels" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <fieldset class="stats-damage" :disabled="!isEditing || saving">
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
            <p class="stats-penalty">Штраф к кубам: {{ healthPenalty }}</p>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { dotTrack, normalizeStats, useCharacterStats } from '../../composables/useCharacterStats';

const GROUP_TITLES = {
    attributes: 'Атрибуты',
    abilities: 'Способности',
    state: 'Состояние',
};

const healthLevels = [
    { value: 0, label: '0 — Здоров' },
    { value: 1, label: '1 — Ушиб' },
    { value: 2, label: '2 — Повреждён' },
    { value: 3, label: '3 — Ранен' },
    { value: 4, label: '4 — Тяжело ранен' },
    { value: 5, label: '5 — Изувечен' },
    { value: 6, label: '6 — Покалечен' },
    { value: 7, label: '7 — Без сознания' },
];

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
});

const emit = defineEmits(['saved']);

const isEditing = ref(false);
const localStats = ref({});
const { saving, error, load, save } = useCharacterStats(() => props.characterId);
let statsEpoch = 0;

const groups = computed(() => {
    return Object.entries(props.schema ?? {}).map(([key, stats]) => ({
        key,
        title: GROUP_TITLES[key] ?? key,
        max: key === 'state' ? 10 : 5,
        stats: Object.entries(stats ?? {}).map(([statKey, label]) => ({
            key: statKey,
            label,
        })),
    }));
});

const healthLevel = computed({
    get() {
        return Number(localStats.value.health?.level ?? 0);
    },
    set(value) {
        writeHealth(Number(value), healthDamage.value);
    },
});

const healthDamage = computed({
    get() {
        return localStats.value.health?.damage ?? 'bashing';
    },
    set(value) {
        writeHealth(healthLevel.value, value);
    },
});

const healthPenalty = computed(() => penaltyFor(healthLevel.value));

watch(
    () => props.stats,
    (stats) => {
        localStats.value = normalizeStats(stats);
        console.log('[StatsGrid] stats from sheet', localStats.value);
    },
    { immediate: true, deep: true },
);

onMounted(async () => {
    const epoch = ++statsEpoch;
    try {
        const stats = await load(props.characterId);
        if (epoch === statsEpoch) {
            localStats.value = stats;
        }
    } catch (err) {
        console.log('[StatsGrid] stats load failed', err);
    }
});

function toggleEditing() {
    isEditing.value = !isEditing.value;
    console.log('[StatsGrid] isEditing', isEditing.value);
}

function penaltyFor(level) {
    if (level <= 1) {
        return 0;
    }
    if (level <= 3) {
        return -1;
    }
    if (level <= 5) {
        return -2;
    }
    if (level === 6) {
        return -5;
    }
    if (level >= 7) {
        return -100;
    }

    return 0;
}

function dotClass(key, index, max) {
    const state = dotTrack(localStats.value[key], max)[Number(index) - 1];
    if (state === 1) {
        return 'is-permanent';
    }
    if (state === 2) {
        return 'is-temporary';
    }

    return 'is-empty';
}

async function onDotClick(key, index, max) {
    if (!isEditing.value || saving.value) {
        return;
    }

    const dotIndex = Number(index);
    const position = dotIndex - 1;
    if (position < 0 || position >= max) {
        return;
    }

    const dots = dotTrack(localStats.value[key], max);
    const state = dots[position];

    if (state === 0) {
        for (let cursor = 0; cursor <= position; cursor += 1) {
            if (dots[cursor] === 0) {
                dots[cursor] = 2;
            }
        }
    } else if (state === 2) {
        dots[position] = 1;
    } else if (state === 1) {
        for (let cursor = position; cursor < dots.length; cursor += 1) {
            dots[cursor] = 0;
        }
    } else {
        return;
    }

    statsEpoch += 1;
    localStats.value[key] = dots;
    console.log('[StatsGrid] click', {
        key,
        dotIndex,
        isEditing: isEditing.value,
        stat: localStats.value[key],
    });
    await persist();
}

function writeHealth(level, damage) {
    localStats.value = {
        ...localStats.value,
        health: {
            level,
            damage,
            penalty: penaltyFor(level),
        },
    };
}

async function onHealthChange() {
    if (!isEditing.value) {
        return;
    }

    writeHealth(healthLevel.value, healthDamage.value);
    await persist();
}

async function persist() {
    try {
        const saved = await save(localStats.value);
        localStats.value = normalizeStats(saved);
        emit('saved', localStats.value);
    } catch {
        localStats.value = normalizeStats(props.stats);
    }
}
</script>

<style scoped>
.stats-grid-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.stats-grid-header h2,
.stats-group h3,
.stats-health h3 {
    margin: 0 0 0.75rem;
}

.stats-group,
.stats-health {
    margin-top: 1.25rem;
}

.stats-row {
    display: grid;
    grid-template-columns: minmax(8rem, 14rem) 1fr;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.4rem;
}

.stats-track {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.stats-dot {
    width: 0.9rem;
    height: 0.9rem;
    min-width: 0.9rem;
    margin: 0;
    padding: 0;
    line-height: 0;
    box-sizing: border-box;
    border: 1px solid var(--text-muted, #8a8078);
    border-radius: 50%;
    background: transparent;
}

.stats-dot.is-locked {
    cursor: not-allowed;
}

.stats-track-pool .stats-dot {
    width: 1.15rem;
    height: 1.15rem;
}

.stats-dot.is-permanent {
    background: var(--text, #1c140f);
    border-color: var(--text, #1c140f);
}

.stats-dot.is-temporary {
    background: #9d2c2c;
    border-color: #9d2c2c;
}

.stats-dot:disabled {
    cursor: default;
}

.stats-health {
    display: grid;
    gap: 0.75rem;
}

.stats-health select {
    display: block;
    margin-top: 0.25rem;
}

.stats-damage {
    margin: 0;
    padding: 0;
    border: none;
}

.stats-damage label {
    margin-right: 0.75rem;
}

.stats-penalty {
    margin: 0;
}
</style>
