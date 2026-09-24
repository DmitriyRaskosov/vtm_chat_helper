<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\Message;
use App\Models\WorldEntity;
use Illuminate\Support\Collection;

class RecentMessagesProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'recent_messages';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        return $this->assembleFrom(collect(), $tokenBudget);
    }

    /**
     * @return Collection<int, Message>
     */
    public function candidates(ContextAssembly $assembly): Collection
    {
        return Message::query()
            ->with('user:id,name')
            ->where('scene_id', $assembly->scene->id)
            ->orderByDesc('id')
            ->limit($assembly->request->historyLimit())
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    public function assembleFrom(Collection $messages, int $tokenBudget): ContextSection
    {
        if ($messages->isEmpty()) {
            return ContextSection::omitted($this->key(), [
                'message_ids' => [],
            ]);
        }

        $names = WorldEntity::query()
            ->whereIn(
                'id',
                $messages->pluck('author_character_id')->filter()->unique()->all(),
            )
            ->pluck('canonical_name', 'id');

        $lines = [];
        $ids = [];
        foreach ($messages as $message) {
            $ids[] = (int) $message->id;
            $authorId = $message->author_character_id;
            $canonical = is_numeric($authorId) ? ($names[(int) $authorId] ?? null) : null;
            $author = $message->displayAuthor(is_string($canonical) ? $canonical : null);
            $lines[] = '[speech] '.$author.': '.$message->body;
        }

        [$content, $truncated] = $this->trimmer->prefix('## Scene speech', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'message_ids' => $ids,
            'truncation_policy' => 'oldest_whole_messages',
        ], $truncated ? 'line_budget' : null);
    }
}
