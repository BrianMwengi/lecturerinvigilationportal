<?php

namespace App\Http\Controllers;

use App\Models\Invigilator;
use App\Services\ExcelVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        // Inject the ExcelVerificationService into the controller
        private readonly ExcelVerificationService $excelVerificationService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Split the search term into individual words and convert them to lowercase for case-insensitive searching
        $searchTerms = preg_split('/\s+/', mb_strtolower(trim($validated['name'])));

        $query = Invigilator::query(); // Start a new query on the Invigilator model

        // Add a where clause for each search term to find invigilators whose names contain all the terms 
        // (terms are e.g "John Doe" would be split into "john" and "doe")
        foreach ($searchTerms as $term) {
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]); // Use whereRaw for case-insensitive search
        }

        $invigilator = $query->first(); // Get the first invigilator that matches the search criteria

        if (! $invigilator) {
            return response()->json([
                'message' => 'No invigilator found with that name.',
            ], 404);
        }

        // Retrieve the invigilator's duties, ordered by date and start time
        $duties = $invigilator->invigilationDuties()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Verify the invigilator's schedule against the Excel data using the ExcelVerificationService
        $isVerifiedAgainstExcel = $this->excelVerificationService
            ->verifyInvigilatorSchedule($invigilator, $duties);

        // Return the invigilator's name, verification status, and schedule in a structured JSON response
        return response()->json([
            'invigilator_name' => $invigilator->name,
            'verified' => $isVerifiedAgainstExcel,
            'schedule' => $duties->map(fn ($duty) => [ // Map each duty to a structured array for the response
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