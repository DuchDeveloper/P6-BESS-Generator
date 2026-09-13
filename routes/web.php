<?php

use App\Models\BasisOfSchedule;
use App\Models\Project;
use App\Services\Bess\SummaryGanttLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Latest Basis of Schedule for a project (rendered inline, printable).
Route::get('/projects/{project}/basis-of-schedule', function (Project $project) {
    $bos = $project->basisOfSchedules()->latest('version')->firstOrFail();

    return view('bess.basis-of-schedule', compact('bos', 'project'));
})->name('bess.bos.latest');

// Specific version — for revisiting older drafts.
Route::get('/projects/{project}/basis-of-schedule/{bos}', function (Project $project, BasisOfSchedule $bos) {
    abort_unless($bos->project_id === $project->id, 404);

    return view('bess.basis-of-schedule', compact('bos', 'project'));
})->name('bess.bos.show');

// PDF download of the latest BoS.
Route::get('/projects/{project}/basis-of-schedule.pdf', function (Project $project) {
    $bos = $project->basisOfSchedules()->latest('version')->firstOrFail();
    $filename = sprintf('BoS-%s-v%d.pdf', $project->code ?? $project->id, $bos->version);

    return Pdf::loadView('bess.basis-of-schedule', compact('bos', 'project'))
        ->setPaper('a4')
        ->download($filename);
})->name('bess.bos.pdf');

// Microsoft Word (.docx) download of the latest BoS.
Route::get('/projects/{project}/basis-of-schedule.docx', function (Project $project) {
    $bos = $project->basisOfSchedules()->latest('version')->firstOrFail();

    return app(\App\Services\Bess\BasisOfScheduleWordService::class)
        ->download($project, $bos);
})->name('bess.bos.docx');

// Summary Gantt — HTML view.
Route::get('/projects/{project}/summary-gantt', function (Project $project) {
    $layout = app(SummaryGanttLayout::class)->build($project);

    return view('bess.summary-gantt', compact('project', 'layout'));
})->name('bess.gantt.show');

// Summary Gantt — PowerPoint download.
Route::get('/projects/{project}/summary-gantt.pptx', function (Project $project) {
    return app(\App\Services\Bess\SummaryGanttPowerPointService::class)
        ->download($project);
})->name('bess.gantt.pptx');
