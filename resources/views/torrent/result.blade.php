<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Resultat
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-green-800 font-semibold">Torrent traite avec succes !</p>
                </div>

                @php $sr = session('split_results'); @endphp

                {{-- SPLIT RESULTS: global + individual parts --}}
                @if($sr)
                    {{-- Global file --}}
                    @if(!empty($sr['global_file']))
                        <div class="mb-6 border border-gray-200 rounded-lg p-4">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Fichier Global (edite)</h3>
                            <p class="text-sm text-gray-600 mb-3">
                                Le torrent complet avec metadonnees randomisees et hash change.
                            </p>
                            <a href="{{ route('torrent.download', ['file' => $sr['global_file']]) }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Telecharger le global (.torrent)
                            </a>
                        </div>
                    @endif

                    {{-- Individual split parts --}}
                    @if(!empty($sr['part_files']))
                        <div class="mb-6 border border-gray-200 rounded-lg p-4">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                Parties splittees ({{ $sr['split_count'] }})
                            </h3>
                            <p class="text-sm text-gray-600 mb-3">
                                Chaque partie est un .torrent independant, avec noms/hash/trackers randomises.
                                Telecharge-les individuellement (recommande) ou tout en ZIP.
                            </p>

                            <div class="space-y-2 mb-4">
                                @foreach($sr['part_files'] as $i => $part)
                                    <div class="flex items-center justify-between bg-gray-50 rounded px-3 py-2">
                                        <span class="text-sm text-gray-700 font-mono truncate mr-3">
                                            Part {{ $i + 1 }}
                                        </span>
                                        <a href="{{ route('torrent.download', ['file' => $part]) }}"
                                           class="shrink-0 inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700">
                                            Telecharger .torrent
                                        </a>
                                    </div>
                                @endforeach
                            </div>

                            @if(!empty($sr['zip_file']))
                                <div class="pt-3 border-t border-gray-200">
                                    <a href="{{ route('torrent.download', ['file' => $sr['zip_file']]) }}"
                                       class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700">
                                        Tout telecharger (ZIP)
                                    </a>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Note: le ZIP doit etre extrait avant d'ajouter les .torrent a ton client.
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                {{-- SINGLE FILE RESULT --}}
                @elseif(session('single_file'))
                    <div class="mb-6 border border-gray-200 rounded-lg p-4">
                        <a href="{{ route('torrent.download', ['file' => session('single_file')]) }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Telecharger le torrent (.torrent)
                        </a>
                    </div>
                @endif

                {{-- Command output --}}
                @if(session('output'))
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Details :</h4>
                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-x-auto max-h-96 overflow-y-auto">{{ session('output') }}</pre>
                    </div>
                @endif

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
