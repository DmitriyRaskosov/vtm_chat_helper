<template>
    <aside class="chat-character-drawer card">
        <header class="chat-panel-header chat-character-drawer-header">
            <h2>{{ title }}</h2>
            <button type="button" class="chat-panel-close" aria-label="Закрыть" @click="emit('close')">×</button>
        </header>

        <div v-if="isStoryteller" class="chat-character-drawer-select">
            <label for="chat-character-select">Персонаж в сцене</label>
            <select
                id="chat-character-select"
                :value="selectedCharacterId ?? ''"
                @change="onSelectChange"
            >
                <option v-for="row in participants" :key="row.character_id" :value="row.character_id">
                    {{ row.character_name }}
                </option>
            </select>
        </div>

        <div v-if="selectedCharacterId" class="chat-character-drawer-body">
            <SheetImageSection :character-id="selectedCharacterId" :can-upload="false" start-open />
            <section class="chat-drawer-stats">
                <h3>Состояние</h3>
                <p v-if="loading" class="muted">Загрузка…</p>
                <p v-if="error" class="error">{{ error }}</p>

                <h4>Запас крови</h4>
                <StatDots
                    label=""
                    :max="10"
                    pool
                    :dots="track('blood_pool', 10)"
                    :locked="statsLocked"
                    @pick="onDotClick('blood_pool', $event, 10)"
                />

                <h4>Сила воли</h4>
                <StatDots
                    label="Постоянная"
                    :max="10"
                    pool
                    :dots="track('willpower', 10)"
                    :locked="statsLocked"
                    @pick="onDotClick('willpower', $event, 10)"
                />
                <StatDots
                    label="Временная"
                    :max="10"
                    pool
                    :dots="track('temporary_willpower', 10)"
                    :locked="statsLocked"
                    @pick="onDotClick('temporary_willpower', $event, 10)"
                />

                <div class="chat-drawer-health">
                    <label>
                        Health
                        <select v-model.number="healthLevel" :disabled="statsLocked" @change="onHealthChange">
                            <option v-for="option in drawerHealthLevels" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <fieldset class="chat-drawer-damage" :disabled="statsLocked">
                        <legend class="muted">Тип урона</legend>
                        <label>
                            <input v-model="healthDamage" type="radio" value="bashing" @change="onHealthChange" />
                            Bashing
                        </label>
                        <label>
                            <input v-model="healthDamage" type="radio" value="lethal" @change="onHealthChange" />
                            Lethal
                        </label>
                        <label>
                            <input v-model="healthDamage" type="radio" value="aggravated" @change="onHealthChange" />
                            Aggravated
                        </label>
                    </fieldset>
                    <p class="chat-drawer-penalty">Штраф к кубам: {{ healthPenalty }}</p>
                </div>
            </section>
        </div>
        <p v-else class="muted">Выберите персонажа.</p>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { drawerHealthLevels, useChatCharacterStats } from '../../composables/useChatCharacterStats';
import SheetImageSection from '../sheet/SheetImageSection.vue';
import StatDots from '../sheet/StatDots.vue';

const props = defineProps({
    isStoryteller: { type: Boolean, required: true },
    participants: { type: Array, default: () => [] },
    selectedCharacterId: { type: [Number, String], default: null },
    myCharacterId: { type: [Number, String], default: null },
    sceneId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'select-character']);

const title = computed(() => {
    if (!props.selectedCharacterId) {
        return 'Персонаж';
    }

    const row = props.participants.find(
        (item) => Number(item.character_id) === Number(props.selectedCharacterId),
    );

    return row?.character_name ?? 'Персонаж';
});

const canEditState = computed(() => {
    if (props.isStoryteller) {
        return true;
    }

    if (props.myCharacterId === null || props.selectedCharacterId === null) {
        return false;
    }

    return Number(props.myCharacterId) === Number(props.selectedCharacterId);
});

function onSelectChange(event) {
    const value = Number(event.target.value);

    emit('select-character', Number.isFinite(value) ? value : null);
}

const {
    loading,
    saving,
    error,
    healthLevel,
    healthDamage,
    healthPenalty,
    track,
    onDotClick,
    onHealthChange,
} = useChatCharacterStats(
    () => props.selectedCharacterId,
    canEditState,
    () => props.sceneId,
);

const statsLocked = computed(() => !canEditState.value || saving.value || loading.value);
</script>

<style scoped>
.chat-drawer-stats h3,
.chat-drawer-stats h4 {
    margin: 0.75rem 0 0.4rem;
}

.chat-drawer-health {
    display: grid;
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.chat-drawer-health select {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
}

.chat-drawer-damage {
    margin: 0;
    padding: 0;
    border: none;
}

.chat-drawer-damage label {
    margin-right: 0.75rem;
}

.chat-drawer-penalty {
    margin: 0;
}
</style>
