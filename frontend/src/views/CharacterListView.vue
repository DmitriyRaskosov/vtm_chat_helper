<template>
    <div>
        <AppNav current="characters" />

        <p v-if="error" class="error">{{ error }}</p>

        <section v-if="isStoryteller" class="card character-create">
            <h2>Новый персонаж</h2>
            <div class="character-create-grid">
                <label>
                    Имя
                    <input v-model="form.canonical_name" type="text" maxlength="120" />
                </label>
                <label>
                    Тип
                    <select v-model="form.character_type">
                        <option value="player">Игрок</option>
                        <option value="npc">НПС</option>
                    </select>
                </label>
                <label v-if="form.character_type === 'player'">
                    User ID владельца
                    <input v-model.number="form.user_id" type="number" min="1" />
                </label>
            </div>
            <button type="button" :disabled="saving || !form.canonical_name.trim()" @click="createCharacter">
                Создать
            </button>
        </section>

        <section class="card">
            <h2>Листы</h2>
            <p v-if="!characters.length" class="muted">Пока нет персонажей.</p>
            <ul class="character-tree">
                <li v-for="row in characters" :key="row.id">
                    <div class="character-row">
                        <RouterLink :to="characterLink(row.id)">{{ row.canonical_name }}</RouterLink>
                        <span class="muted"> · {{ characterTypeLabel(row.character_type) }}</span>
                        <button
                            v-if="isStoryteller"
                            class="link"
                            type="button"
                            @click="hideCharacter(row.id)"
                        >
                            Скрыть
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="isStoryteller" class="card">
            <h2>Скрытые</h2>
            <p v-if="!archived.length" class="muted">Скрытых персонажей нет.</p>
            <ul v-else class="character-tree">
                <li v-for="row in archived" :key="row.id">
                    <div class="character-row">
                        <RouterLink :to="characterLink(row.id)">{{ row.canonical_name }}</RouterLink>
                        <span class="muted"> · {{ characterTypeLabel(row.character_type) }}</span>
                        <button class="link" type="button" @click="restoreCharacter(row.id)">Вернуть</button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import { useCharacterList } from '../composables/useCharacterList';
import { characterTypeLabel } from '../utils/labels';

const route = useRoute();
const chronicleId = computed(() => route.params.chronicleId);

function characterLink(id) {
    return `/c/${chronicleId.value}/characters/${id}`;
}

const {
    isStoryteller,
    characters,
    archived,
    error,
    saving,
    form,
    load,
    createCharacter,
    hideCharacter,
    restoreCharacter,
} = useCharacterList(chronicleId);

onMounted(load);
</script>
