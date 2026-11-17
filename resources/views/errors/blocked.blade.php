<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#050203">
    <title>Access blocked</title>
    <style>
        html, body { margin: 0; height: 100%; }
        body {
            display: grid;
            place-items: center;
            background: radial-gradient(ellipse at 50% 45%, #2a0307, #050203 65%);
            color: #e7d6d6;
            font-family: ui-monospace, 'Cascadia Mono', Consolas, 'Courier New', monospace;
            text-align: center;
            padding: 24px;
        }
        h1 {
            margin: 0 0 18px;
            font-family: Impact, Haettenschweiler, 'Arial Narrow', sans-serif;
            font-size: clamp(34px, 7vw, 76px);
            letter-spacing: 0.04em;
            color: #d81f2c;
            text-shadow: 0 0 24px rgba(255, 0, 20, 0.35);
        }
        p { margin: 6px 0; font-size: 15px; color: rgba(231, 214, 214, 0.75); }
        code { color: #ff6b74; }
    </style>
</head>
<body>
    <main>
        <h1>YOU ARE NOT WELCOME HERE.</h1>
        <p>The address <code>{{ $ip }}</code> has been blocked from this site.</p>
        <p>Every visit is still recorded.</p>
    </main>
</body>
</html>
