<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ErrorResource extends AbstractResource
{
    public function __construct(private readonly string $message, int $code = 400)
    {
        parent::__construct([]);
        $this->status = 'error';
        $this->code = $code;
    }

    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
