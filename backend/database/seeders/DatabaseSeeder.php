<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Administrateurs ---
        User::updateOrCreate(['email' => 'admin@fasodigital.ml'], [
            'name' => 'Amadou Traore',
            'password' => Hash::make('admin123'),
            'role' => 'Administrateur',
            'avatar' => 'AT',
        ]);

        User::updateOrCreate(['email' => 'fatou@fasodigital.ml'], [
            'name' => 'Fatoumata Keita',
            'password' => Hash::make('faso2025'),
            'role' => 'Chef de projet',
            'avatar' => 'FK',
        ]);

        // --- Parametres ---
        Setting::updateOrCreate(['id' => 1], [
            'company' => 'Faso Digital',
            'email' => 'contact@fasodigital.ml',
            'phone' => '+223 20 00 00 00',
            'address' => 'ACI 2000, Bamako - Mali',
            'currency' => 'FCFA',
            'slogan' => 'Le numerique qui fait grandir votre entreprise',
        ]);

        // --- Prospects ---
        $leads = [
            ['name' => 'Ibrahim Diallo', 'company' => 'Sahel Import SARL', 'email' => 'i.diallo@sahelimport.ml', 'phone' => '+223 76 12 34 56', 'city' => 'Bamako', 'service' => 'Site web', 'budget' => 1500000, 'status' => 'Qualifie', 'source' => 'Site web', 'note' => 'Souhaite une refonte + boutique en ligne.'],
            ['name' => 'Aicha Cisse', 'company' => 'Boutique Aicha', 'email' => 'aicha.cisse@gmail.com', 'phone' => '+223 65 88 77 22', 'city' => 'Sikasso', 'service' => 'E-commerce', 'budget' => 900000, 'status' => 'Nouveau', 'source' => 'Facebook', 'note' => 'Vente de tissus bazin en ligne.'],
            ['name' => 'Moussa Sangare', 'company' => 'TransSahel Logistique', 'email' => 'm.sangare@transsahel.ml', 'phone' => '+223 79 45 12 90', 'city' => 'Bamako', 'service' => 'Logiciel PC', 'budget' => 3200000, 'status' => 'Contacte', 'source' => 'Recommandation', 'note' => 'Logiciel de gestion de flotte hors-ligne.'],
            ['name' => 'Kadiatou Toure', 'company' => 'Clinique Espoir', 'email' => 'contact@cliniqueespoir.ml', 'phone' => '+223 66 33 21 45', 'city' => 'Segou', 'service' => 'Application web', 'budget' => 2500000, 'status' => 'Gagne', 'source' => 'Google', 'note' => 'Prise de rendez-vous en ligne.'],
            ['name' => 'Oumar Ba', 'company' => 'Radio Kayira', 'email' => 'oumar.ba@kayira.ml', 'phone' => '+223 78 90 11 22', 'city' => 'Bamako', 'service' => 'Streaming', 'budget' => 1200000, 'status' => 'Nouveau', 'source' => 'Site web', 'note' => 'Plateforme de streaming audio + podcasts.'],
        ];
        foreach ($leads as $l) {
            Lead::create($l);
        }

        // --- Clients ---
        $clients = [
            ['name' => 'Clinique Espoir', 'contact' => 'Kadiatou Toure', 'email' => 'contact@cliniqueespoir.ml', 'phone' => '+223 66 33 21 45', 'city' => 'Segou', 'sector' => 'Sante', 'since' => '2026-06-01', 'value' => 2500000, 'status' => 'Actif'],
            ['name' => 'Groupe Diarra Distribution', 'contact' => 'Seydou Diarra', 'email' => 'seydou@diarradist.ml', 'phone' => '+223 76 10 20 30', 'city' => 'Bamako', 'sector' => 'Distribution', 'since' => '2025-11-15', 'value' => 6800000, 'status' => 'Actif'],
            ['name' => 'Hotel Djenne Palace', 'contact' => 'Awa Maiga', 'email' => 'reservation@djennepalace.ml', 'phone' => '+223 65 44 55 66', 'city' => 'Djenne', 'sector' => 'Tourisme', 'since' => '2025-09-02', 'value' => 1800000, 'status' => 'Actif'],
            ['name' => 'Faso Mode', 'contact' => 'Nana Sylla', 'email' => 'contact@fasomode.ml', 'phone' => '+223 79 88 66 44', 'city' => 'Bamako', 'sector' => 'Mode', 'since' => '2026-02-20', 'value' => 1500000, 'status' => 'Actif'],
        ];
        foreach ($clients as $c) {
            Client::create($c);
        }

        // --- Projets ---
        $projects = [
            ['name' => 'Refonte site + boutique', 'client' => 'Faso Mode', 'type' => 'E-commerce', 'budget' => 1500000, 'status' => 'En cours', 'progress' => 65, 'deadline' => '2026-08-15', 'lead' => 'Fatoumata Keita'],
            ['name' => 'App prise de RDV clinique', 'client' => 'Clinique Espoir', 'type' => 'Application web', 'budget' => 2500000, 'status' => 'En cours', 'progress' => 40, 'deadline' => '2026-09-30', 'lead' => 'Amadou Traore'],
            ['name' => 'Logiciel gestion de stock', 'client' => 'Groupe Diarra Distribution', 'type' => 'Logiciel PC', 'budget' => 3800000, 'status' => 'En cours', 'progress' => 25, 'deadline' => '2026-11-20', 'lead' => 'Amadou Traore'],
            ['name' => 'Site de reservation hotel', 'client' => 'Hotel Djenne Palace', 'type' => 'Site web', 'budget' => 1800000, 'status' => 'En attente', 'progress' => 10, 'deadline' => '2026-10-05', 'lead' => 'Fatoumata Keita'],
        ];
        foreach ($projects as $p) {
            Project::create($p);
        }

        // --- Produits / offres ---
        $products = [
            ['name' => 'FasoStream - Standard', 'category' => 'Streaming', 'price' => 3000, 'cycle' => '/mois', 'subscribers' => 428, 'status' => 'Publie'],
            ['name' => 'FasoStream - Premium 4K', 'category' => 'Streaming', 'price' => 6000, 'cycle' => '/mois', 'subscribers' => 176, 'status' => 'Publie'],
            ['name' => 'FasoTV IPTV - 3 mois', 'category' => 'IPTV', 'price' => 12000, 'cycle' => '/3 mois', 'subscribers' => 512, 'status' => 'Publie'],
            ['name' => 'FasoTV IPTV - 12 mois', 'category' => 'IPTV', 'price' => 40000, 'cycle' => '/an', 'subscribers' => 233, 'status' => 'Publie'],
            ['name' => 'Bazin Riche Premium (5m)', 'category' => 'E-commerce', 'price' => 45000, 'cycle' => '', 'subscribers' => 0, 'status' => 'Publie'],
            ['name' => 'Smartphone Faso X2 64Go', 'category' => 'E-commerce', 'price' => 89000, 'cycle' => '', 'subscribers' => 0, 'status' => 'Publie'],
        ];
        foreach ($products as $p) {
            Product::create($p);
        }

        // --- Commandes ---
        $orders = [
            ['ref' => 'CMD-2601', 'customer' => 'Ousmane Fofana', 'type' => 'E-commerce', 'item' => 'Smartphone Faso X2 64Go', 'amount' => 89000, 'status' => 'Livre', 'date' => '2026-07-01', 'city' => 'Bamako'],
            ['ref' => 'ABO-1187', 'customer' => 'Rokia Konate', 'type' => 'IPTV', 'item' => 'FasoTV IPTV - 12 mois', 'amount' => 40000, 'status' => 'Paye', 'date' => '2026-07-03', 'city' => 'Kayes'],
            ['ref' => 'ABO-1190', 'customer' => 'Drissa Kone', 'type' => 'Streaming', 'item' => 'FasoStream - Premium 4K', 'amount' => 6000, 'status' => 'Paye', 'date' => '2026-07-04', 'city' => 'Bamako'],
            ['ref' => 'CMD-2603', 'customer' => 'Aminata Diarra', 'type' => 'E-commerce', 'item' => 'Bazin Riche Premium (5m)', 'amount' => 45000, 'status' => 'En preparation', 'date' => '2026-07-04', 'city' => 'Sikasso'],
        ];
        foreach ($orders as $o) {
            Order::create($o);
        }

        // --- Articles de blog ---
        $posts = [
            ['title' => 'Pourquoi chaque PME malienne a besoin d\'un site web en 2026', 'slug' => 'pme-malienne-site-web-2026', 'category' => 'Conseils', 'author' => 'Amadou Traore', 'date' => '2026-06-30', 'status' => 'Publie', 'excerpt' => 'Un site web professionnel est le premier vendeur de votre entreprise, disponible 24h/24.', 'content' => "Au Mali, l'usage d'Internet et des smartphones explose. Vos clients cherchent en ligne avant de se deplacer.\n\nUn site web bien concu inspire confiance et capte des prospects pendant que vous dormez."],
            ['title' => 'Lancer sa boutique e-commerce au Mali : le guide complet', 'slug' => 'lancer-boutique-ecommerce-mali', 'category' => 'E-commerce', 'author' => 'Fatoumata Keita', 'date' => '2026-06-18', 'status' => 'Publie', 'excerpt' => 'Du choix des produits au paiement mobile (Orange Money, Moov Money), voici les etapes clefs.', 'content' => "Le e-commerce malien connait une croissance rapide. Integrez les paiements locaux et une livraison fiable.\n\nNotre plateforme FasoShop gere tout dans une seule interface."],
            ['title' => 'IPTV et streaming : le nouveau visage du divertissement africain', 'slug' => 'iptv-streaming-divertissement-africain', 'category' => 'Streaming', 'author' => 'Amadou Traore', 'date' => '2026-05-28', 'status' => 'Publie', 'excerpt' => 'Chaines locales, films africains, sport en direct : le contenu de qualite devient accessible partout.', 'content' => "Avec FasoTV et FasoStream, nous rapprochons le meilleur du divertissement des foyers maliens.\n\nUne connexion internet suffit pour une experience fluide sur tous les ecrans."],
        ];
        foreach ($posts as $p) {
            Post::create($p);
        }

        // --- Messages ---
        Message::create([
            'name' => 'Bakary Kone', 'email' => 'bakary.kone@gmail.com', 'phone' => '+223 76 00 11 22',
            'subject' => 'Demande de devis site vitrine', 'message' => 'Bonjour, je souhaite un site pour mon garage a Bamako.',
            'service' => 'Site web', 'status' => 'Nouveau', 'date' => now(),
        ]);
        Message::create([
            'name' => 'Djeneba Traore', 'email' => 'djeneba@boutiquedj.ml', 'phone' => '+223 65 12 90 34',
            'subject' => 'Boutique en ligne', 'message' => 'Combien coute une boutique avec paiement Orange Money ?',
            'service' => 'E-commerce', 'status' => 'Lu', 'date' => now(),
        ]);
    }
}
