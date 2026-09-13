<template>
    <div v-if="sheet">
        <div class="top">
            <h1>
                <RouterLink to="/chat">Чат</RouterLink>
                <span class="nav-sep">·</span>
                <RouterLink to="/characters">Персонажи</RouterLink>
            </h1>
            <span class="muted">
                {{ auth.user.value?.name }}
                ·
                <button class="link" type="button" @click="logout">Выйти</button>
            </span>
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <section class="card play-bar">
            <label>
                Blood
                <input v-model.number="bloodPool" type="number" min="0" max="50" @change="saveStatus" />
            </label>
            <label>
                Willpower now
                <input v-model.number="tempWillpower" type="number" min="0" max="10" @change="saveStatus" />
            </label>
            <div class="play-health">
                <div class="health-heading">
                    <span class="muted">Health</span>
                    <div class="health-damage">
                        <button
                            v-for="kind in healthDamageKinds"
                            :key="kind.value"
                            type="button"
                            class="secondary"
                            :class="{ current: healthDamage === kind.value }"
                            @click="setHealthDamage(kind.value)"
                        >
                            {{ kind.mark }} {{ kind.label }}
                        </button>
                    </div>
                </div>
                <div class="health-track">
                    <button
                        v-for="box in healthBoxes"
                        :key="box.index"
                        type="button"
                        class="health-step"
                        @click="setHealthLevel(box.index + 1)"
                    >
                        <span
                            class="health-box"
                            :class="box.index < healthLevel ? healthDamage : null"
                        />
                        <span class="health-label">{{ healthLabel(box.index) }}</span>
                    </button>
                </div>
            </div>
            <label>
                XP
                <input v-model.number="experience" type="number" min="0" @change="saveExperience" />
            </label>
        </section>

        <section class="card sheet-header">
            <label>
                Name
                <input v-model="identity.canonical_name" type="text" />
            </label>
            <label>
                Nature
                <input v-model="identity.nature" type="text" />
            </label>
            <label>
                Demeanor
                <input v-model="identity.demeanor" type="text" />
            </label>
            <label>
                Concept
                <input v-model="identity.concept" type="text" />
            </label>
            <label>
                Generation
                <input v-model.number="identity.generation" type="number" min="4" max="15" />
            </label>
            <p class="muted">
                {{ typeLabel(sheet.character_type) }}
                <template v-if="sheet.domitor_name"> · домитор {{ sheet.domitor_name }}</template>
                <template v-if="sheet.clan_name"> · {{ sheet.clan_name }}</template>
            </p>
            <div class="sheet-actions">
                <button type="button" class="secondary" @click="saveIdentity">Сохранить шапку</button>
                <span v-if="flash.identity" class="saved-flash">Сохранено!</span>
            </div>
        </section>

        <section class="card">
            <h2>Attributes</h2>
            <div class="sheet-cols">
                <div v-for="group in attributeGroups" :key="group.key">
                    <h3>{{ group.title }}</h3>
                    <div v-for="trait in group.traits" :key="trait.key" class="trait-row">
                        <span>{{ trait.display_name }}</span>
                        <div class="dots">
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                class="dot"
                                :class="{ filled: traitValue('attribute', trait.key) >= n }"
                                @click="setTrait('attribute', trait, n)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <template v-if="!compact">
            <section class="card">
                <h2>Abilities</h2>
                <div class="sheet-cols">
                    <div v-for="group in abilityGroups" :key="group.key">
                        <h3>{{ group.title }}</h3>
                        <div v-for="trait in group.traits" :key="trait.key" class="trait-row">
                            <span>{{ trait.display_name }}</span>
                            <div class="dots">
                                <button
                                    v-for="n in 5"
                                    :key="n"
                                    type="button"
                                    class="dot"
                                    :class="{ filled: traitValue('ability', trait.key) >= n }"
                                    @click="setTrait('ability', trait, n)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Advantages</h2>
                <div class="sheet-cols">
                    <div>
                        <h3>Disciplines</h3>
                        <div v-for="row in disciplineRows" :key="row.discipline_id" class="trait-row">
                            <span>{{ row.display_name }}</span>
                            <div class="dots">
                                <button
                                    v-for="n in 5"
                                    :key="n"
                                    type="button"
                                    class="dot"
                                    :class="{ filled: row.level >= n }"
                                    @click="setDiscipline(row.discipline_id, n)"
                                />
                            </div>
                        </div>
                        <label>
                            Добавить
                            <select v-model.number="newDisciplineId" @change="addDiscipline">
                                <option :value="null">—</option>
                                <option
                                    v-for="item in unusedDisciplines"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.display_name }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div>
                        <h3>Backgrounds</h3>
                        <div v-for="trait in backgroundTraits" :key="trait.key" class="trait-row">
                            <span>{{ trait.display_name }}</span>
                            <div class="dots">
                                <button
                                    v-for="n in 5"
                                    :key="n"
                                    type="button"
                                    class="dot"
                                    :class="{ filled: traitValue('background', trait.key) >= n }"
                                    @click="setTrait('background', trait, n)"
                                />
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3>Virtues</h3>
                        <div v-for="trait in virtueTraits" :key="trait.key" class="trait-row">
                            <span>{{ trait.display_name }}</span>
                            <div class="dots">
                                <button
                                    v-for="n in 5"
                                    :key="n"
                                    type="button"
                                    class="dot"
                                    :class="{ filled: traitValue('virtue', trait.key) >= n }"
                                    @click="setTrait('virtue', trait, n)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Merits &amp; Flaws</h2>
                <p class="muted merit-hint">
                    Число справа — очки cost из рулбука: merit даёт плюс, flaw минус.
                    При генерации листа суммы обычно сводят к нулю.
                </p>
                <ul class="merit-list">
                    <li v-for="(row, index) in merits" :key="index">
                        <select v-model="row.kind">
                            <option value="merit">Merit</option>
                            <option value="flaw">Flaw</option>
                        </select>
                        <input v-model="row.name" type="text" placeholder="Название" />
                        <label class="merit-cost">
                            <span class="muted">очки</span>
                            <input v-model.number="row.cost" type="number" min="0" max="10" />
                        </label>
                        <span class="merit-sign">{{ meritSigned(row) }}</span>
                        <button type="button" class="link" @click="merits.splice(index, 1)">убрать</button>
                    </li>
                </ul>
                <p class="merit-totals">
                    Merits {{ formatSigned(meritTotals.merits) }}
                    · Flaws {{ formatSigned(-meritTotals.flaws) }}
                    · Итого {{ formatSigned(meritTotals.net) }}
                </p>
                <div class="sheet-actions">
                    <button type="button" class="secondary" @click="addMerit">Добавить</button>
                    <span v-if="flash.meritAdd" class="saved-flash">Добавлено</span>
                    <button type="button" @click="saveMerits">Сохранить merits/flaws</button>
                    <span v-if="flash.merits" class="saved-flash">Сохранено!</span>
                </div>
            </section>

            <section class="card sheet-cols">
                <div>
                    <h2>Humanity / Willpower</h2>
                    <div v-for="trait in otherTraits" :key="trait.key" class="trait-row">
                        <span>{{ trait.display_name }}</span>
                        <div class="dots">
                            <button
                                v-for="n in (trait.maximum || 10)"
                                :key="n"
                                type="button"
                                class="dot"
                                :class="{ filled: traitValue('other', trait.key) >= n }"
                                @click="setTrait('other', trait, n)"
                            />
                        </div>
                    </div>
                </div>
                <div>
                    <h2>Биография</h2>
                    <p v-if="sheet.biography?.summary">{{ sheet.biography.summary }}</p>
                    <p v-else class="muted">Редактор биографии будет позже. Сейчас поле только для чтения, если канон уже есть.</p>
                </div>
            </section>
        </template>
    </div>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else class="muted">Загрузка…</p>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, useAuth } from '../auth';

