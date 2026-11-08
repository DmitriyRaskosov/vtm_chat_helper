<?php

namespace App\Prompt;

use App\Models\PromptOverride;
use App\Prompt\Exceptions\PromptNotFoundException;

final class PromptWorkshopService
{
    public function __construct(
        private PromptRepository $prompts,
    ) {}

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     variables: list<string>,
     *     group: string,
     *     content: string,
     *     is_overridden: bool,
     *     is_json_required: bool
     * }>
     */
    public function list(): array
    {
        /** @var array<string, array{title: string, description: string, variables: list<string>, group: string, is_json_required?: bool}> $metadata */
        $metadata = config('prompts_metadata', []);

        /** @var array<string, string> $overrides */
        $overrides = PromptOverride::query()->pluck('content', 'key')->all();

        $items = [];
        foreach ($metadata as $key => $meta) {
            $items[] = $this->item($key, $meta, $overrides[$key] ?? null);
        }

        usort($items, fn (array $a, array $b): int => [$a['group'], $a['key']] <=> [$b['group'], $b['key']]);

        return $items;
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     variables: list<string>,
     *     group: string,
     *     content: string,
     *     is_overridden: bool,
     *     is_json_required: bool
     * }
     */
    public function save(string $key, string $content): array
    {
        $meta = $this->metadataFor($key);

        PromptOverride::query()->updateOrCreate(
            ['key' => $key],
            ['content' => $content],
        );

        return $this->item($key, $meta, $content);
    }

    public function reset(string $key): void
    {
        $this->metadataFor($key);

        PromptOverride::query()->where('key', $key)->delete();
    }

    /**
     * @return array{title: string, description: string, variables: list<string>, group: string, is_json_required?: bool}
     */
    private function metadataFor(string $key): array
    {
        /** @var array<string, array{title: string, description: string, variables: list<string>, group: string, is_json_required?: bool}> $metadata */
        $metadata = config('prompts_metadata', []);

        if (! array_key_exists($key, $metadata)) {
            throw new PromptNotFoundException($key);
        }

        return $metadata[$key];
    }

    /**
     * @param  array{title: string, description: string, variables: list<string>, group: string, is_json_required?: bool}  $meta
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     variables: list<string>,
     *     group: string,
     *     content: string,
     *     is_overridden: bool,
     *     is_json_required: bool
     * }
     */
    private function item(string $key, array $meta, ?string $overrideContent): array
    {
        $isOverridden = $overrideContent !== null;

        return [
            'key' => $key,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'variables' => $meta['variables'],
            'group' => $meta['group'],
            'content' => $isOverridden ? $overrideContent : $this->prompts->get($key),
            'is_overridden' => $isOverridden,
            'is_json_required' => (bool) ($meta['is_json_required'] ?? false),
        ];
    }
}
