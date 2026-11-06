import { computed, ref, watch } from 'vue';
import { dotTrack, normalizeStats, useCharacterStats } from './useCharacterStats';
import { apiErrorMessage } from '../utils/apiError';

const TABS = [
    { id: 'header', label: 'Шапка' },
    { id: 'attributes', label: 'Атрибуты' },
    { id: 'abilities', label: 'Способности' },
    { id: 'advantages', label: 'Преимущества' },
    { id: 'status', label: 'Статус' },
];

const COLUMN_TITLES = {
    physical: 'Физические',
    social: 'Социальные',
    mental: 'Ментальные',
    talents: 'Таланты',
    skills: 'Навыки',
    knowledges: 'Познания',
};

export const healthLevels = [
    { value: 0, label: '0 — Здоров' },
    { value: 1, label: '1 — Ушиб' },
    { value: 2, label: '2 — Повреждён' },
    { value: 3, label: '3 — Ранен' },
    { value: 4, label: '4 — Тяжело ранен' },
    { value: 5, label: '5 — Изувечен' },
    { value: 6, label: '6 — Покалечен' },
    { value: 7, label: '7 — Без сознания' },
];

export function normalizeSheetData(data) {
    const source = data && typeof data === 'object' ? data : {};

    return {
        backgrounds: Array.isArray(source.backgrounds)
            ? source.backgrounds.map((row) => ({
                name: String(row?.name ?? ''),
                value: row?.value ?? '',
            }))
            : [],
        merits_flaws: Array.isArray(source.merits_flaws)
            ? source.merits_flaws.map((row) => ({
                name: String(row?.name ?? ''),
                points: Number(row?.points ?? 0),
                type: row?.type === 'flaw' ? 'flaw' : 'merit',
            }))
            : [],
        weakness: String(source.weakness ?? ''),
    };
}

function column(schema, key) {
    const stats = schema?.[key] ?? {};

    return {
        key,
        title: COLUMN_TITLES[key] ?? key,
        stats: Object.entries(stats).map(([statKey, label]) => ({
            key: statKey,
            label,
        })),
    };
}

function sheetPayload(sheet) {
    return {
        backgrounds: sheet.backgrounds
            .filter((row) => row.name.trim() !== '')
            .map((row) => ({
                name: row.name.trim(),
                value: numericOrText(row.value),
            })),
        merits_flaws: sheet.merits_flaws
            .filter((row) => row.name.trim() !== '')
            .map((row) => ({
                name: row.name.trim(),
                points: Number(row.points) || 0,
                type: row.type === 'flaw' ? 'flaw' : 'merit',
            })),
        weakness: sheet.weakness ?? '',
    };
}

