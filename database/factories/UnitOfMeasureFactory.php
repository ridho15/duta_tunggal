<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->sentence();
        $words = preg_split('/\s+/', trim($name));
        $abbreviation = '';
        foreach ($words as $word) {
            if (ctype_alpha($word[0])) {
                $abbreviation .= strtoupper($word[0]);
            }
        }
        return [
            'name' => $name,
            'abbreviation' => $abbreviation
        ];
    }
}
