<?php

namespace App\Enums;

enum DiaryEmotion: string
{
    case Neutral = 'neutral';
    case Joy = 'joy';
    case Sadness = 'sadness';
    case Anger = 'anger';
    case Fear = 'fear';
    case Trust = 'trust';
    case Contempt = 'contempt';
    case Resolve = 'resolve';
    case Hunger = 'hunger';
    case Satisfaction = 'satisfaction';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $e): string => $e->value, self::cases());
    }

    /**
     * Нормализует строку от LLM: прямое совпадение, алиас, либо null.
     */
    public static function tryFromAlias(string $value): ?self
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $direct = self::tryFrom($value);
        if ($direct !== null) {
            return $direct;
        }

        $canonical = self::ALIASES[$value] ?? null;

        return $canonical === null ? null : self::from($canonical);
    }

    public function label(): string
    {
        return match ($this) {
            self::Neutral => 'Нейтрально',
            self::Joy => 'Радость',
            self::Sadness => 'Печаль',
            self::Anger => 'Гнев',
            self::Fear => 'Страх',
            self::Trust => 'Доверие',
            self::Contempt => 'Презрение',
            self::Resolve => 'Решимость',
            self::Hunger => 'Голод',
            self::Satisfaction => 'Удовлетворение',
        };
    }

    /**
     * Алиасы для нормализации ответов LLM. Расширять по мере наблюдений.
     * Если решаем выделить алиас в самостоятельную эмоцию — убираем из этой
     * мапы и добавляем case выше.
     */
    private const ALIASES = [
        // гнев
        'fury' => 'anger',
        'rage' => 'anger',
        'wrath' => 'anger',
        'annoyance' => 'anger',
        'irritation' => 'anger',
        'resentment' => 'anger',
        'hostility' => 'anger',
        // страх
        'terror' => 'fear',
        'dread' => 'fear',
        'anxiety' => 'fear',
        'panic' => 'fear',
        'unease' => 'fear',
        // доверие
        'adoration' => 'trust',
        'affection' => 'trust',
        'love' => 'trust',
        'fondness' => 'trust',
        'attachment' => 'trust',
        // радость
        'happiness' => 'joy',
        'delight' => 'joy',
        'triumph' => 'joy',
        'relief' => 'joy',
        'elation' => 'joy',
        'excitement' => 'joy',
        // печаль
        'grief' => 'sadness',
        'melancholy' => 'sadness',
        'sorrow' => 'sadness',
        'loss' => 'sadness',
        'regret' => 'sadness',
        // презрение
        'disdain' => 'contempt',
        'scorn' => 'contempt',
        'arrogance' => 'contempt',
        'mockery' => 'contempt',
        'disgust' => 'contempt',
        // решимость
        'determination' => 'resolve',
        'commitment' => 'resolve',
        'oath' => 'resolve',
        'vow' => 'resolve',
        'conviction' => 'resolve',
        // голод
        'craving' => 'hunger',
        'thirst' => 'hunger',
        'desire' => 'hunger',
        'longing' => 'hunger',
        'appetite' => 'hunger',
        // удовлетворение
        'contentment' => 'satisfaction',
        'fulfillment' => 'satisfaction',
        'pride' => 'satisfaction',
        'gratification' => 'satisfaction',
    ];
}
