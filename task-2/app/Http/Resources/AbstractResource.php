<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class AbstractResource extends JsonResource
{
    public static $wrap = null;

    protected string $status;
    protected int $code;

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode($this->code);
    }
}
