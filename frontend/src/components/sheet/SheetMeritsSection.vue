<template>
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
                <button type="button" class="link" @click="emit('remove', index)">убрать</button>
            </li>
        </ul>
        <p class="merit-totals">
            Merits {{ formatSigned(meritTotals.merits) }}
            · Flaws {{ formatSigned(-meritTotals.flaws) }}
            · Итого {{ formatSigned(meritTotals.net) }}
        </p>
        <div class="sheet-actions">
            <button type="button" class="secondary" @click="emit('add')">Добавить</button>
            <span v-if="flashAdd" class="saved-flash">Добавлено</span>
            <button type="button" @click="emit('save')">Сохранить merits/flaws</button>
            <span v-if="flashSave" class="saved-flash">Сохранено!</span>
        </div>
    </section>
</template>

<script setup>
defineProps({
    merits: { type: Array, required: true },
    meritTotals: { type: Object, required: true },
    meritSigned: { type: Function, required: true },
    formatSigned: { type: Function, required: true },
    flashAdd: { type: Boolean, required: true },
    flashSave: { type: Boolean, required: true },
});

const emit = defineEmits(['add', 'remove', 'save']);
</script>
