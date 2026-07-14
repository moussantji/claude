@extends('torrent.layout')

@section('content')
<div class="card">
    <div class="alert alert-success">
        <p><strong>Success!</strong> Your torrent file has been edited.</p>
    </div>

    <div class="form-group">
        <label>Edited File</label>
        <p style="color: #eee;">{{ $filename }}</p>
    </div>

    @if(session('output'))
        <div class="form-group">
            <label>Command Output</label>
            <pre>{{ session('output') }}</pre>
        </div>
    @endif

    <div class="actions">
        <a href="{{ route('torrent.download') }}" class="btn">Download Edited Torrent</a>
        <a href="{{ route('torrent.index') }}" class="btn btn-secondary">Edit Another</a>
    </div>
</div>
@endsection
