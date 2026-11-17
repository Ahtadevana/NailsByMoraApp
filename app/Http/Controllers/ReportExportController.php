<?php

namespace App\Http\Controllers;

use App\Services\ReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ZipArchive;

class ReportExportController extends Controller
{
    public function export(Request $request, string $format, ReportGenerator $reports)
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        $filters = $request->validate(ReportController::rules(true));
        $report = $reports->generate(
            $filters,
            1,
            (int) ($filters['per_page'] ?? 25),
            true,
        );
        $filename = $this->filename($report, $filters, $format);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($report): void {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['NAILS Studio', $report['title']]);
                fputcsv($output, ['Periode', $report['period']]);
                fputcsv($output, ['Dibuat', $report['generated_at']]);
                fputcsv($output, []);

                foreach ($report['metrics'] as $metric) {
                    fputcsv($output, [$metric['label'], $this->csvValue($metric['value'])]);
                }

                foreach ($report['tables'] as $table) {
                    fputcsv($output, []);
                    fputcsv($output, [$table['title']]);
                    fputcsv($output, array_column($table['columns'], 'label'));
                    foreach ($table['rows'] as $row) {
                        fputcsv($output, array_map(
                            fn (array $column): string|int|float => $this->csvValue($row[$column['key']] ?? ''),
                            $table['columns'],
                        ));
                    }
                }

                fclose($output);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $path = tempnam(sys_get_temp_dir(), 'nails-report-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary workbook file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new \RuntimeException('Unable to create the report workbook.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->packageRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->summarySheet($report));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->detailsSheet($report));
        $zip->close();

        return response()->streamDownload(function () use ($path): void {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function print(Request $request, ReportGenerator $reports)
    {
        $filters = $request->validate(ReportController::rules(true));
        $report = $reports->generate($filters, 1, (int) ($filters['per_page'] ?? 25), true);

        return response()
            ->view('reports.print', ['report' => $report])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function filename(array $report, array $filters, string $extension): string
    {
        $period = $report['type'] === 'inventory_status'
            ? now()->toDateString()
            : $filters['start_date'].'-to-'.$filters['end_date'];

        return Str::slug($report['title']).'-'.$period.'.'.$extension;
    }

    private function csvValue(mixed $value): string|int|float
    {
        if (is_array($value)) {
            return implode('; ', array_map(
                fn (array $item): string => $item['quantity'].' '.$item['unit'],
                $value,
            ));
        }

        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return is_scalar($value) || $value === null ? (string) $value : '';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Ringkasan" sheetId="1" r:id="rId1"/><sheet name="Data laporan" sheetId="2" r:id="rId2"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'</Relationships>';
    }

    private function summarySheet(array $report): string
    {
        $rows = [
            ['NAILS Studio'],
            [$report['title']],
            ['Periode', $report['period']],
            ['Dibuat', $report['generated_at']],
            [],
            ...array_map(fn (array $metric): array => [$metric['label'], $this->workbookMetric($metric['value'])], $report['metrics']),
        ];

        return $this->worksheet($rows);
    }

    private function detailsSheet(array $report): string
    {
        $rows = [];
        foreach ($report['tables'] as $table) {
            $rows[] = [$table['title']];
            $rows[] = array_column($table['columns'], 'label');
            foreach ($table['rows'] as $row) {
                $rows[] = array_map(
                    fn (array $column): mixed => $row[$column['key']] ?? '',
                    $table['columns'],
                );
            }
            $rows[] = [];
        }

        return $this->worksheet($rows);
    }

    private function workbookMetric(mixed $value): string
    {
        return is_array($value)
            ? implode('; ', array_map(fn (array $item): string => $item['quantity'].' '.$item['unit'], $value))
            : (string) $value;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="'.$number.'">';
            foreach (array_values($row) as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $cell = $this->columnLetter($columnIndex + 1).$number;
                if (is_int($value) || is_float($value) || (is_numeric($value) && ! is_string($value))) {
                    $xml .= '<c r="'.$cell.'"><v>'.(float) $value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $column = intdiv($column - 1, 26);
        }

        return $letter;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
