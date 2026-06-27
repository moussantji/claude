<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Users -------------------------------------------------------
        $admin = User::updateOrCreate(
            ['email' => 'admin@streamwave.test'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'is_admin' => true,
                'iptv_username' => 'admin' . random_int(1000, 9999),
                'iptv_password' => Str::random(12),
            ]
        );

        $member = User::updateOrCreate(
            ['email' => 'member@streamwave.test'],
            [
                'name' => 'Demo Member',
                'password' => 'password',
                'is_admin' => false,
                'iptv_username' => 'demo' . random_int(1000, 9999),
                'iptv_password' => Str::random(12),
            ]
        );

        // ---- Categories --------------------------------------------------
        $categories = collect([
            ['name' => 'Sports', 'icon' => '⚽'],
            ['name' => 'Movies', 'icon' => '🎬'],
            ['name' => 'News', 'icon' => '📰'],
            ['name' => 'Entertainment', 'icon' => '🎭'],
            ['name' => 'Kids', 'icon' => '🧸'],
            ['name' => 'Documentary', 'icon' => '🌍'],
            ['name' => 'Music', 'icon' => '🎵'],
        ])->mapWithKeys(function ($cat) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                ['name' => $cat['name'], 'icon' => $cat['icon']]
            );

            return [$cat['name'] => $category];
        });

        // ---- Channels ----------------------------------------------------
        $channelData = [
            'Sports' => ['Pro League Sports', 'Champions Arena', 'Goal TV', 'Court Side', 'Motor Sport HD', 'Fight Night'],
            'Movies' => ['Cinema One', 'Action Max', 'Indie Films', 'Classic Movies', 'Sci-Fi Channel', 'Horror Nights'],
            'News' => ['Global News 24', 'Business Wire', 'World Report', 'Weather Now'],
            'Entertainment' => ['Prime Shows', 'Reality Plus', 'Comedy Central HD', 'Drama Box'],
            'Kids' => ['Cartoon World', 'Junior Learn', 'Toon Time'],
            'Documentary' => ['Nature Explorer', 'History Vault', 'Science Today'],
            'Music' => ['Hit Music TV', 'Jazz Lounge', 'Rock Nation'],
        ];

        $qualities = ['HD', 'FHD', '4K', 'HD', 'HD', 'FHD'];

        foreach ($channelData as $categoryName => $names) {
            foreach ($names as $i => $name) {
                Channel::updateOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $categories[$categoryName]->id,
                        'logo_url' => "https://placehold.co/200x200/1e293b/818cf8/png?text=" . urlencode(Str::words($name, 1, '')),
                        'stream_url' => 'http://demo.streamwave.test/live/' . Str::slug($name) . '.m3u8',
                        'country' => 'International',
                        'quality' => $qualities[$i % count($qualities)],
                        'is_active' => true,
                    ]
                );
            }
        }

        $totalChannels = Channel::count();

        // ---- Packages ----------------------------------------------------
        $packages = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for casual viewers who want the essentials.',
                'price' => 9.99,
                'duration_days' => 30,
                'channel_count' => 1000,
                'max_connections' => 1,
                'features' => ['HD streaming', 'VOD library', 'EPG guide'],
                'is_featured' => false,
            ],
            [
                'name' => 'Premium',
                'description' => 'Our most popular plan with 4K and multi-device support.',
                'price' => 19.99,
                'duration_days' => 30,
                'channel_count' => 5000,
                'max_connections' => 3,
                'features' => ['4K Ultra HD', 'Full VOD library', 'EPG guide', 'Catch-up TV', 'Priority support'],
                'is_featured' => true,
            ],
            [
                'name' => 'Annual Pro',
                'description' => 'Best value — a full year of everything Premium offers.',
                'price' => 149.99,
                'duration_days' => 365,
                'channel_count' => 8000,
                'max_connections' => 5,
                'features' => ['4K Ultra HD', 'Full VOD library', 'EPG guide', 'Catch-up TV', 'Priority support', '2 months free'],
                'is_featured' => false,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::updateOrCreate(
                ['slug' => Str::slug($pkg['name'])],
                array_merge($pkg, ['slug' => Str::slug($pkg['name']), 'is_active' => true])
            );
        }

        // ---- Demo subscription for the member ----------------------------
        $premium = Package::where('slug', 'premium')->first();
        if ($premium) {
            Subscription::updateOrCreate(
                ['user_id' => $member->id, 'package_id' => $premium->id, 'status' => 'active'],
                [
                    'amount_paid' => $premium->price,
                    'starts_at' => Carbon::now()->subDays(5),
                    'ends_at' => Carbon::now()->addDays(25),
                ]
            );
        }

        $this->command->info("Seeded {$totalChannels} channels, " . Package::count() . ' packages, ' . Category::count() . ' categories.');
        $this->command->info('Admin login: admin@streamwave.test / password');
        $this->command->info('Member login: member@streamwave.test / password');
    }
}
