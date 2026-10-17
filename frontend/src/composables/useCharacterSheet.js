import { computed, reactive, ref, unref } from 'vue';
import { api, isStoryteller } from '../auth';
import { useFlash } from './useFlash';
import { apiErrorMessage } from '../utils/apiError';
import { characterTypeLabel } from '../utils/labels';

export { characterTypeLabel as typeLabel };

export const TRAIT_PRESETS = [
    {
        key: 'appearance',
        label: 'Внешность',
        placeholder: 'Худой, в поношенном пальто. Бледные руки в перчатках.',
    },
    {
        key: 'speech_style',
        label: 'Манера речи',
        placeholder: 'Говорит короткими фразами, часто замолкает на середине.',
    },
    {
        key: 'tone',
        label: 'Тон',
        placeholder: 'Ровный, чуть насмешливый. Не повышает голос, даже когда злится.',
    },
    {
        key: 'attitude',
        label: 'Отношение',
        placeholder: 'Держит дистанцию. Отвечает вопросами. Не доверяет, но не показывает.',
    },
    {
        key: 'habits',
        label: 'Привычки',
        placeholder: 'Крутит кольцо на пальце. Барабанит по столу, когда думает.',
    },
    {
        key: 'voice',
        label: 'Голос',
        placeholder: 'Тихий, с хрипотцой. Смеётся редко и коротко.',
    },
    {
        key: 'distinctive_detail',
        label: 'Отличительная деталь',
        placeholder: 'Пахнет ладаном и старой бумагой.',
    },
    {
        key: 'title',
        label: 'Титул',
        placeholder: 'Князь Камарильи, Священник Шабаша — как представляют на сцене, не путать с именем.',
    },
];

function optionalId(value) {
    return value === '' ? null : Number(value);
}

