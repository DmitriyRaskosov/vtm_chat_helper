<?php

namespace Database\Seeders;

use App\Models\CanonSect;
use Illuminate\Database\Seeder;

class CanonSectSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->row('camarilla', 'Камарилья',
                'Секта старейшин, охраняющая Маскарад и поддерживающая статус-кво.',
                1394),
            $this->row('sabbat', 'Шабаш',
                'Кровожадная и мрачная секта, извечные враги Камарильи.',
                1493),
            $this->row('anarchs', 'Анархи',
                'Объединение вампиров, отказавшихся подчиняться диктату Камарильи или Шабаша. Независимое движение внутри/вне сект',
                1493),
            $this->row('independent', 'Независимые',
                'Кланы и каиниты вне основных сект.',
                isIndependent: true),
        ];

        CanonSect::upsert($rows, ['slug'], [
            'name', 'description', 'founded_year', 'ended_year',
            'is_independent', 'updated_at',
        ]);
    }

    private function row(
        string $slug,
        string $name,
        string $description,
        ?int $foundedYear = null,
        bool $isIndependent = false,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'founded_year' => $foundedYear,
            'ended_year' => null,
            'is_independent' => $isIndependent,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}