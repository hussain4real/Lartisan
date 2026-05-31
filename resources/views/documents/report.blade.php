<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Lartisan Report</title>
    <style>
        body {
            color: #1d1d1d;
            font-family: sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        h1 {
            color: #001c72;
            font-size: 24px;
            margin-bottom: 4px;
        }

        table {
            border-collapse: collapse;
            margin-top: 24px;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #d8dee9;
            padding: 10px;
            text-align: left;
        }

        th {
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <h1>Lartisan Scoped Report</h1>
    <p>Generated at {{ $snapshot->generated_at->toDayDateTimeString() }}</p>

    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($snapshot->metrics as $metric => $value)
                <tr>
                    <td>{{ str_replace('_', ' ', $metric) }}</td>
                    <td>{{ is_numeric($value) ? number_format((float) $value) : $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
