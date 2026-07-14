<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Magnet Link
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

                {{-- Info --}}
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">Comment ca marche</h3>
                    <ol class="text-sm text-blue-700 space-y-1 list-decimal pl-5">
                        <li>Colle ton lien magnet</li>
                        <li>L'app se connecte au reseau BitTorrent (DHT + peers)</li>
                        <li>Telecharge les metadonnees du torrent (pas le contenu)</li>
                        <li>Applique l'action choisie (split, edit, ou juste download)</li>
                    </ol>
                    <p class="text-xs text-blue-600 mt-2">La resolution peut prendre 10-60 secondes selon la disponibilite des peers.</p>
                </div>

                <form action="{{ route('torrent.magnet.post') }}" method="POST">
                    @csrf

                    <div class="mb-5">
                        <label for="magnet" class="block text-sm font-medium text-gray-700 mb-1">Lien Magnet</label>
                        <textarea name="magnet" id="magnet" rows="3" required
                                  placeholder="magnet:?xt=urn:btih:..."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-mono text-xs">{{ old('magnet') }}</textarea>
                    </div>

                    <div class="mb-5">
                        <label for="action" class="block text-sm font-medium text-gray-700 mb-1">Action apres resolution</label>
                        <select name="action" id="action" onchange="toggleMaxSize()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="split">Split + Randomize (parties de 1Go max)</option>
                            <option value="edit_only">Editer seulement (randomiser metadonnees + hash)</option>
                            <option value="download_only">Telecharger le .torrent tel quel</option>
                        </select>
                    </div>

                    <div class="mb-5" id="max-size-group">
                        <label for="max_size" class="block text-sm font-medium text-gray-700 mb-1">Taille max par partie (Go)</label>
                        <input type="number" name="max_size" id="max_size" step="0.1" min="0.1" max="100" value="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            Resoudre le Magnet
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

    <script>
    function toggleMaxSize() {
        const action = document.getElementById('action').value;
        document.getElementById('max-size-group').style.display = action === 'split' ? 'block' : 'none';
    }
    toggleMaxSize();
    </script>
</x-app-layout>