const auth = useAuth();
const route = useRoute();
const router = useRouter();
const sheet = ref(null);
const catalog = ref(null);
const error = ref('');
const identity = reactive({
    canonical_name: '',
    nature: '',
    demeanor: '',
    concept: '',
    generation: null,
});
const merits = ref([]);
const bloodPool = ref(0);
const tempWillpower = ref(0);
const experience = ref(0);
const newDisciplineId = ref(null);
const healthBoxes = ref(Array.from({ length: 7 }, (_, index) => ({ index, damage: null })));
const healthDamage = ref('bashing');
const flash = reactive({
    identity: false,
    meritAdd: false,
    merits: false,
});
const flashTimers = {};
const healthDamageKinds = [
    { value: 'bashing', label: 'bashing', mark: '/' },
    { value: 'lethal', label: 'lethal', mark: 'X' },
    { value: 'aggravated', label: 'aggravated', mark: '*' },
];

const compact = computed(() => sheet.value?.character_type === 'ghoul');
const statsMap = computed(() => {
    const map = {};
    for (const stat of sheet.value?.stats ?? []) {
        map[`${stat.category}:${stat.stat_key}`] = stat.value;
    }
    return map;
});

const attributeGroups = computed(() => [
    { key: 'physical', title: 'Physical', traits: catalog.value?.attributes?.physical ?? [] },
    { key: 'social', title: 'Social', traits: catalog.value?.attributes?.social ?? [] },
    { key: 'mental', title: 'Mental', traits: catalog.value?.attributes?.mental ?? [] },
]);
const abilityGroups = computed(() => [
    { key: 'talents', title: 'Talents', traits: catalog.value?.abilities?.talents ?? [] },
    { key: 'skills', title: 'Skills', traits: catalog.value?.abilities?.skills ?? [] },
    { key: 'knowledges', title: 'Knowledges', traits: catalog.value?.abilities?.knowledges ?? [] },
]);
const backgroundTraits = computed(() => catalog.value?.backgrounds ?? []);
const virtueTraits = computed(() => catalog.value?.virtues ?? []);
const otherTraits = computed(() => catalog.value?.other ?? []);
const disciplineRows = computed(() => sheet.value?.disciplines ?? []);
const unusedDisciplines = computed(() => {
    const known = new Set(disciplineRows.value.map((row) => row.discipline_id));
    return (catalog.value?.disciplineList ?? []).filter((row) => !known.has(row.id));
});
const healthLevel = computed(() => {
    let max = -1;
    for (const box of healthBoxes.value) {
        if (box.damage) {
            max = Math.max(max, box.index);
        }
    }
    return max + 1;
});
const meritTotals = computed(() => {
    let meritsPts = 0;
    let flawsPts = 0;
    for (const row of merits.value) {
        const cost = Number(row.cost) || 0;
        if (row.kind === 'flaw') {
            flawsPts += cost;
        } else {
            meritsPts += cost;
        }
    }
    return { merits: meritsPts, flaws: flawsPts, net: meritsPts - flawsPts };
});

