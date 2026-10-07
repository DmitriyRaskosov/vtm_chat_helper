<?php

namespace Database\Seeders;

use App\Enums\CharacterType;
use App\Enums\ChronicleStatus;
use App\Enums\GameSessionStatus;
use App\Enums\SceneParticipantRole;
use App\Enums\SceneStatus;
use App\Enums\UserRole;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\CanonClan;
use App\Models\CanonSect;
use App\Models\Character;
use App\Models\CharacterTrait;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Models\WorldEntity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $storyteller = User::query()->firstOrCreate(
            ['login' => 'admin'],
            [
                'name' => 'admin',
                'role' => UserRole::Storyteller,
                'password' => Hash::make('admin'),
            ],
        );

        $chronicle = Chronicle::query()->create([
            'title' => 'Demo Chronicle',
            'status' => ChronicleStatus::Active,
            'created_by' => $storyteller->id,
        ]);

        $session = GameSession::query()->create([
            'chronicle_id' => $chronicle->id,
            'title' => 'Demo Session',
            'status' => GameSessionStatus::Active,
            'created_by' => $storyteller->id,
        ]);

        $scene = Scene::query()->create([
            'game_session_id' => $session->id,
            'position' => 1,
            'title' => 'Ночь в Элизиуме',
            'status' => SceneStatus::Active,
            'started_at' => now(),
        ]);

        SceneContext::query()->create([
            'scene_id' => $scene->id,
            'chronicle_id' => $chronicle->id,
            'atmosphere' => 'Старый особняк в центре города. Свечи, пыль, запах старой бумаги.',
            'situation' => 'Три Сородича собрались после нападения Шабаша на соседний Элизиум.',
            'revision' => 0,
        ]);

        $npcs = $this->createNpcs($chronicle);

        foreach ($npcs as $npc) {
            SceneParticipant::query()->create([
                'scene_id' => $scene->id,
                'chronicle_id' => $chronicle->id,
                'character_id' => $npc->id,
                'role' => SceneParticipantRole::Npc,
                'is_current' => true,
                'entered_at' => now(),
            ]);
        }

        $this->seedMessages($scene, $storyteller, $npcs);

        $this->command->info('=== Demo stand ===');
        $this->command->info("Chronicle: {$chronicle->id}");
        $this->command->info("Scene:     {$scene->id}");
        $this->command->info('NPCs:      '.$npcs->pluck('id')->implode(', '));
        $this->command->info('Login:     admin / admin');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Character>
     */
    private function createNpcs(Chronicle $chronicle): \Illuminate\Support\Collection
    {
        $definitions = [
            [
                'name' => 'Абрахам',
                'clan_slug' => 'malkavian',
                'sect_slug' => 'camarilla',
                'nature' => 'Антиквар',
                'demeanor' => 'Историк',
                'concept' => 'Архивариус',
                'generation' => 13,
                'traits' => [
                    ['appearance', 'Внешность', 'Худощавый мужчина, бледная кожа, тёмные круги под глазами. Поношенный, но качественный костюм начала XX века. Пальцы всегда испачканы чернилами, хотя он давно не пишет ручкой. Иногда поправляет невидимые очки. Носит с собой блокнот и каталог записей.'],
                    ['speech', 'Манера речи', 'Размеренная, книжная, немного архаичная. Частые паузы, будто сверяется с невидимым каталогом. Обращения: «сударь», «госпожа», «с вашего позволения». Людей и Сородичей называет «томами», «изданиями».'],
                    ['tone', 'Тон', 'Тихий, ровный, негромкий. Чуть надтреснутый. Редко повышает голос; при волнении переходит на шёпот.'],
                    ['attitude', 'Отношение', 'Вежлив, но дистанцирован. Наблюдает, «сканирует» как новый том. Не доверяет сразу, но не проявляет открытой враждебности.'],
                    ['mannerisms', 'Привычки', 'Постоянно что-то записывает, сортирует, шепчет. Каждую ночь составляет «индекс реальности». Считает в воздухе страницы, поправляет картины.'],
                    ['voice', 'Голос', 'Тихий, с длинными паузами. В стрессе — дрожащий шёпот или обрывки фраз на «языке каталога».'],
                    ['signature', 'Отличительная деталь', 'Безумие — «Синдром Архивариуса»: видит мир как бесконечную библиотеку. Боится пожара и уничтожения книг.'],
                ],
            ],
            [
                'name' => 'Ханна Векслер',
                'clan_slug' => 'nosferatu',
                'sect_slug' => 'camarilla',
                'nature' => 'Одиночка',
                'demeanor' => 'Критик',
                'concept' => 'Коммутатор домена',
                'generation' => 10,
                'traits' => [
                    ['appearance', 'Внешность', 'Сутулая, длиннорукая фигура. Кожа серо-землистая, покрыта грубыми наростами и шрамами от проводов. Лицо асимметричное, нос провален. Одежда: рабочий комбинезон, старая кожаная куртка, армейские ботинки. На плече — моток провода, на поясе — инструменты. Пахнет машинным маслом, ржавчиной, мокрым бетоном.'],
                    ['speech', 'Манера речи', 'Короткие, рубленые фразы. Ничего лишнего. Технические метафоры: «на линии», «помехи», «сброс», «перегрузка». Грубоватая, но без витиеватого мата.'],
                    ['tone', 'Тон', 'Хриплый, тихий, с шумом и свистом — сорванные связки. Громче шёпота почти не может. Иногда говорит совсем без интонации, ровным гулом.'],
                    ['attitude', 'Отношение', 'Неприветлива. Смотрит молча, оценивает, не подпускает ближе вытянутой руки. Жалость воспринимает как оскорбление. Не отвечает на вежливость — отвечает на дело.'],
                    ['mannerisms', 'Привычки', 'Постоянно слушает стены: ладонь на бетоне, пауза, кивок. Стучит по трубам короткими ритмами. Разговаривает с крысами, по-настоящему, по именам. Чистит инструменты, когда нервничает. Сидит спиной к стене, лицом к выходу. Жуёт изоляцию от провода, когда думает.'],
                    ['voice', 'Голос', 'Шёпот с присвистом; иногда слышен только хрип. В ярости — короткий лающий рык. В моменты «гула» — монотонное бормотание частот: «шестьдесят герц… помеха… ушли».'],
                    ['signature', 'Отличительная деталь', 'Прозвище «Кабель». «Гул» — чувствует вибрацию через провода и трубы, слышит сквозь бетон. Всегда носит моток провода как талисман. Единственный, кому доверяет, — крыс-фамильяр по кличке «Ноль».'],
                ],
            ],
            [
                'name' => 'Август Сент-Клер',
                'clan_slug' => 'ventrue',
                'sect_slug' => 'camarilla',
                'nature' => 'Автократ',
                'demeanor' => 'Директор',
                'concept' => 'Примоген, серый кардинал домена',
                'generation' => 8,
                'traits' => [
                    ['appearance', 'Внешность', 'Высокий, подтянутый, идеальная осанка. Седина на висках — сохранил как символ. Глаза серо-голубые, спокойные, смотрят чуть дольше комфортного. Безупречный тёмный костюм, дорогие часы, перстень с печатью. Трость с серебряным набалдашником — не для ходьбы, для жеста. Пахнет дорогим табаком и кожей.'],
                    ['speech', 'Манера речи', 'Отточенная, юридическая, вежливая. Ни одного лишнего слова. Вместо приказов — вопросы, которые всё равно приказы. «Мы» вместо «я», когда решает он один. Никогда не угрожает — описывает последствия.'],
                    ['tone', 'Тон', 'Глубокий, тёплый, ровный. Дикторский. Говорит медленнее, чем нужно, — заставляет ждать и додумывать. Никогда не кричит. В ярости становится тише, а не громче.'],
                    ['attitude', 'Отношение', 'Вежлив ровно настолько, насколько полезен собеседник. Оценивает мгновенно: актив или обуза, должник или кредитор. Никогда не оскорбляет первым. Всегда запоминает имя и лицо. К «низшим» — корректен с прозрачной дистанцией.'],
                    ['mannerisms', 'Привычки', 'Поправляет манжету левой рукой — жест-подпись. Снимает невидимую пылинку с рукава собеседника. Ведёт маленькую книжку долгов. Никогда не садится первым. Никогда не пьёт из одного бокала дважды. Держит трость между собой и собеседником — барьер.'],
                    ['voice', 'Голос', 'Глубокий, тёплый, спокойный. Медленный темп, чёткая дикция, паузы в нужных местах. В ярости — тише, а не громче. Это страшнее.'],
                    ['signature', 'Отличительная деталь', 'Прозвище «Канцлер». Ограничение питания: только добровольно уступившие. Перстень с монограммой — использует как «подпись». Двадцать лет не может подчинить Абрахама Леру. Никогда не говорит о своём сире.'],
                ],
            ],
        ];

        $clans = CanonClan::query()->pluck('id', 'slug');
        $sects = CanonSect::query()->pluck('id', 'slug');

        return collect($definitions)->map(function (array $def) use ($chronicle, $clans, $sects): Character {
            $entity = WorldEntity::query()->create([
                'chronicle_id' => $chronicle->id,
                'entity_type' => WorldEntityType::Character,
                'canonical_name' => $def['name'],
                'slug' => Str::slug($def['name']).'-'.random_int(1000, 9999),
                'status' => WorldEntityStatus::Active,
            ]);

            $character = Character::query()->create([
                'id' => $entity->id,
                'chronicle_id' => $chronicle->id,
                'character_type' => CharacterType::Npc,
                'clan_id' => $clans[$def['clan_slug']] ?? null,
                'sect_id' => $sects[$def['sect_slug']] ?? null,
                'nature' => $def['nature'],
                'demeanor' => $def['demeanor'],
                'concept' => $def['concept'],
                'generation' => $def['generation'],
                'is_active' => true,
            ]);

            foreach ($def['traits'] as $i => [$key, $label, $value]) {
                CharacterTrait::query()->create([
                    'character_id' => $character->id,
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'sort_order' => $i * 10,
                ]);
            }

            return $character;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Character>  $npcs
     */
    private function seedMessages(Scene $scene, User $storyteller, \Illuminate\Support\Collection $npcs): void
    {
        [$abraham, $hanna, $august] = [$npcs[0], $npcs[1], $npcs[2]];

        $script = [
            ['user', null, 'Абрахам, как обстановка в Элизиуме?'],
            ['npc', $abraham->id, 'Тишина, сударь. Но тишина бывает двух видов — когда всё спокойно, и когда все замолчали по одной причине.'],
            ['user', null, 'И какая сейчас?'],
            ['npc', $abraham->id, 'Вторая.'],
            ['user', null, 'Что известно о нападении?'],
            ['npc', $hanna->id, 'Слышала. На линии было три сброса за час. Кто-то резал связь перед атакой.'],
            ['user', null, 'Ты можешь сказать, кто?'],
            ['npc', $hanna->id, 'Не могу. Могу сказать где. В восточном коллекторе. И не спрашивай, как я узнала.'],
            ['npc', $august->id, 'Это неважно, откуда. Важно, что мы теперь знаем. Мы должны усилить наблюдение.'],
            ['user', null, 'Август, ты не боишься эскалации?'],
            ['npc', $august->id, 'Мы не эскалируем. Мы отвечаем в рамках регламента. Если Шабаш нарушил Традицию — мы оформим нарушение.'],
            ['npc', $abraham->id, 'Оформить — не значит защитить, сударь.'],
            ['npc', $august->id, 'Оформить — значит иметь право на ответ. А право сильнее ярости.'],
            ['npc', $hanna->id, 'Пока вы оформляете, они роют. Я остаюсь на линии.'],
            ['user', null, 'Хорошо. Работаем.'],
        ];

        foreach ($script as [$type, $characterId, $body]) {
            $npcName = null;
            if ($type === 'npc' && $characterId !== null) {
                $npcName = WorldEntity::query()->whereKey($characterId)->value('canonical_name');
            }

            Message::query()->create([
                'user_id' => $storyteller->id,
                'scene_id' => $scene->id,
                'body' => $body,
                'npc_name' => $npcName,
                'author_character_id' => $characterId,
            ]);
        }
    }
}