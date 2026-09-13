<?php

namespace App\Retrieval\Hybrid;

final readonly class RetrievalBundle
{
    /**
     * @param  list<RetrievalHit>  $hits
     */
    public function __construct(public array $hits) {}
}
