<?php

namespace App\Context;

final class LineTrimmer
{
    public function __construct(private TokenEstimator $estimator) {}

    /**
     * Keep a heading plus as many body lines as fit in `$maxTokens`.
     *
     * @param  list<string>  $bodyLines
     * @return array{0: string, 1: bool} content and whether anything was dropped
     */
    public function prefix(string $heading, array $bodyLines, int $maxTokens): array
    {
        $heading = trim($heading);
        $bodyLines = array_values(array_filter(
            array_map(fn (string $line): string => rtrim($line), $bodyLines),
            fn (string $line): bool => $line !== '',
        ));

        if ($maxTokens < 1 || ($heading === '' && $bodyLines === [])) {
            return ['', $bodyLines !== []];
        }

        $kept = [];
        $truncated = false;

        foreach ($bodyLines as $line) {
            $candidate = $this->join($heading, [...$kept, $line]);
            if ($this->estimator->estimate($candidate) > $maxTokens) {
                $truncated = true;
                break;
            }
            $kept[] = $line;
        }

        if ($kept === []) {
            if ($heading !== '' && $this->estimator->estimate($heading) <= $maxTokens && $bodyLines === []) {
                return [$heading, false];
            }

            return ['', $truncated || $bodyLines !== []];
        }

        return [$this->join($heading, $kept), $truncated];
    }

    /**
     * @param  list<string>  $lines
     */
    private function join(string $heading, array $lines): string
    {
        $body = implode("\n", $lines);

        if ($heading === '') {
            return $body;
        }

        if ($body === '') {
            return $heading;
        }

        return $heading."\n".$body;
    }
}
