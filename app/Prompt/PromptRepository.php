<?php

namespace App\Prompt;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PromptRepository
{
    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @param  array<string, string|int|float>  $vars
     */
    public function get(string $key, array $vars = []): string
    {
        $content = $this->load($key);

        return $vars === [] ? $content : $this->interpolate($content, $vars);
    }

    private function load(string $key): string
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $relativePath = preg_replace('/\./', '/', $key, 1).'.md';
        $absolutePath = resource_path('prompts/'.$relativePath);

        if (! File::exists($absolutePath)) {
            throw new RuntimeException("Prompt not found: {$key} ({$absolutePath})");
        }

        return $this->cache[$key] = File::get($absolutePath);
    }

    /**
     * @param  array<string, string|int|float>  $vars
     */
    private function interpolate(string $content, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $name => $value) {
            $replacements['{{'.$name.'}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }
}