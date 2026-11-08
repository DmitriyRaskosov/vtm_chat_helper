<template>
    <div v-if="open" class="diary-backdrop" @click.self="open = false">
        <div class="diary-modal card context-map-modal">
            <header class="diary-modal-header">
                <h2>Карта контекста</h2>
                <button type="button" class="link" @click="open = false">Закрыть</button>
            </header>
            <p class="muted context-map-lead">
                Какие блоки данных попадают в запрос к модели. Порядок user-сообщения у Copilot совпадает
                с порядком секций в ContextAssembler.
            </p>
            <div class="context-map-passes">
                <section v-for="pass in passes" :key="pass.id" class="context-map-pass">
                    <h3>{{ pass.title }}</h3>
                    <p class="muted context-map-subtitle">{{ pass.subtitle }}</p>
                    <ol class="context-map-steps">
                        <li
                            v-for="step in pass.steps"
                            :key="step.key"
                            class="context-map-step"
                            :class="{ 'context-map-step-optional': step.optional }"
                        >
                            <span class="context-map-step-key">{{ step.key }}</span>
                            <span class="context-map-step-hint">{{ step.hint }}</span>
                            <span v-if="step.optional" class="context-map-optional-badge">по бюджету</span>
                        </li>
                    </ol>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
const open = defineModel({ type: Boolean, default: false });

const passes = [
    {
        id: 'topics',
        title: 'Copilot Topics',
        subtitle: 'Первый проход: извлечь темы для RAG',
        steps: [
            { key: 'system', hint: 'Системный промпт copilot.topics.system (роль system).' },
            { key: 'npc_identity', hint: 'Имя NPC, клан, тип персонажа.' },
            { key: 'scene', hint: 'Активная сцена, атмосфера, локация, заметки Мастера.' },
            { key: 'character_state', hint: 'Кровь и здоровье NPC.' },
            { key: 'inventory', hint: 'Предметы в инвентаре.' },
            { key: 'storyteller_prompt', hint: 'Текст из поля Copilot (что сделать NPC).' },
            { key: 'recent_messages', hint: 'Недавние сообщения чата — подбираются по лимиту токенов.' },
            { key: 'closing', hint: 'Короткая закрывающая инструкция для прохода Topics.' },
        ],
    },
    {
        id: 'reply',
        title: 'Copilot Reply',
        subtitle: 'Второй проход: черновики реплик с подставленным RAG',
        steps: [
            { key: 'system', hint: 'Системный промпт copilot.reply.system (роль system).' },
            { key: 'npc_identity', hint: 'Имя NPC, клан, тип персонажа.' },
            { key: 'scene', hint: 'Сцена и окружение.' },
            { key: 'character_state', hint: 'Кровь и здоровье.' },
            { key: 'inventory', hint: 'Инвентарь.' },
            { key: 'lore_rag', hint: 'Выдержки из канона по темам первого прохода.', optional: true },
            { key: 'world_lore', hint: 'Лор мира хроники (если включён).', optional: true },
            { key: 'storyteller_prompt', hint: 'Промпт рассказчика.' },
            { key: 'recent_messages', hint: 'История чата (сжимается под бюджет).' },
            { key: 'diary', hint: 'Релевантные записи дневника NPC.', optional: true },
            { key: 'direct_relations', hint: 'Связи с другими персонажами и сущностями.', optional: true },
            { key: 'personality', hint: 'Черты личности с листа.', optional: true },
            { key: 'biography', hint: 'Биография и краткое резюме.', optional: true },
            { key: 'closing', hint: 'Закрывающая инструкция для Reply.' },
        ],
    },
    {
        id: 'diary',
        title: 'Diary Writer',
        subtitle: 'Запись L0 в дневник (без ContextAssembler)',
        steps: [
            { key: 'system', hint: 'Промпт diary.writer.system — правила от первого лица.' },
            { key: 'user · identity', hint: 'Имя, клан, секта NPC.' },
            { key: 'user · traits', hint: 'Черты с листа персонажа.' },
            { key: 'user · bio', hint: 'Краткая биография (summary).' },
            { key: 'user · previousEntryOrNone', hint: 'Предыдущая запись дневника или пометка о первой.' },
            { key: 'user · formattedMessages', hint: 'Сообщения сцены между fromMessageId и toMessageId.' },
        ],
    },
];
</script>
