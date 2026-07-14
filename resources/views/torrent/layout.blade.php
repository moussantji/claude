<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Torrent Editor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #00d4ff;
            font-size: 2rem;
        }
        .card {
            background: #16213e;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #aaa;
        }
        input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            background: #0f3460;
            border: 2px dashed #00d4ff;
            border-radius: 8px;
            color: #eee;
            cursor: pointer;
        }
        select, textarea, input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            background: #0f3460;
            border: 1px solid #333;
            border-radius: 8px;
            color: #eee;
            font-size: 0.95rem;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
            font-family: monospace;
        }
        select:focus, textarea:focus, input[type="text"]:focus {
            outline: none;
            border-color: #00d4ff;
        }
        .alert-info {
            background: #152e3d;
            border: 1px solid #00d4ff;
            color: #b0e0ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        code {
            background: #0f3460;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #00d4ff;
        }
        .btn {
            display: inline-block;
            padding: 0.85rem 2rem;
            background: #00d4ff;
            color: #1a1a2e;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #00b8e6;
        }
        .btn-secondary {
            background: #555;
            color: #eee;
        }
        .btn-secondary:hover {
            background: #666;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: #3d1515;
            border: 1px solid #e74c3c;
            color: #ff6b6b;
        }
        .alert-success {
            background: #153d1f;
            border: 1px solid #2ecc71;
            color: #6bff8a;
        }
        .help-text {
            font-size: 0.85rem;
            color: #777;
            margin-top: 0.3rem;
        }
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        pre {
            background: #0f3460;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            color: #ccc;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Torrent Editor</h1>
        @yield('content')
    </div>
</body>
</html>