function numericOrText(value) {
    if (value === '' || value === null || value === undefined) {
        return 0;
    }

    const number = Number(value);

    return Number.isFinite(number) && String(value).trim() !== '' ? number : String(value);
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

function cycleDot(dots, position) {
    const next = dots.slice();
    const state = next[position];

    if (state === 0) {
        for (let cursor = 0; cursor <= position; cursor += 1) {
            if (next[cursor] === 0) {
                next[cursor] = 2;
            }
        }
    } else if (state === 2) {
        next[position] = 1;
    } else if (state === 1) {
        for (let cursor = position; cursor < next.length; cursor += 1) {
            next[cursor] = 0;
        }
    }

    return next;
}

export function useCharacterSheetGrid(props, emit) {
    const activeTab = ref('header');
    const isEditing = ref(false);
    const localStats = ref(normalizeStats(props.stats));
    const localSheet = ref(normalizeSheetData(props.sheetData));
    const disciplineToAdd = ref('');
    const { saving, error, load, save } = useCharacterStats(() => props.characterId);
    let epoch = 0;

    const attributeColumns = computed(() => [
        column(props.schema, 'physical'),
        column(props.schema, 'social'),
        column(props.schema, 'mental'),
    ]);
    const abilityColumns = computed(() => [
        column(props.schema, 'talents'),
        column(props.schema, 'skills'),
        column(props.schema, 'knowledges'),
    ]);
    const virtueStats = computed(() => column(props.schema, 'virtues').stats);

    const addedSlugs = computed(() => new Set(Object.keys(localStats.value.disciplines ?? {})));
    const clanDisciplineOptions = computed(() => openDisciplines(true));
    const otherDisciplineOptions = computed(() => openDisciplines(false));

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
            if (isEditing.value || saving.value) {
                return;
            }
            localStats.value = normalizeStats(stats);
        },
        { deep: true },
    );

    watch(
        () => props.sheetData,
        (data) => {
            if (isEditing.value || saving.value) {
                return;
            }
            localSheet.value = normalizeSheetData(data);
        },
        { deep: true },
    );

    function openDisciplines(clan) {
        return (props.availableDisciplines ?? []).filter((item) => {
            const isClan = item.is_clan_discipline === true;

            return isClan === clan && !addedSlugs.value.has(item.slug);
        });
    }

    function disciplineLabel(slug) {
        const found = (props.availableDisciplines ?? []).find((item) => item.slug === slug);
        if (!found) {
            return slug;
        }

        return found.is_clan_discipline ? `${found.name} · клановая` : found.name;
    }

    function statLabel(group, key, fallback) {
        return props.schema?.[group]?.[key] ?? fallback;
    }

    function dotClass(key, index, max) {
        const state = dotTrack(localStats.value[key], max)[Number(index) - 1];

        return state === 1 ? 'is-permanent' : state === 2 ? 'is-temporary' : 'is-empty';
    }

    function disciplineDotClass(slug, index) {
        const state = dotTrack(localStats.value.disciplines?.[slug], 5)[Number(index) - 1];

        return state === 1 ? 'is-permanent' : state === 2 ? 'is-temporary' : 'is-empty';
    }

    async function refresh() {
        const current = ++epoch;
        isEditing.value = false;
        localStats.value = normalizeStats(props.stats);
        localSheet.value = normalizeSheetData(props.sheetData);

        try {
            const loaded = await load(props.characterId);
            if (current !== epoch) {
                return;
            }
            localStats.value = loaded.stats;
            localSheet.value = normalizeSheetData(loaded.sheet_data);
        } catch (err) {
            if (current !== epoch) {
                return;
            }
            error.value = apiErrorMessage(err, 'Не удалось загрузить чарлист.');
        }
    }

    function toggleEditing() {
        isEditing.value = !isEditing.value;
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

    async function onDotClick(key, index, max) {
        if (!isEditing.value || saving.value) {
            return;
        }

        const position = Number(index) - 1;
        if (position < 0 || position >= max) {
            return;
        }

        epoch += 1;
        localStats.value = {
            ...localStats.value,
            [key]: cycleDot(dotTrack(localStats.value[key], max), position),
        };
        await persist();
    }

    async function onDisciplineDotClick(slug, index) {
        if (!isEditing.value || saving.value) {
            return;
        }

        const position = Number(index) - 1;
        if (position < 0 || position >= 5) {
            return;
        }

        epoch += 1;
        const disciplines = { ...(localStats.value.disciplines ?? {}) };
        disciplines[slug] = cycleDot(dotTrack(disciplines[slug], 5), position);
        localStats.value = { ...localStats.value, disciplines };
        await persist();
    }

    async function addDiscipline() {
        const slug = disciplineToAdd.value;
        if (!isEditing.value || saving.value || slug === '' || addedSlugs.value.has(slug)) {
            return;
        }

        epoch += 1;
        localStats.value = {
            ...localStats.value,
            disciplines: {
                ...(localStats.value.disciplines ?? {}),
                [slug]: dotTrack(0, 5),
            },
        };
        disciplineToAdd.value = '';
        await persist();
    }

    async function removeDiscipline(slug) {
        if (!isEditing.value || saving.value) {
            return;
        }

        epoch += 1;
        const disciplines = { ...(localStats.value.disciplines ?? {}) };
        delete disciplines[slug];
        localStats.value = { ...localStats.value, disciplines };
        await persist();
    }

    function addBackground() {
        if (!isEditing.value) {
            return;
        }

        localSheet.value.backgrounds.push({ name: '', value: 0 });
    }

    async function removeBackground(index) {
        if (!isEditing.value || saving.value) {
            return;
        }

        localSheet.value.backgrounds.splice(index, 1);
        await persist();
    }

    function addMerit() {
        if (!isEditing.value) {
            return;
        }

        localSheet.value.merits_flaws.push({ name: '', points: 0, type: 'merit' });
    }

    async function removeMerit(index) {
        if (!isEditing.value || saving.value) {
            return;
        }

        localSheet.value.merits_flaws.splice(index, 1);
        await persist();
    }

    async function onHealthChange() {
        if (!isEditing.value || saving.value) {
            return;
        }

        writeHealth(healthLevel.value, healthDamage.value);
        await persist();
    }

    async function commitText() {
        if (!isEditing.value || saving.value) {
            return;
        }

        await persist();
    }

    async function persist() {
        const pendingBackgrounds = localSheet.value.backgrounds.filter((row) => row.name.trim() === '');
        const pendingMerits = localSheet.value.merits_flaws.filter((row) => row.name.trim() === '');

        try {
            const saved = await save(localStats.value, sheetPayload(localSheet.value));
            const sheet = normalizeSheetData(saved.sheet_data);
            localStats.value = saved.stats;
            localSheet.value = {
                ...sheet,
                backgrounds: [...sheet.backgrounds, ...pendingBackgrounds],
                merits_flaws: [...sheet.merits_flaws, ...pendingMerits],
            };
            emit('saved', {
                stats: localStats.value,
                sheet_data: saved.sheet_data,
            });
        } catch {
            localStats.value = normalizeStats(props.stats);
            localSheet.value = normalizeSheetData(props.sheetData);
        }
    }

    return {
        tabs: TABS,
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
        dotClass,
        disciplineDotClass,
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
    };
}
