<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editer en gardant le hash (reste telechargeable)
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

                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <h3 class="font-semibold text-green-800 mb-2">Ce mode garde le hash intact</h3>
                    <p class="text-sm text-green-700">
                        Le torrent reste <strong>telechargeable avec les memes seeds</strong>.
                        On ne touche qu'aux champs hors du dictionnaire <code>info</code>.
                    </p>
                    <div class="grid grid-cols-2 gap-2 text-xs text-green-700 mt-3">
                        <div>Modifiable : trackers</div>
                        <div>Modifiable : commentaire</div>
                        <div>Modifiable : cree par</div>
                        <div>Modifiable : date</div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        Note: le nom, les fichiers internes, la source et le flag prive ne peuvent PAS
                        etre changes sans casser le hash (et donc perdre les seeds).
                    </p>
                </div>

                <form action="{{ route('torrent.keephash.post') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-5">
                        <label for="torrent" class="block text-sm font-medium text-gray-700 mb-1">Fichier .torrent</label>
                        <input type="file" name="torrent" id="torrent" accept=".torrent" required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                    </div>

                    <div class="mb-5">
                        <label for="trackers" class="block text-sm font-medium text-gray-700 mb-1">Nouveaux trackers (remplace tout)</label>
                        <textarea name="trackers" id="trackers" rows="4"
                                  placeholder="udp://tracker.opentrackr.org:1337/announce&#10;udp://open.stealth.si:80/announce"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm font-mono">{{ old('trackers') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Un tracker par ligne. Laisse vide pour garder les trackers actuels.</p>
                    </div>

                    <div class="mb-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="randomize_meta" value="1" onchange="toggleMeta()" id="rnd"
                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500" {{ old('randomize_meta') ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">Randomiser commentaire / cree par / date</span>
                        </label>
                    </div>

                    <div id="meta-fields">
                        <div class="mb-4">
                            <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                            <input type="text" name="comment" id="comment" value="{{ old('comment') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                        </div>
                        <div class="mb-5">
                            <label for="created_by" class="block text-sm font-medium text-gray-700 mb-1">Cree par</label>
                            <input type="text" name="created_by" id="created_by" value="{{ old('created_by') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                            Editer (garder le hash)
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
    function toggleMeta() {
        document.getElementById('meta-fields').style.display = document.getElementById('rnd').checked ? 'none' : 'block';
    }
    toggleMeta();
    </script>
</x-app-layout>
