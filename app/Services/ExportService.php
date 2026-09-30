<?php

namespace App\Services;

use App\Models\AppSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV / Excel / PDF exports from plain headings + rows. */
class ExportService
{
    public const PDF_ROW_LIMIT = 3000;

    /**
     * @param  string[]  $headings
     * @param  iterable<array<int, scalar|null>>  $rows
     * @param  array<string,string>  $meta  shown above the table (filters etc.)
     * @param  bool[]  $numeric  per-column numeric flag (for alignment / number format)
     */
    public function download(string $format, string $title, array $headings, iterable $rows, string $filename, array $meta = [], array $numeric = []): Response
    {
        $filename = Str::slug($filename).'-'.now()->format('Ymd-His');

        return match ($format) {
            'csv' => $this->csv($title, $headings, $rows, $filename, $meta),
            'pdf' => $this->pdf($title, $headings, $rows, $filename, $meta, $numeric),
            default => $this->xlsx($title, $headings, $rows, $filename, $meta, $numeric),
        };
    }

    private function csv(string $title, array $headings, iterable $rows, string $filename, array $meta): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $headings, $rows, $meta) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Unicode correctly
            fputcsv($out, [$title]);
            foreach ($meta as $k => $v) {
                fputcsv($out, [$k, $v]);
            }
            fputcsv($out, []);
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $this->safeCell($v), $row));
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(string $title, array $headings, iterable $rows, string $filename, array $meta, array $numeric): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(Str::limit(preg_replace('/[\\\\\/?*\[\]:]/', '', $title), 28, ''));
        $r = 1;
        $sheet->setCellValue([1, $r], AppSetting::get('company_name').' — '.$title);
        $sheet->getStyle([1, $r])->getFont()->setBold(true)->setSize(13);
        $r++;
        foreach ($meta + ['Generated' => now()->format('d M Y H:i').' by '.(auth()->user()?->name ?? 'system')] as $k => $v) {
            $sheet->setCellValue([1, $r], $k);
            $sheet->setCellValueExplicit([2, $r], (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle([1, $r])->getFont()->setBold(true);
            $r++;
        }
        $r++;
        $headerRow = $r;
        foreach (array_values($headings) as $i => $h) {
            $sheet->setCellValue([$i + 1, $r], $h);
        }
        $last = count($headings);
        $sheet->getStyle([1, $r, $last, $r])->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle([1, $r, $last, $r])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        foreach ($rows as $row) {
            $r++;
            foreach (array_values($row) as $i => $v) {
                if (is_int($v) || is_float($v)) {
                    $sheet->setCellValue([$i + 1, $r], $v);
                } else {
                    $sheet->setCellValueExplicit([$i + 1, $r], (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
        for ($i = 1; $i <= $last; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $sheet->freezePane([1, $headerRow + 1]);
        if ($r > $headerRow) {
            $sheet->setAutoFilter([1, $headerRow, $last, $r]);
        }

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function pdf(string $title, array $headings, iterable $rows, string $filename, array $meta, array $numeric): Response
    {
        $list = [];
        $truncated = false;
        foreach ($rows as $row) {
            if (count($list) >= self::PDF_ROW_LIMIT) {
                $truncated = true;
                break;
            }
            $list[] = $row;
        }
        $pdf = Pdf::loadView('exports.pdf', [
            'title' => $title, 'headings' => $headings, 'rows' => $list, 'meta' => $meta, 'numeric' => $numeric, 'truncated' => $truncated,
        ])->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait');

        return $pdf->download($filename.'.pdf');
    }

    /** Neutralise spreadsheet formula injection in CSV. */
    private function safeCell(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($v)) {
            return "'".$v;
        }

        return $v;
    }
}
