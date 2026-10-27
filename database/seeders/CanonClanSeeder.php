<?php

namespace Database\Seeders;

use App\Models\CanonClan;
use Illuminate\Database\Seeder;

class CanonClanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->row('assamite', 'Ассамиты', 'Ассасины', 'Клан наёмных убийц с Ближнего Востока.'),
            $this->row('brujah', 'Бруха', 'Сброд', 'Клан бунтарей, страстных и склонных к насилию.'),
            $this->row('followers_of_set', 'Последователи Сета', 'Змеи', 'Культисты древнего бога Сета, мастера соблазна и разложения.'),
            $this->row('gangrel', 'Гангрелы', 'Дикари', 'Одиночки, близкие к дикой природе.'),
            $this->row('giovanni', 'Джованни', 'Некроманты', 'Династия некромантов из Венеции.'),
            $this->row('lasombra', 'Ласомбра', 'Сторожа', 'Клан власти Шабаша, повелители теней.'),
            $this->row('malkavian', 'Малкавиан', 'Безумцы', 'Клан пророков и безумцев.'),
            $this->row('nosferatu', 'Носферату', 'Канализационные Крысы', 'Клан информационных брокеров, изуродованных Проклятием.'),
            $this->row('ravnos', 'Равнос', 'Обманщики', 'Клан кочевников, иллюзионистов и мошенников.'),
            $this->row('toreador', 'Тореадоры', 'Вырожденцы', 'Клан художников, эстетов и светских львов.'),
            $this->row('tremere', 'Тремеры', 'Колдуны', 'Клан кровавых чародеев и ритуальной магии.'),
            $this->row('tzimisce', 'Цимисхи', 'Изверги', 'Клан трансформаторов плоти, древние монстры Восточной Европы.'),
            $this->row('ventrue', 'Вентру', 'Аристократы', 'Клан синих кровей, лидеров и правителей.'),
            $this->row('caitiff', 'Кайтифф', 'Бесклановые', 'Отверженные, не унаследовавшие клановых черт. Слабокровные.'),
        ];

        CanonClan::upsert($rows, ['slug'], [
            'name',
            'nickname',
            'description',
            'weakness',
            'weakness_system',
            'parent_clan_id',
            'is_bloodline',
            'is_playable',
            'updated_at',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $slug, string $name, string $nickname, string $description): array
    {
        $now = now();

        return [
            'slug' => $slug,
            'name' => $name,
            'nickname' => $nickname,
            'description' => $description,
            'weakness' => '',
            'weakness_system' => '',
            'parent_clan_id' => null,
            'is_bloodline' => false,
            'is_playable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
