<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editeur Manuel
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

                <form action="{{ route('torrent.edit') }}" method="POST" enctype="multipart/form-data">
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
                        <label for="action" class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                        <select name="action" id="action" onchange="toggleFields()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <optgroup label="Trackers">
                                <option value="add_trackers">Ajouter des trackers</option>
                                <option value="remove_trackers">Supprimer des trackers</option>
                                <option value="replace_trackers">Remplacer tous les trackers</option>
                            </optgroup>
                            <optgroup label="Confidentialite">
                                <option value="make_private">Rendre prive (desactiver DHT/PEX)</option>
                                <option value="make_public">Rendre public (activer DHT/PEX)</option>
                            </optgroup>
                            <optgroup label="Avance">
                                <option value="rename_files">Renommer les fichiers internes</option>
                                <option value="alter_metadata">Alterer les metadonnees</option>
                                <option value="change_hash">Changer le hash (randomiser)</option>
                            </optgroup>
                        </select>
                    </div>

                    {{-- Trackers --}}
                    <div class="mb-5 field-group" id="trackers-group">
                        <label for="trackers" class="block text-sm font-medium text-gray-700 mb-1">URLs des trackers</label>
                        <textarea name="trackers" id="trackers" rows="4"
                                  placeholder="https://tracker1.example.com/announce&#10;https://tracker2.example.com/announce"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-mono">{{ old('trackers') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Un URL par ligne</p>
                    </div>

                    {{-- Rename --}}
                    <div class="mb-5 field-group hidden" id="rename-group">
                        <label for="renames" class="block text-sm font-medium text-gray-700 mb-1">Renommages</label>
                        <textarea name="renames" id="renames" rows="4"
                                  placeholder="ancien_nom.mkv=nouveau_nom.mkv&#10;subs/english.srt=subs/francais.srt"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-mono">{{ old('renames') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Format: ancien=nouveau (un par ligne)</p>
                    </div>

                    {{-- Metadata --}}
                    <div class="field-group hidden" id="metadata-group">
                        <div class="mb-4">
                            <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                            <input type="text" name="comment" id="comment" placeholder="Vide = supprimer" value="{{ old('comment') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div class="mb-4">
                            <label for="created_by" class="block text-sm font-medium text-gray-700 mb-1">Cree par</label>
                            <input type="text" name="created_by" id="created_by" placeholder="Ex: qBittorrent 4.6.0" value="{{ old('created_by') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div class="mb-4">
                            <label for="creation_date" class="block text-sm font-medium text-gray-700 mb-1">Date de creation</label>
                            <input type="text" name="creation_date" id="creation_date" placeholder="now / timestamp / 0=supprimer" value="{{ old('creation_date') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div class="mb-5">
                            <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                            <input type="text" name="source" id="source" placeholder="Ex: MonTracker" value="{{ old('source') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>

                    {{-- Hash info --}}
                    <div class="field-group hidden mb-5" id="hash-group">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800"><strong>Randomiser le hash :</strong> injecte un champ aleatoire dans le dictionnaire info. Le hash change completement. Les peers originaux ne te retrouveront plus.</p>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Editer le torrent
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
    function toggleFields() {
        const action = document.getElementById('action').value;
        document.querySelectorAll('.field-group').forEach(el => el.classList.add('hidden'));

        if (['add_trackers', 'remove_trackers', 'replace_trackers'].includes(action)) {
            document.getElementById('trackers-group').classList.remove('hidden');
        } else if (action === 'rename_files') {
            document.getElementById('rename-group').classList.remove('hidden');
        } else if (action === 'alter_metadata') {
            document.getElementById('metadata-group').classList.remove('hidden');
        } else if (action === 'change_hash') {
            document.getElementById('hash-group').classList.remove('hidden');
        }
    }
    toggleFields();
    </script>
</x-app-layout>
