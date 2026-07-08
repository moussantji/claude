<?php

namespace Tests\Feature;

use App\Models\MediaUpload;
use App\Services\VidmolyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.vidmoly.api_key', 'test-key');
        config()->set('services.vidmoly.api_base', 'https://api.vidmoly.test');
        config()->set('services.vidmoly.embed_base', 'https://vidmoly.test');
    }

    private function fakeVidmoly(): void
    {
        Http::fake([
            'https://api.vidmoly.test/api/upload/server*' => Http::response([
                'result' => 'https://up.vidmoly.test/upload',
                'sess_id' => 'sess-123',
            ], 200),
            'https://up.vidmoly.test/upload' => Http::response([
                ['file_code' => 'abc123', 'file_status' => 'OK'],
            ], 200),
        ]);
    }

    private function tempVideo(string $name = 'clip.mp4'): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('media_', true).'_'.$name;
        file_put_contents($path, 'fake-bytes');

        return $path;
    }

    public function test_client_uploads_and_returns_embed_url(): void
    {
        $this->fakeVidmoly();
        $file = $this->tempVideo();

        $client = app(VidmolyClient::class);
        $result = $client->upload($file, 'My clip');

        $this->assertSame('abc123', $result['file_code']);
        $this->assertSame('https://vidmoly.test/embed-abc123.html', $result['embed_url']);

        @unlink($file);
    }

    public function test_command_uploads_file_and_stores_link(): void
    {
        $this->fakeVidmoly();
        $file = $this->tempVideo();

        $this->artisan('media:upload', ['path' => $file])
            ->assertSuccessful();

        $this->assertDatabaseHas('media_uploads', [
            'source_path' => $file,
            'status' => 'uploaded',
            'file_code' => 'abc123',
            'embed_url' => 'https://vidmoly.test/embed-abc123.html',
        ]);

        @unlink($file);
    }

    public function test_command_skips_already_uploaded_file(): void
    {
        $this->fakeVidmoly();
        $file = $this->tempVideo();

        MediaUpload::create([
            'path_hash' => hash('sha256', $file),
            'source_path' => $file,
            'title' => 'clip',
            'provider' => 'vidmoly',
            'file_code' => 'existing',
            'embed_url' => 'https://vidmoly.test/embed-existing.html',
            'status' => 'uploaded',
            'uploaded_at' => now(),
        ]);

        $this->artisan('media:upload', ['path' => $file])
            ->expectsOutputToContain('skip (already uploaded)')
            ->assertSuccessful();

        // Not re-uploaded: no request was sent.
        Http::assertNothingSent();
        $this->assertSame('existing', MediaUpload::first()->file_code);

        @unlink($file);
    }

    public function test_upload_requires_api_key(): void
    {
        config()->set('services.vidmoly.api_key', '');
        $file = $this->tempVideo();

        $this->artisan('media:upload', ['path' => $file])
            ->assertFailed();

        @unlink($file);
    }
}
