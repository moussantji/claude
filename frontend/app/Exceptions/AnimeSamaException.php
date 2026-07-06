<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Raised when scraping anime-sama fails or the site is unreachable.
 */
class AnimeSamaException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $status = $this->getCode() >= 400 && $this->getCode() < 600
            ? (int) $this->getCode()
            : 502;

        return response()->json([
            'message' => $this->getMessage(),
            'source' => 'anime-sama',
        ], $status);
    }
}
