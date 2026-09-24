<?php

namespace Database\Seeders;

use App\Models\CanonDiscipline;
use Illuminate\Database\Seeder;

class CanonDisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->row('animalism', 'Анимализм',
                'Связь с животным миром, власть над зверем внутри себя и вокруг.',
                false),
            $this->row('auspex', 'Прорицание',
                'Сверхъестественное восприятие: чтение аур, телепатия, предвидение.',
                false),
            $this->row('celerity', 'Стремительность',
                'Сверхчеловеческая скорость и рефлексы.',
                true),
            $this->row('chimerstry', 'Химерия',
                'Создание иллюзий, реальных для жертвы.',
                false),
            $this->row('dementation', 'Помешательство',
                'Передача безумия Малкавиан и манипуляция им.',
                false),
            $this->row('dominate', 'Доминирование',
                'Прямое подчинение воли смертных и сородичей.',
                false),
            $this->row('fortitude', 'Стойкость',
                'Сверхъестественная устойчивость к урону.',
                true),
            $this->row('necromancy', 'Некромантия',
                'Власть над миром мёртвых, тенями и призраками.',
                false),
            $this->row('obtenebration', 'Власть над Тенью',
                'Власть над тенями и бездной.',
                false),
            $this->row('obfuscate', 'Затемнение',
                'Уход от восприятия, маскировка, невидимость.',
                false),
            $this->row('potence', 'Могущество',
                'Сверхъестественная сила.',
                true),
            $this->row('presence', 'Присутствие',
                'Сверхъестественное обаяние, внушение эмоций.',
                false),
            $this->row('protean', 'Превращение',
                'Изменение тела: когти, облик зверя, туман.',
                false),
            $this->row('quietus', 'Смертоносность',
                'Кровяная магия Ассамитов: яды, скрытность, смерть.',
                false),
            $this->row('serpentis', 'Серпентис',
                'Тёмная сила Сета: гипноз, змеиные черты, проклятия.',
                false),
            $this->row('thaumaturgy', 'Тауматургия',
                'Кровавая магия Тремеров: ритуалы и пути.',
                false),
            $this->row('vicissitude', 'Изменчивость',
                'Изменение плоти и костей живых и мёртвых.',
                false),
        ];

        CanonDiscipline::upsert($rows, ['slug'], [
            'name', 'description', 'is_common', 'updated_at',
        ]);
    }

    private function row(string $slug, string $name, string $description, bool $isCommon): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'is_common' => $isCommon,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}