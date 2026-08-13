<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MasterScheduleExcelReader
{
    private const MASTER_FILE_RELATIVE_PATH = 'app/master_schedule.xlsx';
    private const SHEET_NAME = 'Sheet2';
    private const FIRST_DATA_ROW = 9; // row 8 holds the column headers

    public function readAllDuties(): Collection
    {
        $filePath = storage_path(self::MASTER_FILE_RELATIVE_PATH);

        if (! file_exists($filePath)) {
            return collect();
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->sheetNameExists(self::SHEET_NAME)
            ? $spreadsheet->getSheetByName(self::SHEET_NAME)
            : $spreadsheet->getSheet(0);

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

            // Date and Time are merged cells in the source file - only the top row of each
            // block has a value, so we carry the last-seen value down to the rows below it.
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
                'lecturer_name' => trim((string) $lecturerName) ?: null,
                'invigilator_name' => trim((string) $invigilatorName),
                'student_count' => $studentCount,
                'student_count_note' => $studentCountNote,
            ]);
        }

        return $duties;
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
        // Source format: "MONDAY 17/08/2026" - we only need the "17/08/2026" part.
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
        // Source uses both "9:00AM" and "12.30 PM" styles - unify the separator first.
        $rawTime = trim(str_replace('.', ':', $rawTime));

        try {
            return Carbon::parse($rawTime)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
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

        // e.g. "34+1=35" -> total is 35, keep the original expression as a note.
        $parts = explode('=', $raw);
        $total = (int) trim(end($parts));

        return [$total, $raw];
    }
}