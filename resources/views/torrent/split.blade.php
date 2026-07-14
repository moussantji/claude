@extends('torrent.layout')

@section('content')
<div class="card">
    <h2 style="color:#00d4ff; margin-bottom:1.5rem;">Splitter un torrent</h2>

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('torrent.split.post') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="torrent">Fichier .torrent a splitter</label>
            <input type="file" name="torrent" id="torrent" accept=".torrent" required>
            <p class="help-text">Doit etre un torrent multi-fichiers</p>
        </div>

        <div class="form-group">
            <label for="max_size">Taille max par partie (en Go)</label>
            <input type="number" name="max_size" id="max_size" step="0.1" min="0.1" max="100" value="1" placeholder="1.0">
            <p class="help-text">Chaque torrent genere fera au plus cette taille. Defaut: 1 Go</p>
        </div>

        <div class="form-group">
            <label for="randomize" style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;">
                <input type="checkbox" name="randomize" id="randomize" value="1" {{ old('randomize') ? 'checked' : '' }}
                       style="width:auto; accent-color:#00d4ff;">
                <strong>Randomiser les metadonnees</strong>
            </label>
            <p class="help-text" style="margin-top:0.5rem;">
                Active: chaque partie aura un <strong>nom de torrent aleatoire</strong>, des <strong>noms de fichiers internes differents</strong>,
                des <strong>trackers, commentaire, source, client</strong> aleatoires, et un <strong>hash unique</strong>.<br>
                Les parties seront totalement meconnaissables par rapport au torrent original.
            </p>
        </div>

        <div class="alert alert-info" style="margin-top:1.5rem;">
            <p><strong>Comment ca marche ?</strong></p>
            <ul style="margin-top:0.5rem; padding-left:1.2rem;">
                <li>Les fichiers sont regroupes en parties de taille max</li>
                <li>Chaque partie devient un .torrent independant</li>
                <li>Les fichiers trop gros pour une partie ont leur propre torrent</li>
                <li>Le resultat est telecharge en ZIP si plusieurs parties</li>
            </ul>
            <p style="margin-top:0.5rem; color:#ffaa00;">
                Les nouveaux torrents ont un hash different : les peers originaux ne pourront pas seeder ces parties.
            </p>
        </div>

        <div class="actions">
            <button type="submit" class="btn">Splitter le torrent</button>
            <a href="{{ route('torrent.index') }}" class="btn btn-secondary">Retour</a>
        </div>
    </form>
</div>
@endsection
