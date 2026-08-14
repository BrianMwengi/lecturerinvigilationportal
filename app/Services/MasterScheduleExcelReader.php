<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MasterScheduleExcelReader
{
    private const DISK_NAME = 'master_schedule';
    private const MASTER_FILE_PATH = 'master_schedule.xlsx';
    private const SHEET_NAME = 'Sheet2';
    private const FIRST_DATA_ROW = 9; // row 8 holds the column headers

    public function readAllDuties(): Collection
    {
        $disk = Storage::disk(self::DISK_NAME);

        if (! $disk->exists(self::MASTER_FILE_PATH)) {
            return collect();
        }

        $worksheet = $this->loadWorksheet($disk);

        $duties = collect();
        $currentDate = null;
        $currentTimeRange = null;

        foreach ($worksheet->getRowIterator(self::FIRST_DATA_ROW) as $row) {
            $cellIterator = $row->getCellIterator('A', 'G');
            $cellIterator->setIterateOnlyExistingCells(false);

            $cellValues = [];
            foreach ($cellIterator as $cell) {
                $cellValues[] = $cell->getValue();
            }

            [$dateCell, $timeCell, $courseCodes, $studentCountRaw, $room, $lecturerName, $invigilatorName] = $cellValues;

            if (filled($dateCell)) {
                $currentDate = $this->extractDateFromDayCell((string) $dateCell) ?? $currentDate;
            }

            if (filled($timeCell)) {
                $currentTimeRange = (string) $timeCell;
            }

            if (! $this->rowRepresentsARealDuty($courseCodes, $invigilatorName)) {
                continue;
            }

            [$startTime, $endTime] = $this->splitTimeRange($currentTimeRange);
            [$studentCount, $studentCountNote] = $this->parseStudentCount($studentCountRaw);

            $duties->push([
                'date' => $currentDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'course_codes' => trim((string) $courseCodes),
                'room' => trim((string) $room),
                'lecturer_name' => $this->normalizeName($lecturerName) ?: null,
                'invigilator_name' => $this->normalizeName($invigilatorName),
                'student_count' => $studentCount,
                'student_count_note' => $studentCountNote,
            ]);
        }

        return $duties;
    }

    private function loadWorksheet(Filesystem $disk): Worksheet
    {
        // PhpSpreadsheet needs a real local file path to read from. In production
        // the file actually lives in Laravel Object Storage (the app server's own
        // disk is ephemeral and gets wiped on every deploy), so we pull it down to
        // a throwaway temp file first, then discard that temp file immediately.
        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'master_schedule_');
        file_put_contents($temporaryFilePath, $disk->get(self::MASTER_FILE_PATH));

        try {
            $spreadsheet = IOFactory::load($temporaryFilePath);

            return $spreadsheet->sheetNameExists(self::SHEET_NAME)
                ? $spreadsheet->getSheetByName(self::SHEET_NAME)
                : $spreadsheet->getSheet(0);
        } finally {
            @unlink($temporaryFilePath);
        }
    }

    private function rowRepresentsARealDuty(mixed $courseCodes, mixed $invigilatorName): bool
    {
        $courseCodes = trim((string) $courseCodes);
        $invigilatorName = trim((string) $invigilatorName);

        if ($courseCodes === '' || $invigilatorName === '') {
            return false;
        }

        return strtoupper($courseCodes) !== 'NO EXAM';
    }

    private function extractDateFromDayCell(string $dayCell): ?string
    {
        if (! preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $dayCell, $matches)) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', $matches[1])->format('Y-m-d');
    }

    private function splitTimeRange(?string $timeRange): array
    {
        if (blank($timeRange) || ! str_contains($timeRange, '-')) {
            return [null, null];
        }

        [$startRaw, $endRaw] = explode('-', $timeRange, 2);

        return [
            $this->normalizeTime($startRaw),
            $this->normalizeTime($endRaw),
        ];
    }

    private function normalizeTime(string $rawTime): ?string
    {
        $rawTime = trim(str_replace('.', ':', $rawTime));

        try {
            return Carbon::parse($rawTime)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeName(mixed $rawName): string
    {
        $name = trim((string) $rawName);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\b(Dr|Mr|Mrs|Ms|Prof|Rev)\.?\s*/', '$1. ', $name);

        return $name;
    }

    private function parseStudentCount(mixed $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        if (! str_contains($raw, '=')) {
            return [(int) $raw, null];
        }

        $parts = explode('=', $raw);
        $total = (int) trim(end($parts));

        return [$total, $raw];
    }
}