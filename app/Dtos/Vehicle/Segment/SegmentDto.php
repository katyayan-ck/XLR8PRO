<?php

namespace App\Dtos\Vehicle\Segment;

class SegmentDto
{
    public function __construct(
        public string $code,
        public string $name
    ) {
    }

    public static function fromArray(
        array $data
    ): self {
        return new self(
            code: strtoupper(trim($data['code'])),
            name: strtoupper(trim($data['name']))
        );
    }
}