<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} · NailsByMora</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #292426; }
        body { margin: 32px auto; max-width: 1100px; padding: 0 24px; }
        header { border-bottom: 2px solid #7c2838; padding-bottom: 18px; }
        h1 { margin: 8px 0; font-size: 25px; }
        h2 { margin: 26px 0 10px; font-size: 17px; }
        .eyebrow, .muted { color: #71696b; font-size: 12px; }
        .metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 20px; }
        .metric { border: 1px solid #e8e0dd; border-radius: 9px; padding: 12px; }
        .metric strong { display: block; margin-top: 7px; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border-bottom: 1px solid #e8e0dd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f5eeeb; font-size: 10px; text-transform: uppercase; }
        .number { text-align: right; }
        .toolbar { position: fixed; right: 20px; top: 16px; }
        @media print {
            body { max-width: none; margin: 0; padding: 0; }
            .toolbar { display: none; }
            .metrics, table, section { break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <button class="toolbar" type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    <header>
        <p class="eyebrow">NailsByMora · Laporan</p>
        <h1>{{ $report['title'] }}</h1>
        <p><strong>Periode:</strong> {{ $report['period'] }}</p>
        <p class="muted">Dibuat {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->locale('id')->translatedFormat('j F Y, H:i') }}</p>
    </header>
    <section class="metrics">
        @foreach ($report['metrics'] as $metric)
            <article class="metric">
                <span class="muted">{{ $metric['label'] }}</span>
                @if (is_array($metric['value']))
                    <strong>{{ collect($metric['value'])->map(fn ($quantity) => $quantity['quantity'].' '.$quantity['unit'])->join(', ') ?: '0' }}</strong>
                @elseif (($metric['format'] ?? '') === 'currency')
                    <strong>Rp {{ number_format((float) $metric['value'], 0, ',', '.') }}</strong>
                @else
                    <strong>{{ $metric['value'] }}</strong>
                @endif
            </article>
        @endforeach
    </section>
    @foreach ($report['tables'] as $table)
        <section>
            <h2>{{ $table['title'] }}</h2>
            @if (count($table['rows']))
                <table>
                    <thead><tr><th>No.</th>@foreach ($table['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($table['rows'] as $index => $row)
                            <tr>
                                <td>{{ ($table['pagination']['from'] ?? 1) + $index }}</td>
                                @foreach ($table['columns'] as $column)
                                    @php($value = $row[$column['key']] ?? null)
                                    <td @class(['number' => ($column['format'] ?? '') === 'currency'])>
                                        @if (($column['format'] ?? '') === 'currency')
                                            Rp {{ number_format((float) $value, 0, ',', '.') }}
                                        @elseif (($column['format'] ?? '') === 'date')
                                            {{ \Illuminate\Support\Carbon::parse($value)->locale('id')->translatedFormat('j M Y') }}
                                        @elseif (($column['format'] ?? '') === 'percent')
                                            {{ number_format((float) $value, 1) }}%
                                        @else
                                            {{ $value ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="muted">Tidak ada data untuk periode yang dipilih.</p>
            @endif
        </section>
    @endforeach
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
