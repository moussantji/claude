@extends('torrent.layout')

@section('content')
<div class="card">
    <h2 style="color:#00d4ff; margin-bottom:1.5rem;">Générer un .torrent aléatoire</h2>

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('torrent.generate.post') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="num_files">Nombre de fichiers</label>
            <input type="number" name="num_files" id="num_files" min="1" max="50" placeholder="Aléatoire (1-8 si vide)" value="{{ old('num_files') }}">
            <p class="help-text">Laissez vide pour un nombre aléatoire (1 à 8)</p>
        </div>

        <div class="form-group">
            <label for="private" style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;">
                <input type="checkbox" name="private" id="private" value="1" {{ old('private') ? 'checked' : '' }}
                       style="width:auto; accent-color:#00d4ff;">
                Torrent privé (désactiver DHT/PEX)
            </label>
        </div>

        <div class="form-group">
            <label for="source">Source</label>
            <input type="text" name="source" id="source" placeholder="Aléatoire si vide (ex: TorrentLeech, RED...)" value="{{ old('source') }}">
        </div>

        <div class="form-group">
            <label for="comment">Commentaire</label>
            <input type="text" name="comment" id="comment" placeholder="Auto-généré si vide" value="{{ old('comment') }}">
        </div>

        <div class="form-group">
            <label for="created_by">Créé par (client)</label>
            <input type="text" name="created_by" id="created_by" placeholder="Aléatoire si vide (ex: qBittorrent 4.6.3)" value="{{ old('created_by') }}">
        </div>

        <div class="actions">
            <button type="submit" class="btn">Générer le torrent</button>
            <a href="{{ route('torrent.index') }}" class="btn btn-secondary">Retour à l'éditeur</a>
        </div>
    </form>
</div>
@endsection
