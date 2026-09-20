<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollaborationRequestController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliverableController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectExpenseController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\ProjectMilestoneController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TaskAssigneeController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskDependencyController;
use App\Http\Controllers\Api\TaskProgressController;
use App\Http\Controllers\Api\TaskStatusController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::match(['put', 'post'], '/profile', [ProfileController::class, 'update']);

    Route::get('/meta', [MetaController::class, 'index']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Tableaux de bord & rapports
    Route::get('/dashboard', [DashboardController::class, 'overview']);
    Route::get('/dashboard/projects', [DashboardController::class, 'projects']);
    Route::get('/reports/series', [ReportController::class, 'series']);

    // Utilisateurs
    Route::apiResource('users', UserController::class);

    // Départements + membres
    Route::apiResource('departments', DepartmentController::class);
    Route::get('/departments/{department}/members', [DepartmentController::class, 'members']);
    Route::post('/departments/{department}/members', [DepartmentController::class, 'upsertMember']);
    Route::delete('/departments/{department}/members/{user}', [DepartmentController::class, 'removeMember']);

    // Postes rattachés à un département
    Route::get('/positions', [PositionController::class, 'all']);
    Route::post('/positions', [PositionController::class, 'store']);
    Route::get('/departments/{department}/positions', [PositionController::class, 'index']);
    Route::put('/positions/{position}', [PositionController::class, 'update']);
    Route::delete('/positions/{position}', [PositionController::class, 'destroy']);

    // Tâches
    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/{task}/progress', [TaskProgressController::class, 'store']);
    Route::post('/tasks/{task}/progress/{progressUpdate}/review', [TaskProgressController::class, 'review']);
    Route::post('/tasks/{task}/progress/{progressUpdate}/reject', [TaskProgressController::class, 'reject']);
    Route::post('/tasks/{task}/request-reopen', [TaskAssigneeController::class, 'requestReopen']);
    Route::post('/tasks/{task}/status', [TaskStatusController::class, 'update']);
    Route::post('/tasks/{task}/deliverables', [DeliverableController::class, 'store']);
    Route::delete('/tasks/{task}/deliverables/{deliverable}', [DeliverableController::class, 'destroy']);
    Route::post('/tasks/{task}/evaluation', [EvaluationController::class, 'store']);
    Route::post('/tasks/{task}/complete-my-part', [TaskAssigneeController::class, 'completeMyPart']);
    Route::post('/tasks/{task}/assignees/{user}/reopen', [TaskAssigneeController::class, 'reopen']);
    Route::post('/tasks/{task}/dependencies', [TaskDependencyController::class, 'store']);
    Route::delete('/tasks/{task}/dependencies/{dependsOnTask}', [TaskDependencyController::class, 'destroy']);

    // Projets (Module 1 — Gestion de projets)
    Route::apiResource('projects', ProjectController::class);
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'upsertMember']);
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'removeMember']);
    Route::get('/projects/{project}/milestones', [ProjectMilestoneController::class, 'index']);
    Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store']);
    Route::put('/milestones/{milestone}', [ProjectMilestoneController::class, 'update']);
    Route::delete('/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy']);
    Route::get('/projects/{project}/expenses', [ProjectExpenseController::class, 'index']);
    Route::post('/projects/{project}/expenses', [ProjectExpenseController::class, 'store']);
    Route::put('/expenses/{expense}', [ProjectExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}', [ProjectExpenseController::class, 'destroy']);
    Route::post('/projects/{project}/deliverables', [DeliverableController::class, 'storeForProject']);
    Route::delete('/projects/{project}/deliverables/{deliverable}', [DeliverableController::class, 'destroyForProject']);

    // Demandes de collaboration inter-départements
    Route::get('/collaboration-requests', [CollaborationRequestController::class, 'index']);
    Route::post('/collaboration-requests', [CollaborationRequestController::class, 'store']);
    Route::post('/collaboration-requests/{collaborationRequest}/respond', [CollaborationRequestController::class, 'respond']);
    Route::delete('/collaboration-requests/{collaborationRequest}', [CollaborationRequestController::class, 'cancel']);

    // Paramètres de la plateforme
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);
});
