import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

export function extractionMemoryLabel(candidate) {
    return `${candidate.text} · ${candidate.node_type}`;
}
import { useRoute } from 'vue-router';
import { api, useAuth } from '../auth';

export const loreClearanceLevels = [0, 1, 2, 3, 4, 5];

export const healthDamageKinds = [
    { value: 'bashing', label: 'bashing', mark: '/' },
    { value: 'lethal', label: 'lethal', mark: 'X' },
    { value: 'aggravated', label: 'aggravated', mark: '*' },
];

export function typeLabel(type) {
    if (type === 'player') return 'игрок';
    return 'НПС';
}

export function loreClearanceLevelsLabel(levels) {
    const sorted = [...(levels ?? [0])].sort((a, b) => a - b);
    return sorted.map((level) => `уровень ${level}`).join(', ');
}

export function setClearanceThrough(level, current) {
    const next = new Set(current ?? []);
    for (const value of loreClearanceLevels) {
        if (value <= level) {
            next.add(value);
        } else {
            next.delete(value);
        }
    }
    return [...next].sort((a, b) => a - b);
}

function optionalId(value) {
    return value === '' ? null : Number(value);
}

export function useCharacterSheet() {
    const auth = useAuth();
    const route = useRoute();
    const sheet = ref(null);
    const catalog = ref(null);
    const worldEntities = ref([]);
    const error = ref('');
    const isStoryteller = computed(() => auth.user.value?.is_storyteller === true);
    const identity = reactive({
        canonical_name: '',
        nature: '',
        demeanor: '',
        concept: '',
        generation: null,
    });
    const biography = reactive({
        summary: '',
        full_text: '',
        principles: '',
        motivation: '',
        fears: '',
        desires: '',
        behavioral_rules: '',
    });
    const place = reactive({
        sect_id: '',
        clan_id: '',
        haven_entity_id: '',
        lore_clearance_levels: [0],
    });
    const creating = reactive({ sect: false, haven: false });
    const createNames = reactive({ sect: '', haven: '' });
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
        biography: false,
        place: false,
    });
    const flashTimers = {};
    const extractorEnabled = ref(false);
    const extracting = ref(false);
    const sects = computed(() => catalog.value?.sects ?? []);
    const clans = computed(() => catalog.value?.clans ?? []);
    const bloodlines = computed(() => catalog.value?.bloodlines ?? []);
    const havens = computed(() => worldEntities.value.filter((row) => row.entity_type === 'location'));
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

    function removeMerit(index) {
        merits.value.splice(index, 1);
    }

    async function loadExtractorStatus() {
        if (!isStoryteller.value) {
            extractorEnabled.value = false;
            return;
        }
        try {
            const { data } = await api.get('/extract/status');
            extractorEnabled.value = Boolean(data.enabled);
        } catch {
            extractorEnabled.value = false;
        }
    }

    function applySheet(character) {
        sheet.value = character;

        identity.canonical_name = character.canonical_name ?? '';
        identity.nature = character.nature ?? '';
        identity.demeanor = character.demeanor ?? '';
        identity.concept = character.concept ?? '';
        identity.generation = character.generation;
        biography.summary = character.biography?.summary ?? '';
        biography.full_text = character.biography?.full_text ?? '';
        biography.principles = character.biography?.principles ?? '';
        biography.motivation = character.biography?.motivation ?? '';
        biography.fears = character.biography?.fears ?? '';
        biography.desires = character.biography?.desires ?? '';
        biography.behavioral_rules = character.biography?.behavioral_rules ?? '';

        // синхронизируем place с новым состоянием персонажа
        Object.assign(place, {
            sect_id: character.sect_id ?? '',
            clan_id: character.clan_id ?? '',
            haven_entity_id: character.haven_entity_id ?? '',
            lore_clearance_levels: [...(character.lore_clearance_levels ?? [0])],
        });

        merits.value = (character.merits_flaws ?? []).map((row) => ({ ...row }));
        bloodPool.value = character.status?.blood_pool ?? 0;
        tempWillpower.value = character.status?.temporary_willpower ?? 0;
        experience.value = character.experience ?? 0;
        healthBoxes.value = (character.health_boxes ?? []).map((box) => ({ ...box }));
        const damaged = [...healthBoxes.value].reverse().find((box) => box.damage);
        healthDamage.value = damaged?.damage ?? 'bashing';
    }

    async function runBiographyExtraction() {
        if (!sheet.value?.id || extracting.value) {
            return null;
        }
        extracting.value = true;
        error.value = '';
        try {
            const { data } = await api.post('/extract', { character_id: sheet.value.id }, { timeout: 320000 });
            return data.extraction_run_id ?? data.run?.id ?? null;
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось разобрать биографию.';
            return null;
        } finally {
            extracting.value = false;
        }
    }

    async function load() {
        error.value = '';
        try {
            const requests = [
                api.get(`/characters/${route.params.id}`),
                catalog.value
                    ? Promise.resolve({ data: { catalog: catalog.value, disciplines: catalog.value.disciplineList } })
                    : api.get('/character-sheet/catalog'),
            ];
            if (isStoryteller.value) {
                requests.push(api.get('/world/entities'));
            }
            const [sheetRes, catalogRes, worldRes] = await Promise.all(requests);
            if (!catalog.value) {
                catalog.value = {
                    ...catalogRes.data.catalog,
                    disciplineList: catalogRes.data.disciplines ?? [],
                    clans: catalogRes.data.clans ?? [],
                    bloodlines: catalogRes.data.bloodlines ?? [],
                    sects: catalogRes.data.sects ?? [],
                };
            }
            if (worldRes) {
                worldEntities.value = worldRes.data.entities ?? [];
            }
            if (sheetRes) {
                applySheet(sheetRes.data.character);
            }
            await loadExtractorStatus();
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

    async function saveBiography() {
        error.value = '';
        try {
            const { data } = await api.put(`/characters/${sheet.value.id}/biography`, { ...biography });
            applySheet(data.character);
            flashSaved('biography');
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.summary?.[0]
                ?? 'Не удалось сохранить биографию.';
        }
    }

    function missingPlaceOption(kind) {
        if (kind === 'sect') {
            return Boolean(sheet.value?.sect_id)
                && !sects.value.some((row) => row.id === sheet.value.sect_id);
        }
        if (kind === 'clan') {
            return Boolean(sheet.value?.clan_id)
                && !clans.value.some((row) => row.id === sheet.value.clan_id);
        }
        return Boolean(sheet.value?.haven_entity_id)
            && !havens.value.some((row) => row.id === sheet.value.haven_entity_id);
    }

    async function savePlace() {
        error.value = '';
        try {
            const { data } = await api.put(`/characters/${sheet.value.id}/place`, {
                sect_id: optionalId(place.sect_id),
                clan_id: optionalId(place.clan_id),
                haven_entity_id: optionalId(place.haven_entity_id),
                lore_clearance_levels: [...place.lore_clearance_levels].sort((a, b) => a - b),
            });
            applySheet(data.character);
            flashSaved('place');
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось сохранить место в мире.';
        }
    }

    async function createPlaceEntity(kind) {
        const name = createNames[kind].trim();
        if (!name) {
            return;
        }
        error.value = '';
        try {
            const payload = kind === 'haven'
                ? { canonical_name: name, entity_type: 'location' }
                : { canonical_name: name, entity_type: 'faction' };
            const { data } = await api.post('/world/entities', payload);
            worldEntities.value = [...worldEntities.value, data.entity];
            if (kind === 'sect') {
                place.sect_id = String(data.entity.id);
            } else {
                place.haven_entity_id = String(data.entity.id);
            }
            createNames[kind] = '';
            creating[kind] = false;
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось создать сущность мира.';
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

    watch(() => route.params.id, load);
    onMounted(load);
    onUnmounted(() => {
        for (const timer of Object.values(flashTimers)) {
            clearTimeout(timer);
        }
    });

    return {
        sheet,
        error,
        isStoryteller,
        identity,
        biography,
        place,
        creating,
        createNames,
        merits,
        bloodPool,
        tempWillpower,
        experience,
        newDisciplineId,
        healthBoxes,
        healthDamage,
        flash,
        sects,
        clans,
        havens,
        attributeGroups,
        abilityGroups,
        backgroundTraits,
        virtueTraits,
        otherTraits,
        disciplineRows,
        unusedDisciplines,
        healthLevel,
        meritTotals,
        traitValue,
        healthLabel,
        formatSigned,
        meritSigned,
        addMerit,
        removeMerit,
        missingPlaceOption,
        saveIdentity,
        saveBiography,
        savePlace,
        createPlaceEntity,
        setTrait,
        saveStatus,
        saveExperience,
        setHealthLevel,
        setHealthDamage,
        saveMerits,
        setDiscipline,
        addDiscipline,
        extractorEnabled,
        extracting,
        runBiographyExtraction,
    };
}
