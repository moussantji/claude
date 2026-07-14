<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Resultat
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                {{-- Success message --}}
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-green-800 font-semibold">Torrent traite avec succes !</p>
                </div>

                {{-- Split results: both global and split downloads --}}
                @if(session('split_results'))
                    <div class="mb-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Fichiers disponibles</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Global edited torrent --}}
                            @if(session('global_torrent'))
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-800 mb-2">Fichier Global (edite)</h4>
                                    <p class="text-sm text-gray-600 mb-3">
                                        Le torrent complet avec metadonnees randomisees (hash change, source nettoyee).
                                    </p>
                                    <a href="{{ route('torrent.download', ['type' => 'global']) }}"
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                        Telecharger le global
                                    </a>
                                </div>
                            @endif

                            {{-- Split parts --}}
                            @if(session('split_zip'))
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-800 mb-2">Parties Splittees ({{ session('split_results')['split_count'] ?? '?' }} parts)</h4>
                                    <p class="text-sm text-gray-600 mb-3">
                                        Chaque partie &le; taille max, avec noms/hash/trackers totalement randomises.
                                    </p>
                                    <a href="{{ route('torrent.download', ['type' => 'split']) }}"
                                       class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700">
                                        Telecharger les parties (ZIP)
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                {{-- Normal single file result --}}
                @else
                    <div class="mb-6">
                        <p class="text-gray-700 mb-3">
                            <strong>Fichier :</strong> {{ $filename ?? session('edited_torrent_name', 'edited.torrent') }}
                        </p>
                        <a href="{{ route('torrent.download') }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Telecharger le torrent edite
                        </a>
                    </div>
                @endif

                {{-- Command output --}}
                @if(session('output'))
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Details de l'operation :</h4>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-x-auto max-h-96 overflow-y-auto">{{ session('output') }}</pre>
                    </div>
                @endif

                {{-- Back button --}}
                <div class="mt-6 flex gap-3">
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-md hover:bg-gray-300">
                        Retour au dashboard
                    </a>
                    <a href="{{ route('torrent.split') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-md hover:bg-gray-300">
                        Split un autre torrent
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
