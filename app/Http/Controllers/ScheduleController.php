<?php

namespace App\Http\Controllers;

use App\Models\Invigilator;
use App\Services\ExcelVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ExcelVerificationService $excelVerificationService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $searchTerms = preg_split('/\s+/', mb_strtolower(trim($validated['name'])));

        $query = Invigilator::query();

        foreach ($searchTerms as $term) {
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
        }

        $invigilator = $query->first();

        if (! $invigilator) {
            return response()->json([
                'message' => 'No invigilator found with that name.',
            ], 404);
        }

        $duties = $invigilator->invigilationDuties()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $isVerifiedAgainstExcel = $this->excelVerificationService
            ->verifyInvigilatorSchedule($invigilator, $duties);

        return response()->json([
            'invigilator_name' => $invigilator->name,
            'verified' => $isVerifiedAgainstExcel,
            'schedule' => $duties->map(fn ($duty) => [
                'date' => $duty->date->format('Y-m-d'),
                'start_time' => $duty->start_time->format('H:i'),
                'end_time' => $duty->end_time->format('H:i'),
                'course_codes' => $duty->course_codes,
                'room' => $duty->room,
                'lecturer_name' => $duty->lecturer_name,
                'student_count' => $duty->student_count,
            ]),
        ]);
    }
}