<?php

namespace Database\Factories\Admin;

use App\Models\Admin\EmpPostAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmpPostAssignmentFactory extends Factory
{
    protected $model = EmpPostAssignment::class;

    public function definition(): array
    {
        return [
            'emp_code'        => 'BMPL-' . str_pad($this->faker->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'post_code'       => 'NKH-SLS-SHW-FSC-001',
            'assignment_type' => 'primary',
            'from_date'       => now()->subYear()->toDateString(),
            'to_date'         => null,
            'relieving_type'  => 'onboarding',
            'remarks'         => null,
            'relieved_by'     => null,
            'created_by'      => null,
        ];
    }

    public function closed(string $toDate = null): static
    {
        return $this->state([
            'to_date'        => $toDate ?? now()->subMonth()->toDateString(),
            'relieving_type' => 'transfer',
        ]);
    }

    public function additional(): static
    {
        return $this->state(['assignment_type' => 'additional']);
    }

    public function forEmployee(string $empCode): static
    {
        return $this->state(['emp_code' => $empCode]);
    }

    public function forPost(string $postCode): static
    {
        return $this->state(['post_code' => $postCode]);
    }
}