<?php

namespace App\Chronicle\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoChronicleException extends Exception
{
    public function __construct()
    {
        parent::__construct('There is no available chronicle.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
