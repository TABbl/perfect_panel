<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SuccessResource extends AbstractResource
{
    public function __construct(private readonly array $payload, int $code = 200)
    {
        parent::__construct($payload);
        $this->status = 'success';
        $this->code = $code;
    }

    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'code' => $this->code,
            'data' => $this->payload,
        ];
    }
}
