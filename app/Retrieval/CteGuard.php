<?php

namespace App\Retrieval;

use Illuminate\Support\Facades\DB;

final class CteGuard
{
    /**
     * Run a CTE (or other graph SQL) inside a transaction with a statement timeout.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            $ms = max(1, (int) config('retrieval.statement_timeout_ms', 2000));
            DB::statement('SET LOCAL statement_timeout = '.$ms);

            return $callback();
        });
    }
}
