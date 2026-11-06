export const sheetStatCatalog = [
    { key: 'strength', label: 'Сила' },
    { key: 'dexterity', label: 'Ловкость' },
    { key: 'stamina', label: 'Выносливость' },
    { key: 'charisma', label: 'Обаяние' },
    { key: 'manipulation', label: 'Манипулирование' },
    { key: 'appearance', label: 'Привлекательность' },
    { key: 'perception', label: 'Восприятие' },
    { key: 'intelligence', label: 'Интеллект' },
    { key: 'wits', label: 'Смекалка' },
    { key: 'alertness', label: 'Бдительность' },
    { key: 'athletics', label: 'Атлетика' },
    { key: 'brawl', label: 'Драка' },
    { key: 'dodge', label: 'Уклонение' },
    { key: 'empathy', label: 'Эмпатия' },
    { key: 'expression', label: 'Экспрессия' },
    { key: 'intimidation', label: 'Запугивание' },
    { key: 'leadership', label: 'Лидерство' },
    { key: 'streetwise', label: 'Знание улиц' },
    { key: 'subterfuge', label: 'Хитрость' },
    { key: 'animal_ken', label: 'Знание животных' },
    { key: 'crafts', label: 'Ремёсла' },
    { key: 'drive', label: 'Вождение' },
    { key: 'etiquette', label: 'Этикет' },
    { key: 'firearms', label: 'Огнестрельное оружие' },
    { key: 'melee', label: 'Холодное оружие' },
    { key: 'performance', label: 'Исполнение' },
    { key: 'security', label: 'Безопасность' },
    { key: 'stealth', label: 'Скрытность' },
    { key: 'survival', label: 'Выживание' },
    { key: 'academics', label: 'Академические знания' },
    { key: 'computer', label: 'Компьютер' },
    { key: 'finance', label: 'Финансы' },
    { key: 'investigation', label: 'Расследование' },
    { key: 'law', label: 'Закон' },
    { key: 'linguistics', label: 'Лингвистика' },
    { key: 'medicine', label: 'Медицина' },
    { key: 'occult', label: 'Оккультизм' },
    { key: 'politics', label: 'Политика' },
    { key: 'science', label: 'Наука' },
    { key: 'conscience', label: 'Совесть' },
    { key: 'self_control', label: 'Самоконтроль' },
    { key: 'courage', label: 'Смелость' },
    { key: 'humanity', label: 'Человечность' },
    { key: 'willpower', label: 'Сила воли' },
    { key: 'temporary_willpower', label: 'Временная воля' },
    { key: 'blood_pool', label: 'Запас крови' },
];

export function canonicalStatKey(name) {
    const trimmed = String(name ?? '').trim();
    if (trimmed === '') {
        return '';
    }

    const byKey = sheetStatCatalog.find((row) => row.key === trimmed);
    if (byKey) {
        return byKey.key;
    }

    const folded = trimmed.toLocaleLowerCase('ru');
    const byLabel = sheetStatCatalog.find((row) => row.label.toLocaleLowerCase('ru') === folded);

    return byLabel?.key ?? trimmed;
}

export function sheetStatLabel(key) {
    return sheetStatCatalog.find((row) => row.key === key)?.label ?? key;
}

export function sheetStatSuggestions(query, takenKeys = []) {
    const needle = String(query ?? '').trim().toLocaleLowerCase('ru');
    if (needle === '') {
        return [];
    }

    const taken = new Set(takenKeys);

    return sheetStatCatalog.filter((row) => {
        if (taken.has(row.key)) {
            return false;
        }

        return row.label.toLocaleLowerCase('ru').includes(needle)
            || row.key.includes(needle);
    });
}
