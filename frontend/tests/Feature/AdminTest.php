<?php

namespace Tests\Feature;

use App\Models\Anime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_admin_routes_require_authentication_and_admin(): void
    {
        $this->getJson('/api/admin/stats')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create()); // regular user
        $this->getJson('/api/admin/stats')->assertStatus(403);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonStructure(['data' => ['animes', 'episode_links']]);
    }

    public function test_admin_can_list_and_edit_an_anime(): void
    {
        Sanctum::actingAs($this->admin());
        $anime = Anime::create([
            'url' => 'https://anime-sama.fr/catalogue/naruto/',
            'slug' => 'naruto',
            'name' => 'Naruto',
        ]);

        $this->getJson('/api/admin/animes?q=naru')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Naruto');

        $this->putJson("/api/admin/animes/{$anime->id}", [
            'synopsis' => 'Un jeune ninja.',
            'trailer_url' => 'https://youtu.be/abcdefghijk',
            'is_mature' => true,
        ])->assertOk()->assertJsonPath('data.synopsis', 'Un jeune ninja.');

        $this->assertDatabaseHas('animes', [
            'id' => $anime->id,
            'trailer_url' => 'https://youtu.be/abcdefghijk',
            'is_mature' => true,
        ]);
    }

    public function test_admin_can_delete_an_anime(): void
    {
        Sanctum::actingAs($this->admin());
        $anime = Anime::create(['url' => 'https://x/catalogue/a/', 'slug' => 'a', 'name' => 'A']);

        $this->deleteJson("/api/admin/animes/{$anime->id}")->assertOk();
        $this->assertDatabaseCount('animes', 0);
    }

    public function test_make_admin_command(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com']);
        $this->assertFalse((bool) $user->is_admin);

        $this->artisan('user:make-admin boss@example.com')->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('user:make-admin boss@example.com --revoke')->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_catalogue_applies_local_overrides(): void
    {
        Anime::create([
            'url' => 'https://anime-sama.fr/catalogue/konosuba/',
            'slug' => 'konosuba',
            'name' => 'KonoSuba',
            'synopsis' => 'Synopsis édité localement.',
            'trailer_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ]);

        Http::fake([
            '*/api/catalogue*' => Http::response([
                'name' => 'KonoSuba',
                'synopsis' => 'Backend synopsis',
                'seasons' => [],
            ], 200),
        ]);

        $this->getJson('/api/anime/catalogue?url=https://anime-sama.fr/catalogue/konosuba/')
            ->assertOk()
            ->assertJsonPath('data.synopsis', 'Synopsis édité localement.')
            ->assertJsonPath('data.trailer_key', 'abcdefghijk');
    }

    public function test_manual_trailer_is_preferred_over_backend(): void
    {
        Anime::create([
            'url' => 'https://anime-sama.fr/catalogue/konosuba/',
            'slug' => 'konosuba',
            'name' => 'KonoSuba',
            'trailer_url' => 'https://youtu.be/abcdefghijk',
        ]);

        Http::fake(); // backend must NOT be called

        $this->getJson('/api/anime/trailer?title=konosuba')
            ->assertOk()
            ->assertJsonPath('data.youtube_key', 'abcdefghijk')
            ->assertJsonPath('data.source', 'manual');

        Http::assertNothingSent();
    }
}
