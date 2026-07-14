<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Generer un Torrent Aleatoire
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

                <form action="{{ route('torrent.generate.post') }}" method="POST">
                    @csrf

                    <div class="mb-5">
                        <label for="num_files" class="block text-sm font-medium text-gray-700 mb-1">Nombre de fichiers</label>
                        <input type="number" name="num_files" id="num_files" min="1" max="50" placeholder="Aleatoire (1-8)"
                               value="{{ old('num_files') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div class="mb-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="private" value="1" {{ old('private') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Torrent prive</span>
                        </label>
                    </div>

                    <div class="mb-5">
                        <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                        <input type="text" name="source" id="source" placeholder="Aleatoire si vide"
                               value="{{ old('source') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div class="mb-5">
                        <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                        <input type="text" name="comment" id="comment" placeholder="Auto-genere si vide"
                               value="{{ old('comment') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div class="mb-5">
                        <label for="created_by" class="block text-sm font-medium text-gray-700 mb-1">Cree par (client)</label>
                        <input type="text" name="created_by" id="created_by" placeholder="Aleatoire si vide"
                               value="{{ old('created_by') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Generer
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
