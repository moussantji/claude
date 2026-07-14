<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Split + Randomize
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        @foreach ($errors->all() as $error)
                            <p class="text-red-700 text-sm">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Info banner --}}
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">Comment ca marche</h3>
                    <ol class="text-sm text-blue-700 space-y-1 list-decimal pl-5">
                        <li>Upload ton fichier .torrent</li>
                        <li>Le fichier <strong>global</strong> est automatiquement edite : metadonnees nettoyees, hash change</li>
                        <li>Le torrent est <strong>splitte</strong> en parties de taille max (defaut 1 Go)</li>
                        <li>Chaque partie a des <strong>noms, trackers, source, hash completement aleatoires</strong></li>
                        <li>Tu telecharges le <strong>fichier global edite</strong> ET/OU le <strong>ZIP des parties</strong></li>
                    </ol>
                </div>

                <form action="{{ route('torrent.split.post') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-5">
                        <label for="torrent" class="block text-sm font-medium text-gray-700 mb-1">Fichier .torrent</label>
                        <input type="file" name="torrent" id="torrent" accept=".torrent"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div class="mb-5">
                        <div class="text-center text-xs text-gray-400 mb-2">— OU —</div>
                        <label for="torrent_url" class="block text-sm font-medium text-gray-700 mb-1">Lien vers le .torrent (URL directe)</label>
                        <input type="url" name="torrent_url" id="torrent_url" value="{{ old('torrent_url') }}"
                               placeholder="https://tracker.example/download/123.torrent"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div class="mb-5">
                        <label for="max_size" class="block text-sm font-medium text-gray-700 mb-1">Taille max par partie (Go)</label>
                        <input type="number" name="max_size" id="max_size" step="0.1" min="0.1" max="100" value="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Chaque partie fera au plus cette taille. Les fichiers plus gros auront leur propre torrent.</p>
                    </div>

                    {{-- Auto features info --}}
                    <div class="mb-5 p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 mb-2">Automatiquement applique :</p>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div class="flex items-center gap-1">
                                <span class="text-green-500">&#10003;</span> Noms de fichiers randomises
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-green-500">&#10003;</span> Hash unique par partie
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-green-500">&#10003;</span> Trackers aleatoires
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-green-500">&#10003;</span> Source / client changes
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-green-500">&#10003;</span> Commentaire nettoye
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-green-500">&#10003;</span> Date de creation changee
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Splitter + Randomiser
                        </button>
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center px-4 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                            Retour
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
