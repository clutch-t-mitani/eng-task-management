<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DirectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DirectorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_seeder_creates_initial_director(): void
    {
        config(['app.director_initial_password' => 'secret-password']);

        $this->seed(DirectorSeeder::class);

        $director = User::query()
            ->where('email', 'test@example.com')
            ->firstOrFail();

        $this->assertSame('Test Director', $director->name);
        $this->assertTrue(Hash::check('secret-password', $director->password));
    }

    public function test_director_seeder_requires_initial_password_to_create_director(): void
    {
        config(['app.director_initial_password' => '']);

        $this->expectException(RuntimeException::class);

        $this->seed(DirectorSeeder::class);
    }

    public function test_director_seeder_restores_soft_deleted_director(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ])->delete();

        $this->seed(DirectorSeeder::class);

        $this->assertNotNull(
            User::query()->where('email', 'test@example.com')->first()
        );
    }

    public function test_director_seeder_does_not_reset_existing_director_password(): void
    {
        $director = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('changed-password'),
        ]);

        config(['app.director_initial_password' => 'secret-password']);

        $this->seed(DirectorSeeder::class);

        $director->refresh();

        $this->assertTrue(Hash::check('changed-password', $director->password));
        $this->assertFalse(Hash::check('secret-password', $director->password));
    }
}
