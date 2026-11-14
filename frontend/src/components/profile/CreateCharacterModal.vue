<template>
    <div v-if="open" class="diary-backdrop" @click.self="open = false">
        <form class="diary-modal card create-character-form" @submit.prevent="submit">
            <header class="diary-modal-header">
                <h2>Новый персонаж</h2>
                <button type="button" class="link" @click="open = false">Закрыть</button>
            </header>

            <p class="muted">Персонаж сохранится в библиотеку. В хронику его можно добавить отдельно.</p>
            <p v-if="error" class="error">{{ error }}</p>

            <label>
                Имя
                <input v-model="name" type="text" maxlength="100" required />
            </label>
            <label v-if="hasAnyStorytellerRole">
                Тип
                <select v-model="characterType">
                    <option value="player">Игрок</option>
                    <option value="npc">НПС</option>
                </select>
            </label>
            <label>
                Клан
                <select v-model="clanId" required>
                    <option value="" disabled>Выберите клан</option>
                    <option v-for="clan in clans" :key="clan.id" :value="String(clan.id)">
                        {{ clan.name }}
                    </option>
                </select>
            </label>
            <label>
                Концепция
                <input v-model="concept" type="text" maxlength="120" />
            </label>

            <button type="submit" :disabled="!canSubmit">
                {{ saving ? 'Создание…' : 'Создать' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { hasAnyStorytellerRole } from '../../auth';
import { useLibraryCharacters } from '../../composables/useLibraryCharacters';
import { useProfile } from '../../composables/useProfile';

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['created']);

const { loadClans } = useProfile();
const { error, saving, createTemplate } = useLibraryCharacters();

const name = ref('');
const clanId = ref('');
const concept = ref('');
const characterType = ref('npc');
const clans = ref([]);

const canSubmit = computed(() => (
    !saving.value
    && name.value.trim() !== ''
    && clanId.value !== ''
));

onMounted(() => {
    loadClans()
        .then((rows) => {
            clans.value = rows;
        })
        .catch(() => {});
});

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    const payload = {
        name: name.value,
        clan_id: Number(clanId.value),
        concept: concept.value,
    };

    if (hasAnyStorytellerRole.value) {
        payload.character_type = characterType.value;
    }

    try {
        const template = await createTemplate(payload);
        emit('created', template);
        open.value = false;
    } catch {
        // error уже в useLibraryCharacters
    }
}
</script>

<style scoped>
.create-character-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.create-character-form button {
    margin-top: 0.25rem;
}
</style>
