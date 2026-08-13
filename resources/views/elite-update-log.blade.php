<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Elite Update Log</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; }
        h1 { font-size: 1.25rem; }
        table { border-collapse: collapse; width: 100%; font-size: 0.85rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem 0.6rem; text-align: left; vertical-align: top; }
        th { background: #f4f4f4; }
        tr:nth-child(even) { background: #fafafa; }
        .result-success { color: #1a7f37; font-weight: 600; }
        .result-policy_not_found, .result-risk_not_found { color: #9a6700; }
        .result-ambiguous, .result-db_error { color: #cf222e; font-weight: 600; }
        .empty { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <h1>Elite Update Log</h1>
    <p>Most recent {{ count($entries) }} entries (newest first).</p>

    @if (empty($entries))
        <p class="empty">No log entries yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>IP</th>
                    <th>Policy No</th>
                    <th>Old Value</th>
                    <th>New Value</th>
                    <th>Result</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $entry['timestamp'] }}</td>
                        <td>{{ $entry['ip'] }}</td>
                        <td>{{ $entry['policy_no'] }}</td>
                        <td>{{ $entry['old_value'] }}</td>
                        <td>{{ $entry['new_value'] }}</td>
                        <td class="result-{{ $entry['result'] }}">{{ $entry['result'] }}</td>
                        <td>{{ $entry['error'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
