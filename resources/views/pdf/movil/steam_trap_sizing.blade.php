<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dimensionamiento de Trampa de Vapor</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 14px;
            margin-top: 18px;
            margin-bottom: 8px;
        }

        pre {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 10px;
        }

        .header {
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Dimensionamiento de Trampa de Vapor</h1>
        <div class="muted">
            Generado: {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>

    <h2>Datos enviados desde Flutter</h2>
    <pre>{{ json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

    <h2>Parámetros enviados al SP</h2>
    <pre>{{ json_encode($spParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

    <h2>Ítem seleccionado</h2>
    <pre>{{ json_encode($selectedItem, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

    <h2>Ítems origen</h2>
    <pre>{{ json_encode($originItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

    <h2>Respuesta cruda completa</h2>
    <pre>{{ json_encode($rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

</body>
</html>
