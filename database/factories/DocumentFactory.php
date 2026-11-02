<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->word().'.pdf';

        return [
            'name' => $name,
            'type' => DocumentType::Document,
            'filename' => $name,
            'file_path' => 'documents/'.$this->faker->uuid().'.pdf',
        ];
    }
}
