<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DirectorSeeder extends Seeder
{
    public function run(): void
    {
        $director = User::withTrashed()->firstOrNew([
            'email' => 'test@example.com',
        ]);

        if (! $director->exists) {
            $initialPassword = config('app.director_initial_password');

            if (! is_string($initialPassword) || $initialPassword === '') {
                throw new RuntimeException('DIRECTOR_INITIAL_PASSWORD must be set to create the initial director.');
            }

            $director->name = 'Test Director';
            $director->password = $initialPassword;
            $director->save();

            return;
        }

        if ($director->trashed()) {
            $director->restore();
        }
    }
}
