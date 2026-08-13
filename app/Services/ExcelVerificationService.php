<?php

namespace App\Services;

use App\Models\Invigilator;
use App\Models\InvigilationDuty;
use Illuminate\Support\Collection;

class ExcelVerificationService
{
    public function __construct(
        private readonly MasterScheduleExcelReader $excelReader
    ) {}

    public function verifyInvigilatorSchedule(Invigilator $invigilator, Collection $databaseDuties): bool
    {
        $excelDutiesForInvigilator = $this->excelReader->readAllDuties()
            ->filter(fn (array $duty) => $this->namesMatch($duty['invigilator_name'], $invigilator->name));

        if ($excelDutiesForInvigilator->isEmpty()) {
            return false;
        }

        foreach ($databaseDuties as $databaseDuty) {
            $matchingExcelDuty = $this->findMatchingExcelDuty($excelDutiesForInvigilator, $databaseDuty);

            if (! $matchingExcelDuty || ! $this->dutiesMatch($matchingExcelDuty, $databaseDuty)) {
                return false;
            }
        }

        return true;
    }

    private function namesMatch(string $excelName, string $databaseName): bool
    {
        return mb_strtolower(trim($excelName)) === mb_strtolower(trim($databaseName));
    }

    private function findMatchingExcelDuty(Collection $excelDuties, InvigilationDuty $databaseDuty): ?array
    {
        return $excelDuties->first(
            fn (array $duty) => $duty['course_codes'] === $databaseDuty->course_codes
                && $duty['date'] === $databaseDuty->date->format('Y-m-d')
        );
    }

    private function dutiesMatch(array $excelDuty, InvigilationDuty $databaseDuty): bool
    {
        return $excelDuty['room'] === $databaseDuty->room
            && $excelDuty['start_time'] === $databaseDuty->start_time->format('H:i')
            && $excelDuty['end_time'] === $databaseDuty->end_time->format('H:i');
    }
}