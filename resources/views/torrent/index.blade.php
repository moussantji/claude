@extends('torrent.layout')

@section('content')
<div class="card">
    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('torrent.edit') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="torrent">Fichier .torrent</label>
            <input type="file" name="torrent" id="torrent" accept=".torrent" required>
            <p class="help-text">Sélectionnez un fichier .torrent à éditer (max 10 Mo)</p>
        </div>

        <div class="form-group">
            <label for="action">Action</label>
            <select name="action" id="action" onchange="toggleFields()">
                <optgroup label="Trackers">
                    <option value="add_trackers">Ajouter des trackers</option>
                    <option value="remove_trackers">Supprimer des trackers</option>
                    <option value="replace_trackers">Remplacer tous les trackers</option>
                </optgroup>
                <optgroup label="Confidentialité">
                    <option value="make_private">Rendre privé (désactiver DHT/PEX)</option>
                    <option value="make_public">Rendre public (activer DHT/PEX)</option>
                </optgroup>
                <optgroup label="Avancé">
                    <option value="rename_files">Renommer les fichiers internes</option>
                    <option value="alter_metadata">Altérer les métadonnées</option>
                    <option value="change_hash">Changer le hash (randomiser)</option>
                </optgroup>
            </select>
        </div>

        {{-- Trackers field --}}
        <div class="form-group field-group" id="trackers-group">
            <label for="trackers">URLs des trackers</label>
            <textarea name="trackers" id="trackers" placeholder="https://tracker1.example.com/announce&#10;https://tracker2.example.com/announce&#10;&#10;Un tracker par ligne...">{{ old('trackers') }}</textarea>
            <p class="help-text">Un URL de tracker par ligne</p>
        </div>

        {{-- Rename field --}}
        <div class="form-group field-group" id="rename-group" style="display:none;">
            <label for="renames">Renommages</label>
            <textarea name="renames" id="renames" placeholder="ancien_nom.mkv=nouveau_nom.mkv&#10;dossier/fichier.nfo=dossier/nouveau.nfo&#10;&#10;Format : ancien=nouveau (un par ligne)">{{ old('renames') }}</textarea>
            <p class="help-text">Format : <code>ancien_nom=nouveau_nom</code> — un renommage par ligne.<br>
                Pour le nom du torrent (dossier racine), utilisez directement le nom actuel.<br>
                Pour les sous-fichiers, utilisez le chemin relatif (ex: <code>subs/english.srt=subs/francais.srt</code>)</p>
        </div>

        {{-- Metadata fields --}}
        <div class="field-group" id="metadata-group" style="display:none;">
            <div class="form-group">
                <label for="comment">Commentaire</label>
                <input type="text" name="comment" id="comment" placeholder="Nouveau commentaire (vide = supprimer)" value="{{ old('comment') }}">
            </div>
            <div class="form-group">
                <label for="created_by">Créé par</label>
                <input type="text" name="created_by" id="created_by" placeholder="Ex: qBittorrent 4.6.0 (vide = supprimer)" value="{{ old('created_by') }}">
            </div>
            <div class="form-group">
                <label for="creation_date">Date de création</label>
                <input type="text" name="creation_date" id="creation_date" placeholder="now / timestamp unix / 0 pour supprimer" value="{{ old('creation_date') }}">
                <p class="help-text">Valeurs : <code>now</code> (date actuelle), un timestamp unix, ou <code>0</code> pour supprimer</p>
            </div>
            <div class="form-group">
                <label for="source">Source</label>
                <input type="text" name="source" id="source" placeholder="Ex: MonTracker (vide = supprimer)" value="{{ old('source') }}">
                <p class="help-text">Champ source dans le dictionnaire info — utilisé par certains trackers privés</p>
            </div>
        </div>

        {{-- Hash info --}}
        <div class="field-group" id="hash-group" style="display:none;">
            <div class="alert alert-info">
                <p><strong>Randomiser le hash</strong></p>
                <p>Cette action injecte un champ aléatoire dans le dictionnaire <code>info</code> du torrent, ce qui produit un hash complètement différent. Le contenu des fichiers reste identique.</p>
                <p style="margin-top:0.5rem; color:#ffaa00;">⚠️ Les anciens peers ne pourront plus vous trouver avec ce nouveau hash.</p>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn">Éditer le torrent</button>
            <a href="{{ route('torrent.generate') }}" class="btn btn-secondary">Générer un torrent aléatoire</a>
        </div>
    </form>
</div>

<script>
function toggleFields() {
    const action = document.getElementById('action').value;

    // Hide all field groups
    document.querySelectorAll('.field-group').forEach(el => el.style.display = 'none');

    // Show relevant group
    if (['add_trackers', 'remove_trackers', 'replace_trackers'].includes(action)) {
        document.getElementById('trackers-group').style.display = 'block';
    } else if (action === 'rename_files') {
        document.getElementById('rename-group').style.display = 'block';
    } else if (action === 'alter_metadata') {
        document.getElementById('metadata-group').style.display = 'block';
    } else if (action === 'change_hash') {
        document.getElementById('hash-group').style.display = 'block';
    }
}
// Run on load
toggleFields();
</script>
@endsection
