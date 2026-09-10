<?php

namespace Database\Factories\IAM;

use App\Models\IAM\PostReporting;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostReportingFactory extends Factory
{
    protected $model = PostReporting::class;

    public function definition(): array
    {
        return [
            'from_post_code' => 'NKH-SLS-SHW-FSC-001',
            'to_post_code'   => 'NKH-SLS-SHW-SM-001',
            'topic'          => 'sales',
            'param_type'     => null,
            'param_value'    => null,
            'from_date'      => now()->subYear()->toDateString(),
            'to_date'        => null,
            'priority'       => 1,
            'notes'          => null,
            'created_by'     => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'from_date' => now()->subYears(2)->toDateString(),
            'to_date'   => now()->subMonth()->toDateString(),
        ]);
    }

    public function withParam(string $type, ?string $value = null): static
    {
        return $this->state([
            'param_type'  => $type,
            'param_value' => $value,
        ]);
    }
}