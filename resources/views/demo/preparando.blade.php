<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Preparando tu demo — Renta Fácil</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               font-family:Roboto,system-ui,sans-serif; background:#0f172a; color:#fff; text-align:center; padding:24px; }
        .spinner { width:48px; height:48px; margin:0 auto 24px; border:4px solid rgba(255,255,255,.2);
                   border-top-color:#06b6d4; border-radius:50%; animation:spin 1s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        h1 { font-size:22px; margin:0 0 8px; }
        p { color:#94a3b8; margin:0; font-size:15px; }
        .error { color:#fca5a5; margin-top:16px; display:none; }
    </style>
</head>
<body>
    <div>
        <div class="spinner"></div>
        <h1>Preparando tu demo…</h1>
        <p>Estamos creando un negocio de ejemplo solo para ti.</p>
        <p class="error" id="error">No pudimos crear el demo. Recarga la página para intentarlo de nuevo.</p>
    </div>

    <script>
        fetch('{{ route('demo.create') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(function (res) {
            if (!res.ok) { throw new Error('failed'); }
            return res.json();
        })
        .then(function (data) { window.location = data.url; })
        .catch(function () { document.getElementById('error').style.display = 'block'; });
    </script>
</body>
</html>
