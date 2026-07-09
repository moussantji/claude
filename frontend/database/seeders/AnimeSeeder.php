<?php

namespace Database\Seeders;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\Season;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the database with sample animes, seasons, episodes and video links
 * so the app works out of the box without any external API.
 */
class AnimeSeeder extends Seeder
{
    public function run(): void
    {
        $animes = [
            [
                'name' => 'Solo Leveling',
                'slug' => 'solo-leveling',
                'synopsis' => "Dans un monde où des portails mènent à des donjons remplis de monstres, Sung Jinwoo est le chasseur le plus faible de rang E. Après avoir frôlé la mort dans un donjon à double étage, il obtient un mystérieux système qui lui permet de monter en niveau sans limite.",
                'image_url' => 'https://cdn.myanimelist.net/images/anime/1908/141527.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=Drq_2nqIl6I',
                'categories' => ['Action', 'Fantaisie'],
                'languages' => ['VF', 'VOSTFR'],
                'genres' => ['Action', 'Aventure', 'Fantaisie'],
                'seasons' => [
                    [
                        'name' => 'Saison 1',
                        'episodes' => [
                            ['name' => "Je suis le plus faible", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/sl-s1e1-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/sl-s1e1-vostfr'],
                            ]],
                            ['name' => "Si je fais demi-tour", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/sl-s1e2-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/sl-s1e2-vostfr'],
                            ]],
                            ['name' => "Il est temps de partir", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/sl-s1e3-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/sl-s1e3-vostfr'],
                            ]],
                        ],
                    ],
                    [
                        'name' => 'Saison 2 - Arise from the Shadow',
                        'episodes' => [
                            ['name' => "Le retour", 'links' => [
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/sl-s2e1-vostfr'],
                            ]],
                            ['name' => "L'armée des ombres", 'links' => [
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/sl-s2e2-vostfr'],
                            ]],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Jujutsu Kaisen',
                'slug' => 'jujutsu-kaisen',
                'synopsis' => "Yuji Itadori, un lycéen aux capacités physiques exceptionnelles, avale un doigt maudit du démon Ryomen Sukuna pour sauver un ami. Devenu l'hôte de Sukuna, il intègre l'école d'exorcisme de Tokyo pour combattre les fléaux.",
                'image_url' => 'https://cdn.myanimelist.net/images/anime/1171/109222.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=4A_X-Dvl0ws',
                'categories' => ['Action', 'Surnaturel'],
                'languages' => ['VF', 'VOSTFR'],
                'genres' => ['Action', 'Surnaturel', 'Shonen'],
                'seasons' => [
                    [
                        'name' => 'Saison 1',
                        'episodes' => [
                            ['name' => "Ryomen Sukuna", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/jjk-s1e1-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/jjk-s1e1-vostfr'],
                            ]],
                            ['name' => "Pour moi-même", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/jjk-s1e2-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/jjk-s1e2-vostfr'],
                            ]],
                            ['name' => "Fille d'acier", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/jjk-s1e3-vf'],
                            ]],
                        ],
                    ],
                    [
                        'name' => 'Saison 2',
                        'episodes' => [
                            ['name' => "Passé caché", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/jjk-s2e1-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/jjk-s2e1-vostfr'],
                            ]],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Demon Slayer',
                'slug' => 'demon-slayer',
                'synopsis' => "Tanjiro Kamado, un jeune vendeur de charbon, voit sa famille massacrée par un démon. Sa sœur Nezuko est transformée en démone. Il rejoint le Corps des Pourfendeurs de Démons pour la sauver et venger sa famille.",
                'image_url' => 'https://cdn.myanimelist.net/images/anime/1286/99889.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=VQGCKyvzIM4',
                'categories' => ['Action', 'Shonen'],
                'languages' => ['VF', 'VOSTFR'],
                'genres' => ['Action', 'Surnaturel', 'Historique'],
                'seasons' => [
                    [
                        'name' => 'Saison 1',
                        'episodes' => [
                            ['name' => "Cruauté", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/ds-s1e1-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/ds-s1e1-vostfr'],
                            ]],
                            ['name' => "Un formateur nommé Sakonji Urokodaki", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/ds-s1e2-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/ds-s1e2-vostfr'],
                            ]],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'One Piece',
                'slug' => 'one-piece',
                'synopsis' => "Monkey D. Luffy, un garçon au corps élastique, part en mer avec son équipage pour trouver le One Piece, le trésor légendaire du roi des pirates Gol D. Roger, et devenir le nouveau roi des pirates.",
                'image_url' => 'https://cdn.myanimelist.net/images/anime/1244/138851.jpg',
                'trailer_url' => null,
                'categories' => ['Aventure', 'Shonen'],
                'languages' => ['VF', 'VOSTFR'],
                'genres' => ['Action', 'Aventure', 'Comédie', 'Shonen'],
                'seasons' => [
                    [
                        'name' => 'East Blue',
                        'episodes' => [
                            ['name' => "Je suis Luffy ! Celui qui deviendra le roi des pirates !", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/op-s1e1-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/op-s1e1-vostfr'],
                            ]],
                            ['name' => "Le grand épéiste arrive ! Roronoa Zoro en mer", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/op-s1e2-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/op-s1e2-vostfr'],
                            ]],
                            ['name' => "Morgan VS Luffy", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/op-s1e3-vf'],
                            ]],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Frieren',
                'slug' => 'frieren',
                'synopsis' => "Après avoir vaincu le roi des démons aux côtés de ses compagnons héros, l'elfe magicienne Frieren réalise qu'elle n'a pas pris le temps de les connaître. Elle entreprend un voyage pour comprendre les humains et honorer la mémoire de ses amis mortels.",
                'image_url' => 'https://cdn.myanimelist.net/images/anime/1015/138006.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=wgMuzdO7MWs',
                'categories' => ['Aventure', 'Fantaisie'],
                'languages' => ['VOSTFR'],
                'genres' => ['Aventure', 'Drame', 'Fantaisie'],
                'seasons' => [
                    [
                        'name' => 'Saison 1',
                        'episodes' => [
                            ['name' => "La fin du voyage du héros", 'links' => [
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/fr-s1e1-vostfr'],
                            ]],
                            ['name' => "Le pays où le soleil se couche", 'links' => [
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/fr-s1e2-vostfr'],
                            ]],
                            ['name' => "Tuer des monstres", 'links' => [
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/fr-s1e3-vostfr'],
                            ]],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Naruto Shippuden',
                'slug' => 'naruto-shippuden',
                'synopsis' => "Deux ans et demi après avoir quitté Konoha pour s'entraîner, Naruto Uzumaki revient plus fort que jamais. L'Akatsuki menace les villages ninja et cherche à capturer les démons à queues, dont celui enfermé en Naruto.",
                'image_url' => 'https://cdn.myanimelist.net/images/anime/1565/111305.jpg',
                'trailer_url' => null,
                'categories' => ['Action', 'Shonen'],
                'languages' => ['VF', 'VOSTFR'],
                'genres' => ['Action', 'Aventure', 'Shonen'],
                'seasons' => [
                    [
                        'name' => 'Saison 1 - Sauvetage du Kazekage',
                        'episodes' => [
                            ['name' => "Retour au pays", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/ns-s1e1-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/ns-s1e1-vostfr'],
                            ]],
                            ['name' => "L'Akatsuki passe à l'action", 'links' => [
                                ['language' => 'VF', 'url' => 'https://example.com/ns-s1e2-vf'],
                                ['language' => 'VOSTFR', 'url' => 'https://example.com/ns-s1e2-vostfr'],
                            ]],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($animes as $animeData) {
            $slug = $animeData['slug'];
            $anime = Anime::firstOrCreate(
                ['slug' => $slug],
                [
                    'url' => 'local://anime/' . $slug,
                    'name' => $animeData['name'],
                    'alternative_names' => [],
                    'synopsis' => $animeData['synopsis'],
                    'image_url' => $animeData['image_url'],
                    'trailer_url' => $animeData['trailer_url'],
                    'categories' => $animeData['categories'],
                    'languages' => $animeData['languages'],
                    'genres' => $animeData['genres'],
                    'is_mature' => false,
                ]
            );

            foreach ($animeData['seasons'] as $pos => $seasonData) {
                $season = Season::firstOrCreate(
                    ['anime_id' => $anime->id, 'position' => $pos],
                    [
                        'url' => 'local://anime/' . $slug . '/saison/' . ($pos + 1) . '-' . Str::lower(Str::random(4)),
                        'name' => $seasonData['name'],
                    ]
                );

                foreach ($seasonData['episodes'] as $idx => $epData) {
                    $episode = Episode::firstOrCreate(
                        ['season_id' => $season->id, 'index' => $idx + 1],
                        [
                            'name' => $epData['name'],
                        ]
                    );

                    foreach ($epData['links'] as $linkPos => $linkData) {
                        EpisodeLink::firstOrCreate(
                            ['episode_id' => $episode->id, 'language' => $linkData['language'], 'url' => $linkData['url']],
                            [
                                'position' => $linkPos,
                                'host' => parse_url($linkData['url'], PHP_URL_HOST) ?? 'example.com',
                            ]
                        );
                    }
                }
            }
        }
    }
}
