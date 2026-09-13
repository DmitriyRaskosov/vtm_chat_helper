<?php

namespace App\Context;

final readonly class ContextSection
{
    /**
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public string $key,
        public string $content,
        public int $tokenEstimate,
        public array $provenance,
        public bool $included,
        public ?string $truncation,
    ) {}

    /**
     * @param  array<string, mixed>  $provenance
     */
    public static function fromContent(
        string $key,
        string $content,
        TokenEstimator $estimator,
        array $provenance = [],
        ?string $truncation = null,
    ): self {
        $content = trim($content);
        if ($content === '') {
            return self::omitted($key, $provenance);
        }

        return new self(
            $key,
            $content,
            $estimator->estimate($content),
            $provenance,
            true,
            $truncation,
        );
    }

    /**
     * @param  array<string, mixed>  $provenance
     */
    public static function omitted(string $key, array $provenance = []): self
    {
        return new self($key, '', 0, $provenance, false, null);
    }
}
