<?php

namespace Database\Factories\IAM;

use App\Models\IAM\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        $branchCode = $this->faker->randomElement(['NKH', 'BKN', 'JDH']);
        $deptCode   = $this->faker->randomElement(['SLS', 'SVC', 'ACC']);
        $divCode    = $this->faker->randomElement(['SHW', 'FLD']);
        $desigCode  = $this->faker->randomElement(['FSC', 'SM', 'BM', 'TL']);
        $postCode   = "{$branchCode}-{$deptCode}-{$divCode}-{$desigCode}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);

        return [
            'name'          => $postCode,   // Spatie REQUIRES name
            'guard_name'    => 'web',       // Must match model boot default
            'post_code'     => $postCode,
            'display_name'  => "{$desigCode} @ {$branchCode}",
            'is_post'       => true,
            'branch_code'   => $branchCode,
            'dept_code'     => $deptCode,
            'div_code'      => $divCode,
            'desig_code'    => $desigCode,
            'max_occupants' => 1,
            'is_active'     => true,
            'seq_no'        => 1,
        ];
    }

    public function withVacancy(int $max = 2): static
    {
        return $this->state(['max_occupants' => $max]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forPost(string $postCode): static
    {
        return $this->state([
            'post_code' => $postCode,
            'name'      => $postCode,
        ]);
    }
}
