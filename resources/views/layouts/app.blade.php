<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Game Chat')</title>
    <style>
        :root {
            --bg: #12110f;
            --panel: #1c1a17;
            --line: #3a342c;
            --text: #ece7dc;
            --muted: #a39a8c;
            --accent: #c45c26;
            --mine: #2a241c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            background: var(--bg);
            color: var(--text);
        }
        a { color: var(--accent); }
        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 1.5rem 1rem 2rem;
        }
        .wrap.wide { max-width: 1100px; }
        .stage {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 1rem;
            align-items: start;
        }
        @media (max-width: 800px) {
            .stage { grid-template-columns: 1fr; }
        }
        .storyteller-panel h2 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--line);
            padding-bottom: 0.75rem;
        }
        .top h1 { margin: 0; font-size: 1.25rem; font-weight: 600; }
        .muted { color: var(--muted); font-size: 0.9rem; }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            padding: 1.25rem;
        }
        label { display: block; margin: 0.75rem 0 0.35rem; font-size: 0.9rem; }
        input[type="text"], input[type="email"], input[type="password"], textarea {
            width: 100%;
            padding: 0.6rem 0.7rem;
            background: #141210;
            color: var(--text);
            border: 1px solid var(--line);
            font: inherit;
        }
        textarea { min-height: 4.5rem; resize: vertical; }
        button, .btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.55rem 1rem;
            background: var(--accent);
            color: #fff;
            border: 0;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
        }
        button.link, form.inline { display: inline; }
        button.link {
            margin: 0;
            padding: 0;
            background: none;
            color: var(--accent);
            text-decoration: underline;
        }
        .error { color: #e07070; font-size: 0.85rem; margin: 0.25rem 0 0; }
        .chat-log {
            height: min(60vh, 480px);
            overflow-y: auto;
            border: 1px solid var(--line);
            background: #141210;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .msg {
            padding: 0.5rem 0.65rem;
            border: 1px solid var(--line);
            background: var(--panel);
        }
        .msg.mine { background: var(--mine); }
        .msg .meta { font-size: 0.8rem; color: var(--muted); margin-bottom: 0.25rem; }
        .composer { display: flex; gap: 0.5rem; align-items: flex-end; }
        .composer textarea { margin: 0; flex: 1; }
        .composer button { margin: 0; }
    </style>
</head>
<body>
    <div class="wrap @yield('wrap_class')">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
