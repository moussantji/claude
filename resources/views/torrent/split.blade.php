@extends('torrent.layout')

@section('content')
<div class="card">
    <h2 style="color:#00d4ff; margin-bottom:1.5rem;">Splitter un torrent (max par partie)</h2>

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="alert alert-info" style="margin-bottom:1.5rem;">
        <p><strong>Comment ca marche ?</strong></p>
        <p>Upload un torrent multi-fichiers. L'outil regroupe les fichiers en parties de taille maximale (defaut: 1 Go). Chaque partie devient un .torrent independant avec ses propres trackers et hash.</p>
        <p style="margin-top:0.5rem; color:#ffaa00;">Les fichiers plus gros que la taille max auront leur propre torrent.</p>
    </div>

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

        <div class="actions">
            <button type="submit" class="btn">Splitter le torrent</button>
            <a href="{{ route('torrent.index') }}" class="btn btn-secondary">Retour</a>
        </div>
    </form>
</div>
@endsection