function typeLabel(type) {
    if (type === 'player') return 'игрок';
    if (type === 'ghoul') return 'гуль';
    return 'НПС';
}

function traitValue(category, key) {
    return statsMap.value[`${category}:${key}`] ?? 0;
}

function healthLabel(index) {
    return catalog.value?.health_boxes?.[index]?.label ?? `Box ${index}`;
}

function formatSigned(value) {
    if (value > 0) {
        return `+${value}`;
    }
    return String(value);
}

function meritSigned(row) {
    const cost = Number(row.cost) || 0;
    return row.kind === 'flaw' ? formatSigned(-cost) : formatSigned(cost);
}

function flashSaved(key) {
    flash[key] = true;
    clearTimeout(flashTimers[key]);
    flashTimers[key] = setTimeout(() => {
        flash[key] = false;
    }, 2000);
}

function addMerit() {
    merits.value.push({ kind: 'merit', name: '', cost: 1 });
    flashSaved('meritAdd');
}

function applySheet(next) {
    sheet.value = next;
    identity.canonical_name = next.canonical_name ?? '';
    identity.nature = next.nature ?? '';
    identity.demeanor = next.demeanor ?? '';
    identity.concept = next.concept ?? '';
    identity.generation = next.generation;
    merits.value = (next.merits_flaws ?? []).map((row) => ({ ...row }));
    bloodPool.value = next.status?.blood_pool ?? 0;
    tempWillpower.value = next.status?.temporary_willpower ?? 0;
    experience.value = next.experience ?? 0;
    healthBoxes.value = (next.health_boxes ?? []).map((box) => ({ ...box }));
    const damaged = [...healthBoxes.value].reverse().find((box) => box.damage);
    healthDamage.value = damaged?.damage ?? 'bashing';
}

