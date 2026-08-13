<?php

use App\Models\InvigilationDuty;
use App\Models\Invigilator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns only the matched invigilator\'s duties, not a same-surname invigilator\'s duties', function () {
    $anthony = Invigilator::create(['name' => 'Mr. Anthony Wambua']);
    $alex = Invigilator::create(['name' => 'Mr. Alex Wambua']);

    InvigilationDuty::create([
        'invigilator_id' => $anthony->id,
        'date' => '2026-08-26',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'course_codes' => 'ACS314B/SIT352B',
        'room' => '1CT114',
        'lecturer_name' => 'Mr. Anthony Wambua',
        'student_count' => 45,
    ]);

    InvigilationDuty::create([
        'invigilator_id' => $alex->id,
        'date' => '2026-08-25',
        'start_time' => '12:30',
        'end_time' => '14:30',
        'course_codes' => 'DICT216A',
        'room' => 'BCC8',
        'lecturer_name' => 'Mr. Alex Wambua',
        'student_count' => 30,
    ]);

    $response = $this->postJson('/search', ['name' => 'Anthony Wambua']);

    $response->assertOk()
        ->assertJson(['invigilator_name' => 'Mr. Anthony Wambua'])
        ->assertJsonCount(1, 'schedule')
        ->assertJsonPath('schedule.0.course_codes', 'ACS314B/SIT352B');
});

it('returns a 404 with a clear message when no invigilator matches the search', function () {
    $response = $this->postJson('/search', ['name' => 'Nobody Here']);

    $response->assertNotFound()
        ->assertJson(['message' => 'No invigilator found with that name.']);
});