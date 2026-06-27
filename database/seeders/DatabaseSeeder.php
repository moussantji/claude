<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Series;
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
            ['name' => 'Admin User', 'password' => 'password', 'is_admin' => true]
        );

        $member = User::updateOrCreate(
            ['email' => 'member@streamwave.test'],
            ['name' => 'Demo Member', 'password' => 'password', 'is_admin' => false]
        );

        // ---- Payment methods (admin uploads the QR codes later) ----------
        $paymentMethods = [
            [
                'name' => 'Orange Money',
                'account_number' => '07 07 00 00 00',
                'color' => '#ff7900',
                'instructions' => "1. Composez #144*82# (Orange Money).\n2. Envoyez le montant au numéro indiqué.\n3. Saisissez le numéro utilisé ci-dessous et validez.",
            ],
            [
                'name' => 'Moov Money',
                'account_number' => '01 01 00 00 00',
                'color' => '#0066b3',
                'instructions' => "1. Composez *155# (Moov Money).\n2. Envoyez le montant au numéro indiqué.\n3. Saisissez le numéro utilisé ci-dessous et validez.",
            ],
            [
                'name' => 'Wave',
                'account_number' => '05 05 00 00 00',
                'color' => '#1dc8ff',
                'instructions' => "1. Ouvrez l'application Wave.\n2. Envoyez le montant au numéro indiqué (ou scannez le QR code).\n3. Saisissez le numéro utilisé ci-dessous et validez.",
            ],
        ];
        foreach ($paymentMethods as $i => $pm) {
            PaymentMethod::updateOrCreate(
                ['slug' => Str::slug($pm['name'])],
                array_merge($pm, ['slug' => Str::slug($pm['name']), 'is_active' => true, 'sort_order' => $i]),
            );
        }

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

        // ---- Packages (prices in FCFA) -----------------------------------
        $packages = [
            [
                'name' => 'Starter',
                'description' => 'Idéal pour les spectateurs occasionnels.',
                'price' => 5000,
                'duration_days' => 30,
                'channel_count' => 1000,
                'max_connections' => 1,
                'features' => ['Streaming HD', 'Bibliothèque VOD', 'Guide EPG'],
                'is_featured' => false,
            ],
            [
                'name' => 'Premium',
                'description' => "Notre offre la plus populaire avec la 4K et le multi-appareils.",
                'price' => 10000,
                'duration_days' => 30,
                'channel_count' => 5000,
                'max_connections' => 3,
                'features' => ['4K Ultra HD', 'VOD complète', 'Guide EPG', 'Catch-up TV', 'Support prioritaire'],
                'is_featured' => true,
            ],
            [
                'name' => 'Annual Pro',
                'description' => "Le meilleur rapport qualité-prix — un an d'accès complet.",
                'price' => 90000,
                'duration_days' => 365,
                'channel_count' => 8000,
                'max_connections' => 5,
                'features' => ['4K Ultra HD', 'VOD complète', 'Guide EPG', 'Catch-up TV', 'Support prioritaire', '2 mois offerts'],
                'is_featured' => false,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::updateOrCreate(
                ['slug' => Str::slug($pkg['name'])],
                array_merge($pkg, ['slug' => Str::slug($pkg['name']), 'is_active' => true])
            );
        }

        // ---- Movies (preview trailers) -----------------------------------
        $movieData = [
            ['The Last Horizon', 'Sci-Fi', 2024, 142, 8.4, '4K'],
            ['Midnight Avenue', 'Thriller', 2023, 118, 7.6, 'FHD'],
            ['Echoes of Tomorrow', 'Drama', 2024, 131, 8.1, '4K'],
            ['Crimson Skyline', 'Action', 2022, 126, 7.2, 'FHD'],
            ['The Silent Garden', 'Mystery', 2023, 109, 7.9, 'HD'],
            ['Velocity', 'Action', 2024, 134, 8.0, '4K'],
            ['Paper Lanterns', 'Romance', 2021, 98, 6.8, 'HD'],
            ['Beneath the Ice', 'Adventure', 2023, 122, 7.4, 'FHD'],
            ['Quantum Drift', 'Sci-Fi', 2024, 147, 8.7, '4K'],
            ['City of Ash', 'Crime', 2022, 115, 7.1, 'FHD'],
            ['Laughing Matters', 'Comedy', 2023, 101, 6.9, 'HD'],
            ['The Hollow Crown', 'Fantasy', 2024, 138, 8.2, '4K'],
        ];

        foreach ($movieData as [$title, $genre, $year, $runtime, $rating, $quality]) {
            $slug = Str::slug($title);
            Movie::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => "A captivating {$genre} film that takes audiences on an unforgettable journey filled with tension, emotion and stunning visuals.",
                    'poster_url' => "https://picsum.photos/seed/movie-{$slug}/400/600",
                    'backdrop_url' => "https://picsum.photos/seed/movie-{$slug}-bd/1600/900",
                    'genre' => $genre,
                    'year' => $year,
                    'duration_minutes' => $runtime,
                    'rating' => $rating,
                    'quality' => $quality,
                    'stream_url' => null,
                    'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoyrides.mp4',
                    'is_featured' => $title === 'Quantum Drift',
                    'is_active' => true,
                ]
            );
        }

        // ---- Series ------------------------------------------------------
        $seriesData = [
            ['Dark Frequencies', 'Sci-Fi', 2022, null, 3, 8.6],
            ['The Brightwater Files', 'Crime', 2021, 2024, 4, 8.9],
            ['Northern Lights', 'Drama', 2023, null, 2, 7.8],
            ['Court of Shadows', 'Fantasy', 2024, null, 1, 8.3],
            ['Startup Avenue', 'Comedy', 2020, 2023, 4, 7.5],
            ['Deep Currents', 'Thriller', 2023, null, 2, 8.1],
        ];

        foreach ($seriesData as [$title, $genre, $startYear, $endYear, $seasons, $rating]) {
            $slug = Str::slug($title);
            $series = Series::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => "An acclaimed {$genre} series that keeps viewers on the edge of their seats across {$seasons} gripping seasons.",
                    'poster_url' => "https://picsum.photos/seed/series-{$slug}/400/600",
                    'backdrop_url' => "https://picsum.photos/seed/series-{$slug}-bd/1600/900",
                    'genre' => $genre,
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                    'seasons' => $seasons,
                    'rating' => $rating,
                    'is_featured' => $title === 'The Brightwater Files',
                    'is_active' => true,
                ]
            );

            for ($s = 1; $s <= min($seasons, 3); $s++) {
                for ($e = 1; $e <= 6; $e++) {
                    Episode::updateOrCreate(
                        ['series_id' => $series->id, 'season' => $s, 'episode_number' => $e],
                        [
                            'title' => "Episode {$e}",
                            'stream_url' => null,
                            'duration_minutes' => random_int(38, 55),
                        ]
                    );
                }
            }
        }

        // ---- Sample completed order for the demo member ------------------
        $premium = Package::where('slug', 'premium')->first();
        $orange = PaymentMethod::where('slug', 'orange-money')->first();
        if ($premium && $orange) {
            Order::updateOrCreate(
                ['reference' => 'CMD-DEMO-0001'],
                [
                    'user_id' => $member->id,
                    'package_id' => $premium->id,
                    'package_name' => $premium->name,
                    'payment_method_id' => $orange->id,
                    'payment_method_name' => $orange->name,
                    'payment_number' => '07 07 12 34 56',
                    'amount' => $premium->price,
                    'currency' => 'FCFA',
                    'status' => 'completed',
                    'iptv_username' => 'demo_user',
                    'iptv_password' => Str::random(10),
                    'iptv_dns' => 'http://line.streamwave.tv:8080',
                    'processed_by' => $admin->id,
                    'processed_at' => Carbon::now()->subDays(2),
                ]
            );
        }

        $this->command->info("Seeded {$totalChannels} channels, " . Movie::count() . ' movies, ' . Series::count() . ' series, ' . Package::count() . ' packages, ' . PaymentMethod::count() . ' payment methods.');
        $this->command->info('Admin: admin@streamwave.test / password — Member: member@streamwave.test / password');
    }
}
