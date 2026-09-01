<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SimpleSpreadsheetReader
{
    /**
     * @return array<int, array<int, string|null>>
     */
    public function read(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '' || $extension === 'tmp') {
            $extension = $this->detectFormat($path);
        }

        return match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException('فرمت فایل پشتیبانی نمی‌شود. فقط xlsx و csv مجاز است.'),
        };
    }

    private function detectFormat(string $path): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('خواندن فایل ممکن نشد.');
        }

        $header = fread($handle, 4) ?: '';
        fclose($handle);

        if (str_starts_with($header, "PK\x03\x04")) {
            return 'xlsx';
        }

        return 'csv';
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('خواندن فایل csv ممکن نشد.');
        }

        while (($data = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($data[0]) && is_string($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]) ?? $data[0];
            }

            $rows[] = array_map(fn ($value) => $this->normalizeCell($value), $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('افزونه ZipArchive روی سرور فعال نیست.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('باز کردن فایل xlsx ممکن نشد.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('شیت اول در فایل xlsx یافت نشد.');
        }

        $zip->close();

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('پردازش شیت xlsx ممکن نشد.');
        }

        $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowValues = [];
            foreach ($row->c as $cell) {
                $columnIndex = $this->columnIndexFromCellReference((string) $cell['r']);
                $rowValues[$columnIndex] = $this->readCellValue($cell, $sharedStrings);
            }

            if ($rowValues === []) {
                continue;
            }

            $maxIndex = max(array_keys($rowValues));
            $normalized = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $normalized[] = $rowValues[$i] ?? null;
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        if ($shared === false) {
            return [];
        }

        $shared->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $parts = [];
            foreach ($item->r as $run) {
                $parts[] = (string) $run->t;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function readCellValue(\SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return $this->normalizeCell((string) ($cell->is->t ?? ''));
        }

        $value = isset($cell->v) ? (string) $cell->v : '';

        if ($type === 's') {
            $index = (int) $value;

            return $this->normalizeCell($sharedStrings[$index] ?? '');
        }

        return $this->normalizeCell($value);
    }

    private function columnIndexFromCellReference(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
