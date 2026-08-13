<?php

namespace App\Console\Commands;

use App\Models\InvigilationDuty;
use App\Models\Invigilator;
use App\Services\MasterScheduleExcelReader;
use Illuminate\Console\Command;

class ImportSchedule extends Command
{
    protected $signature = 'schedule:import';
    protected $description = 'Import invigilation duties from the master Excel file into the database';

    public function handle(MasterScheduleExcelReader $excelReader): int
    {
        $duties = $excelReader->readAllDuties();

        if ($duties->isEmpty()) {
            $this->error('No duties found. Check that storage/app/master_schedule.xlsx exists and has data.');

            return self::FAILURE;
        }

        $importedCount = 0;

        foreach ($duties as $duty) {
            if (! $duty['start_time'] || ! $duty['end_time']) {
                $this->warn("Skipped a row with an unparseable time for {$duty['invigilator_name']} on {$duty['date']}.");
                continue;
            }

            if ($duty['end_time'] < $duty['start_time']) {
                $this->warn("Note: end_time is before start_time for {$duty['course_codes']} on {$duty['date']} — check the source row.");
            }

            $invigilator = Invigilator::firstOrCreate(['name' => $duty['invigilator_name']]);

            InvigilationDuty::updateOrCreate(
                [
                    'invigilator_id' => $invigilator->id,
                    'date' => $duty['date'],
                    'start_time' => $duty['start_time'],
                    'course_codes' => $duty['course_codes'],
                ],
                [
                    'end_time' => $duty['end_time'],
                    'room' => $duty['room'],
                    'lecturer_name' => $duty['lecturer_name'],
                    'student_count' => $duty['student_count'],
                    'student_count_note' => $duty['student_count_note'],
                ]
            );

            $importedCount++;
        }

        $this->info("Imported {$importedCount} invigilation duty record(s).");

        return self::SUCCESS;
    }
}