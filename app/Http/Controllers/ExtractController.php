<?php

namespace App\Http\Controllers;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Extractor\ExtractionCandidateService;
use App\Extractor\ExtractionDisabledException;
use App\Extractor\ExtractionParseException;
use App\Extractor\ExtractionTokenLimitException;
use App\Extractor\GraphExtractorService;
use App\Http\Requests\ManageExtractionCandidateRequest;
use App\Http\Requests\PatchExtractionCandidateRequest;
use App\Http\Requests\RunExtractionRequest;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\LoreEntry;
use App\Models\Scene;
use App\Models\WorldEntity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExtractController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => (string) config('extractor.driver') === 'ollama',
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        $statuses = $request->filled('status')
            ? [(string) $request->query('status')]
            : array_map(
                fn (ExtractionRunStatus $status): string => $status->value,
                ExtractionRunStatus::inboxDefault(),
            );

        $count = ExtractionRun::query()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('status', $statuses)
            ->count();

        if ($request->boolean('count_only')) {
            return response()->json(['count' => $count]);
        }

        $runs = ExtractionRun::query()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('status', $statuses)
            ->orderByDesc('id')
            ->get();

        $sourceLabels = $this->resolveInboxSourceLabels($runs);

        return response()->json([
            'count' => $count,
            'runs' => $runs->map(fn (ExtractionRun $run): array => $this->serializeInboxRun(
                $run,
                (string) ($sourceLabels[$run->source_type->value][$run->source_id] ?? ''),
            ))->values(),
        ]);
    }

    public function store(RunExtractionRequest $request, GraphExtractorService $extractor): JsonResponse
    {
        if ((string) config('extractor.driver') === 'none') {
            return response()->json(['message' => 'Graph extractor is disabled.'], 503);
        }

        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );
        $chronicle = Chronicle::query()->findOrFail($chronicleId);

        try {
            if ($request->filled('character_id')) {
                $character = Character::query()->findOrFail((int) $request->validated('character_id'));
                if ((int) $character->chronicle_id !== $chronicleId) {
                    abort(404);
                }

                $run = $extractor->runFromBiography($character, $chronicle, $request->user());
            } elseif ($request->filled('scene_id')) {
                $scene = Scene::query()
                    ->with('gameSession')
                    ->findOrFail((int) $request->validated('scene_id'));

                if ((int) $scene->gameSession->chronicle_id !== $chronicleId) {
                    abort(404);
                }

                $run = $extractor->runFromSceneManual(
                    $scene,
                    $chronicle,
                    $request->user(),
                    $request->filled('from_message_id') ? $request->integer('from_message_id') : null,
                    $request->filled('to_message_id') ? $request->integer('to_message_id') : null,
                );
            } else {
                $loreEntry = LoreEntry::query()->findOrFail((int) $request->validated('lore_entry_id'));

                if ((int) $loreEntry->chronicle_id !== $chronicleId) {
                    abort(404);
                }

                $run = $extractor->runFromLore($loreEntry, $chronicle, $request->user());
            }
        } catch (ExtractionDisabledException) {
            return response()->json(['message' => 'Graph extractor is disabled.'], 503);
        } catch (ExtractionTokenLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (ConnectionException|RequestException $e) {
            if ($this->isOllamaTimeout($e)) {
                $seconds = (int) config('extractor.http_timeout_seconds');

                return response()->json([
                    'message' => "Extractor request timed out after {$seconds} seconds. Try again or shorten the source slice.",
                ], 503);
            }

            return response()->json(['message' => 'Ollama is unavailable.'], 503);
        } catch (ExtractionParseException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (RuntimeException $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            if ($e->getMessage() === 'Ollama is unavailable.') {
                return response()->json(['message' => 'Ollama is unavailable.'], 503);
            }

            return response()->json(['message' => $e->getMessage()], 502);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'extraction_run_id' => $run->id,
            'run' => $this->serialize($run),
        ], 201);
    }

    public function reparse(Request $request, ExtractionRun $run, GraphExtractorService $extractor): JsonResponse
    {
        $this->assertChronicle($request, $run);

        if ($run->source_type !== ExtractionSourceType::Scene) {
            abort(422, 'Only scene extraction runs can be reparsed.');
        }

        $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);

        try {
            $replacement = $extractor->reparseSceneRun($run, $chronicle, $request->user());
        } catch (ExtractionDisabledException) {
            return response()->json(['message' => 'Graph extractor is disabled.'], 503);
        } catch (ExtractionParseException|ExtractionTokenLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (ConnectionException|RequestException $e) {
            return response()->json(['message' => 'Ollama is unavailable.'], 503);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'extraction_run_id' => $replacement->id,
            'run' => $this->serialize($replacement),
        ], 201);
    }

    public function show(Request $request, ExtractionRun $run): JsonResponse
    {
        $this->assertChronicle($request, $run);

        return response()->json(['run' => $this->serialize($run)]);
    }

    public function accept(
        ManageExtractionCandidateRequest $request,
        ExtractionRun $run,
        int $index,
        ExtractionCandidateService $candidates,
    ): JsonResponse {
        $this->assertChronicle($request, $run);

        try {
            $run = $candidates->accept(
                $run,
                (string) $request->validated('candidate_type'),
                $index,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['run' => $this->serialize($run)]);
    }

    public function discard(
        ManageExtractionCandidateRequest $request,
        ExtractionRun $run,
        int $index,
        ExtractionCandidateService $candidates,
    ): JsonResponse {
        $this->assertChronicle($request, $run);

        try {
            $run = $candidates->discard(
                $run,
                (string) $request->validated('candidate_type'),
                $index,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['run' => $this->serialize($run)]);
    }

    public function patch(
        PatchExtractionCandidateRequest $request,
        ExtractionRun $run,
        int $index,
        ExtractionCandidateService $candidates,
    ): JsonResponse {
        $this->assertChronicle($request, $run);

        $validated = $request->validated();
        $patch = [];

        if (array_key_exists('name', $validated)) {
            $patch['name'] = $validated['name'];
        }

        if (array_key_exists('kind', $validated)) {
            $patch['kind'] = $validated['kind'];
        }

        if (array_key_exists('alias_of_entity_id', $validated)) {
            $patch['alias_of_entity_id'] = $validated['alias_of_entity_id'];
        }

        if (array_key_exists('sect_faction_id', $validated)) {
            $patch['sect_faction_id'] = $validated['sect_faction_id'];
        }

        if (array_key_exists('aliases', $validated)) {
            $patch['aliases'] = $validated['aliases'];
        }

        try {
            $run = $candidates->patchMention($run, $index, $patch);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['run' => $this->serialize($run)]);
    }

    private function isOllamaTimeout(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }

    private function assertChronicle(Request $request, ExtractionRun $run): void
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $run->chronicle_id !== $chronicleId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ExtractionRun $run): array
    {
        return [
            'id' => (int) $run->id,
            'chronicle_id' => (int) $run->chronicle_id,
            'source_type' => $run->source_type->value,
            'source_id' => (int) $run->source_id,
            'status' => $run->status?->value ?? ExtractionRunStatus::NeedsReview->value,
            'trigger' => $run->trigger?->value,
            'from_message_id' => $run->from_message_id === null ? null : (int) $run->from_message_id,
            'to_message_id' => $run->to_message_id === null ? null : (int) $run->to_message_id,
            'message_count' => $run->from_message_id !== null && $run->to_message_id !== null
                ? $this->messageCountForRun($run)
                : null,
            'driver' => $run->driver,
            'model' => $run->model,
            'raw_response' => $run->raw_response,
            'candidates' => $run->candidates,
            'user_id' => (int) $run->user_id,
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInboxRun(ExtractionRun $run, string $sourceLabel): array
    {
        $payload = $this->serialize($run);
        $payload['source_label'] = $sourceLabel;
        $payload['scene_title'] = $run->source_type === ExtractionSourceType::Scene ? $sourceLabel : null;

        return $payload;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExtractionRun>  $runs
     * @return array<string, array<int, string>>
     */
    private function resolveInboxSourceLabels(\Illuminate\Support\Collection $runs): array
    {
        $labels = [
            ExtractionSourceType::Scene->value => [],
            ExtractionSourceType::Lore->value => [],
            ExtractionSourceType::Biography->value => [],
        ];

        $sceneIds = $runs
            ->filter(fn (ExtractionRun $run): bool => $run->source_type === ExtractionSourceType::Scene)
            ->pluck('source_id')
            ->unique()
            ->all();

        if ($sceneIds !== []) {
            $labels[ExtractionSourceType::Scene->value] = Scene::query()
                ->whereIn('id', $sceneIds)
                ->pluck('title', 'id')
                ->all();
        }

        $loreIds = $runs
            ->filter(fn (ExtractionRun $run): bool => $run->source_type === ExtractionSourceType::Lore)
            ->pluck('source_id')
            ->unique()
            ->all();

        if ($loreIds !== []) {
            $labels[ExtractionSourceType::Lore->value] = LoreEntry::query()
                ->whereIn('id', $loreIds)
                ->pluck('title', 'id')
                ->all();
        }

        $characterIds = $runs
            ->filter(fn (ExtractionRun $run): bool => $run->source_type === ExtractionSourceType::Biography)
            ->pluck('source_id')
            ->unique()
            ->all();

        if ($characterIds !== []) {
            $labels[ExtractionSourceType::Biography->value] = WorldEntity::query()
                ->whereIn('id', $characterIds)
                ->pluck('canonical_name', 'id')
                ->all();
        }

        return $labels;
    }

    private function messageCountForRun(ExtractionRun $run): int
    {
        return \App\Models\Message::query()
            ->where('scene_id', $run->source_id)
            ->whereBetween('id', [(int) $run->from_message_id, (int) $run->to_message_id])
            ->count();
    }
}
