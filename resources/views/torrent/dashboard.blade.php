<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Torrent Editor - Admin Panel
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                {{-- Split + Randomize --}}
                <a href="{{ route('torrent.split') }}" class="block bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="text-3xl mb-3">&#9986;</div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Split + Randomize</h3>
                        <p class="text-gray-600 text-sm">
                            Upload un torrent, le decoupe en parties de 1Go max.
                            Randomise automatiquement toutes les metadonnees (noms, hash, trackers, source...).
                            Propose le fichier global ET les parties.
                        </p>
                        <span class="inline-block mt-3 px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">Principal</span>
                    </div>
                </a>

                {{-- Edit --}}
                <a href="{{ route('torrent.index') }}" class="block bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="text-3xl mb-3">&#9998;</div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Editeur Manuel</h3>
                        <p class="text-gray-600 text-sm">
                            Modifier manuellement un torrent : trackers, fichiers internes, metadonnees, hash, flag prive/public.
                        </p>
                    </div>
                </a>

                {{-- Generate --}}
                <a href="{{ route('torrent.generate') }}" class="block bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="text-3xl mb-3">&#127922;</div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Generer un Torrent</h3>
                        <p class="text-gray-600 text-sm">
                            Creer un fichier .torrent completement aleatoire (noms, fichiers, hash, trackers).
                        </p>
                    </div>
                </a>

            </div>

            {{-- Info section --}}
            <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Fonctionnalites</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-1">Split + Randomize (auto)</h4>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Decoupe en parties &le; taille max</li>
                                <li>Randomise noms de fichiers internes</li>
                                <li>Change le hash de chaque partie</li>
                                <li>Randomise trackers, source, client</li>
                                <li>Fournit le fichier global + ZIP des parties</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-1">Editeur Manuel</h4>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Ajouter / supprimer / remplacer trackers</li>
                                <li>Renommer fichiers internes</li>
                                <li>Modifier commentaire, source, client, date</li>
                                <li>Changer le hash (randomiser)</li>
                                <li>Rendre prive / public</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
