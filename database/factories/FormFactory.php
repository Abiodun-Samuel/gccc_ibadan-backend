<?php

namespace Database\Factories;

use App\Enums\FormTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Form>
 */
class FormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(FormTypeEnum::values());
        $is_completed = $this->faker->randomElement([true, false]);

        return [
            'type' => $type,
            'name' => $type === FormTypeEnum::TESTIMONY->value ? $this->faker->name() : null,
            'is_completed' => $is_completed,
            'phone_number' => $type === FormTypeEnum::TESTIMONY->value ? $this->faker->phoneNumber() : null,
            'wants_to_share_testimony' => $type === FormTypeEnum::TESTIMONY->value ? $this->faker->boolean() : null,
            'content' => $this->faker->paragraph(),
        ];
    }
}