async function load() {
    error.value = '';
    try {
        const [sheetRes, catalogRes] = await Promise.all([
            api.get(`/characters/${route.params.id}`),
            catalog.value
                ? Promise.resolve({ data: { catalog: catalog.value, disciplines: catalog.value.disciplineList } })
                : api.get('/character-sheet/catalog'),
        ]);
        if (!catalog.value) {
            catalog.value = {
                ...catalogRes.data.catalog,
                disciplineList: catalogRes.data.disciplines ?? [],
            };
        }
        applySheet(sheetRes.data.character);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить лист.';
    }
}

async function saveIdentity() {
    error.value = '';
    try {
        const { data } = await api.patch(`/characters/${sheet.value.id}`, { ...identity });
        applySheet(data.character);
        flashSaved('identity');
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить шапку.';
    }
}

async function setTrait(category, trait, n) {
    const current = traitValue(category, trait.key);
    const value = current === n ? n - 1 : n;
    error.value = '';
    try {
        const { data } = await api.put(`/characters/${sheet.value.id}/stats`, {
            stats: [{
                category,
                stat_key: trait.key,
                display_name: trait.display_name,
                value: Math.max(0, value),
                maximum: trait.maximum ?? 5,
                sort_order: trait.sort_order ?? 0,
            }],
        });
        applySheet(data.character);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить характеристику.';
    }
}

async function saveStatus() {
    error.value = '';
    try {
        const { data } = await api.patch(`/characters/${sheet.value.id}/status`, {
            revision: sheet.value.status?.revision ?? 0,
            blood_pool: bloodPool.value,
            temporary_willpower: tempWillpower.value,
        });
        applySheet(data.character);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить ресурсы.';
        await load();
    }
}

async function saveExperience() {
    error.value = '';
    try {
        const { data } = await api.patch(`/characters/${sheet.value.id}/experience`, {
            experience: experience.value,
        });
        applySheet(data.character);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить опыт.';
    }
}

async function writeHealth(level, damage) {
    const boxes = healthBoxes.value.map((box) => ({
        index: box.index,
        damage: box.index < level ? damage : null,
    }));
    error.value = '';
    try {
        const { data } = await api.put(`/characters/${sheet.value.id}/health`, { boxes });
        applySheet(data.character);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить здоровье.';
    }
}

async function setHealthLevel(n) {
    const next = healthLevel.value === n ? n - 1 : n;
    await writeHealth(Math.max(0, next), healthDamage.value);
}

async function setHealthDamage(kind) {
    healthDamage.value = kind;
    if (healthLevel.value === 0) {
        return;
    }
    await writeHealth(healthLevel.value, kind);
}

async function saveMerits() {
    error.value = '';
    try {
        const { data } = await api.put(`/characters/${sheet.value.id}/merits`, {
            merits_flaws: merits.value.filter((row) => row.name.trim() !== ''),
        });
        applySheet(data.character);
        flashSaved('merits');
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить merits/flaws.';
    }
}

async function setDiscipline(disciplineId, n) {
    const current = disciplineRows.value.find((row) => row.discipline_id === disciplineId)?.level ?? 0;
    const level = current === n ? n - 1 : n;
    await writeDisciplines(disciplineId, Math.max(0, level));
}

async function addDiscipline() {
    if (!newDisciplineId.value) {
        return;
    }
    await writeDisciplines(newDisciplineId.value, 1);
    newDisciplineId.value = null;
}

async function writeDisciplines(disciplineId, level) {
    const next = disciplineRows.value
        .filter((row) => row.discipline_id !== disciplineId)
        .map((row) => ({ discipline_id: row.discipline_id, level: row.level }));
    next.push({ discipline_id: disciplineId, level });
    error.value = '';
    try {
        const { data } = await api.put(`/characters/${sheet.value.id}/disciplines`, { disciplines: next });
        applySheet(data.character);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить дисциплины.';
    }
}

async function logout() {
    await auth.logout();
    await router.push('/login');
}

watch(() => route.params.id, load);
onMounted(load);
onUnmounted(() => {
    for (const timer of Object.values(flashTimers)) {
        clearTimeout(timer);
    }
});
</script>