export function useCharacterSheet(characterId) {
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
        lore_clearance_levels: [0],
    });
    const newDisciplineId = ref(null);
    const traits = ref([]);
    let traitSeq = 0;
    const flash = reactive({
        identity: false,
        biography: false,
        traits: false,
        place: false,
    });
    const { flash: flashSavedKey } = useFlash();
    const sects = computed(() => catalog.value?.sects ?? []);
    const clans = computed(() => catalog.value?.clans ?? []);
    const bloodlines = computed(() => catalog.value?.bloodlines ?? []);
    const statsMap = computed(() => {
        const map = {};
        for (const stat of sheet.value?.stats ?? []) {
            map[`${stat.category}:${stat.stat_key}`] = stat.value;
        }
        return map;
    });

    const disciplineRows = computed(() => sheet.value?.disciplines ?? []);
    const unusedDisciplines = computed(() => {
        const known = new Set(disciplineRows.value.map((row) => row.discipline_id));
        return (catalog.value?.disciplineList ?? []).filter((row) => !known.has(row.id));
    });

    function formatSigned(value) {
        if (value > 0) {
            return `+${value}`;
        }
        return String(value);
    }

    function flashSaved(key) {
        flashSavedKey(flash, key);
    }

    function makeTraitRow(row = {}) {
        traitSeq += 1;

        return {
            _id: traitSeq,
            key: row.key ?? '',
            label: row.label ?? '',
            value: row.value ?? '',
            sort_order: row.sort_order ?? 0,
        };
    }

    function isPresetTraitKey(key) {
        return TRAIT_PRESETS.some((preset) => preset.key === key);
    }

    function applyTraitsFromSheet(characterTraits) {
        const fromApi = characterTraits ?? [];
        const byKey = new Map();
        for (const row of fromApi) {
            const key = row.key ?? '';
            if (key !== '') {
                byKey.set(key, row);
            }
        }

        const rows = TRAIT_PRESETS.map((preset, index) => {
            const saved = byKey.get(preset.key);

            return makeTraitRow({
                key: preset.key,
                label: preset.label,
                value: saved?.value ?? '',
                sort_order: index,
            });
        });

        for (const row of fromApi) {
            const key = row.key ?? '';
            if (key === '' || isPresetTraitKey(key)) {
                continue;
            }
            rows.push(makeTraitRow(row));
        }

        traits.value = rows;
    }

    function addTraitPreset(preset) {
        if (traits.value.some((row) => row.key === preset.key)) {
            return;
        }

        traits.value.push(makeTraitRow({
            key: preset.key,
            label: preset.label,
            value: '',
        }));
    }

    function addCustomTrait() {
        traits.value.push(makeTraitRow());
    }

    function removeTrait(index) {
        const row = traits.value[index];
        if (row && isPresetTraitKey(row.key)) {
            row.value = '';
            return;
        }
        traits.value.splice(index, 1);
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
        });
        applyTraitsFromSheet(character.traits);
    }

    async function load() {
        error.value = '';
        try {
            const requests = [
                api.get(`/characters/${unref(characterId)}`),
                catalog.value
                    ? Promise.resolve({ data: { catalog: catalog.value, disciplines: catalog.value.disciplineList } })
                    : api.get('/character-sheet/catalog'),
            ];
            const [sheetRes, catalogRes] = await Promise.all(requests);
            if (!catalog.value) {
                catalog.value = {
                    ...catalogRes.data.catalog,
                    disciplineList: catalogRes.data.disciplines ?? [],
                    clans: catalogRes.data.clans ?? [],
                    bloodlines: catalogRes.data.bloodlines ?? [],
                    sects: catalogRes.data.sects ?? [],
                };
            }
            if (sheetRes) {
                applySheet(sheetRes.data.character);
            }
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось загрузить лист.');
        }
    }

    async function saveIdentity() {
        error.value = '';
        try {
            const { data } = await api.patch(`/characters/${sheet.value.id}`, { ...identity });
            applySheet(data.character);
            flashSaved('identity');
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось сохранить шапку.');
        }
    }

    async function saveBiography() {
        error.value = '';
        try {
            const { data } = await api.put(`/characters/${sheet.value.id}/biography`, { ...biography });
            applySheet(data.character);
            flashSaved('biography');
        } catch (e) {
            error.value = apiErrorMessage(
                e,
                e.response?.data?.errors?.summary?.[0] ?? 'Не удалось сохранить биографию.',
            );
        }
    }

    async function saveTraits() {
        error.value = '';
        try {
            const payload = traits.value
                .filter((row) => row.value.trim() !== '')
                .map((row, index) => ({
                    key: row.key.trim(),
                    label: row.label.trim(),
                    value: row.value.trim(),
                    sort_order: index,
                }));
            const { data } = await api.put(`/characters/${sheet.value.id}/traits`, { traits: payload });
            applySheet(data.character);
            flashSaved('traits');
        } catch (e) {
            const firstError = Object.values(e.response?.data?.errors ?? {})[0]?.[0];
            error.value = apiErrorMessage(e, firstError ?? 'Не удалось сохранить черты.');
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
        return false;
    }

    async function savePlace() {
        error.value = '';
        try {
            const { data } = await api.put(`/characters/${sheet.value.id}/place`, {
                sect_id: optionalId(place.sect_id),
                clan_id: optionalId(place.clan_id),
            });
            applySheet(data.character);
            flashSaved('place');
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось сохранить место в мире.');
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

    async function removeDiscipline(disciplineId) {
        await writeDisciplines(disciplineId, 0);
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
            error.value = apiErrorMessage(e, 'Не удалось сохранить дисциплины.');
        }
    }

    return {
        load,
        sheet,
        error,
        isStoryteller,
        identity,
        biography,
        place,
        newDisciplineId,
        traits,
        traitPresets: TRAIT_PRESETS,
        flash,
        sects,
        clans,
        disciplineRows,
        unusedDisciplines,
        formatSigned,
        missingPlaceOption,
        saveIdentity,
        saveBiography,
        saveTraits,
        addTraitPreset,
        addCustomTrait,
        removeTrait,
        savePlace,
        setDiscipline,
        addDiscipline,
        removeDiscipline,
    };
}
