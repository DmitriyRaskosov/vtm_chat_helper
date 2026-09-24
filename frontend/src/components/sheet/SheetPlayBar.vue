<template>
    <section class="card play-bar">
        <label>
            Blood
            <input v-model.number="bloodPool" type="number" min="0" max="50" @change="emit('save-status')" />
        </label>
        <label>
            Willpower now
            <input v-model.number="tempWillpower" type="number" min="0" max="10" @change="emit('save-status')" />
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
                        @click="emit('set-health-damage', kind.value)"
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
                    @click="emit('set-health-level', box.index + 1)"
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
            <input v-model.number="experience" type="number" min="0" @change="emit('save-experience')" />
        </label>
    </section>
</template>

<script setup>
import { healthDamageKinds } from '../../composables/useCharacterSheet';

defineProps({
    healthBoxes: { type: Array, required: true },
    healthDamage: { type: String, required: true },
    healthLevel: { type: Number, required: true },
    healthLabel: { type: Function, required: true },
});

const bloodPool = defineModel('bloodPool', { type: Number });
const tempWillpower = defineModel('tempWillpower', { type: Number });
const experience = defineModel('experience', { type: Number });

const emit = defineEmits(['save-status', 'save-experience', 'set-health-level', 'set-health-damage']);
</script>
