<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_and_list_favorites(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/favorites', [
            'catalogue_url' => 'https://anime-sama.fr/catalogue/naruto/',
            'name' => 'Naruto',
        ])->assertCreated();

        $this->actingAs($user)->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Naruto');
    }

    public function test_adding_the_same_favorite_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $payload = [
            'catalogue_url' => 'https://anime-sama.fr/catalogue/naruto/',
            'name' => 'Naruto',
        ];

        $this->actingAs($user)->postJson('/api/favorites', $payload)->assertCreated();
        $this->actingAs($user)->postJson('/api/favorites', $payload)->assertOk();

        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_a_user_cannot_delete_another_users_favorite(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $favorite = $owner->favorites()->create([
            'catalogue_url' => 'https://anime-sama.fr/catalogue/naruto/',
            'name' => 'Naruto',
        ]);

        $this->actingAs($other)
            ->deleteJson("/api/favorites/{$favorite->id}")
            ->assertForbidden();
    }
}
