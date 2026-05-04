<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'type'        => $this->faker->randomElement(Report::TYPES),
            'latitude'    => $this->faker->latitude(),
            'longitude'   => $this->faker->longitude(),
            'address'     => $this->faker->address(),
            'image_path'  => null,
            'description' => $this->faker->optional()->sentence(),
            'status'      => Report::STATUS_PENDING,
        ];
    }
}
