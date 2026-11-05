<?php

namespace Tests\Feature;

use App\Context\ContextAssembly;
use App\Context\ContextRequest;
use App\Context\Providers\DiaryProvider;
use App\Models\CharacterDiaryEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesCopilotScene;
use Tests\Support\CreatesDiaryEntries;
use Tests\TestCase;

class DiaryProviderTest extends TestCase
{
    use CreatesCopilotScene;
    use CreatesDiaryEntries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeEmbeddings();
        $this->setUpCopilotScene();
    }

    public function test_diary_section_uses_relative_in_game_time(): void
    {
        $current = Carbon::parse('1999-11-01 00:00:00');
        $this->chronicle->update(['current_in_game_date' => $current]);

        $monthAgo = $this->createDiaryEntry('Текст месячной давности');
        $monthAgo->update(['in_game_date' => Carbon::parse('1999-10-01 00:00:00')]);

        $section = $this->diarySection();
        $this->assertStringContainsString('[diary — 1 month ago] Текст месячной давности', $section);
        $this->assertStringNotContainsString('[diary — earlier]', $section);
        $this->assertStringNotContainsString('[diary — most recent]', $section);

        CharacterDiaryEntry::query()->delete();

        $yearAgo = $this->createDiaryEntry('Текст прошлого года');
        DB::table('character_diary_entries')->where('id', $yearAgo->id)->update([
            'in_game_date' => null,
            'created_at' => '1998-11-01 00:00:00',
        ]);

        $fallback = $this->diarySection();
        $this->assertStringContainsString('[diary — 1 year ago] Текст прошлого года', $fallback);
    }

    private function diarySection(): string
    {
        $assembly = new ContextAssembly(
            new ContextRequest(
                npcName: 'Иван',
                prompt: 'Что он помнит',
                sceneId: $this->sceneId,
                draftCount: 1,
                characterId: $this->npcId,
            ),
            $this->scene,
            $this->gameSession,
            $this->chronicle->fresh(),
            $this->npc,
        );

        $section = app(DiaryProvider::class)->assemble($assembly, 2000);
        $this->assertTrue($section->included);

        return $section->content;
    }
}
