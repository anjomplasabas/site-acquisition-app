<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PipelineApprovalController;
use App\Http\Controllers\PipelineCommercialController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PipelineImportController;
use App\Http\Controllers\PipelineRemarkController;
use App\Http\Controllers\PipelineSurveyController;
use App\Http\Controllers\PipelineWorkflowController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStageController;
use App\Http\Controllers\ProjectStageIspController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteContactController;
use App\Http\Controllers\SiteManagementController;
use App\Http\Controllers\SitePlanningController;
use App\Http\Controllers\SitePlanningDesignController;
use App\Http\Controllers\SitePlanningSurveyController;
use App\Http\Controllers\SiteProfileController;
use App\Http\Controllers\StageTaskController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\HandleForcePasswordChange;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::post('/', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email')->middleware('throttle:3,5');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update')->middleware('throttle:5,1');
});

Route::middleware(['auth', HandleForcePasswordChange::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->middleware('module:dashboard')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions');

    Route::middleware('super_admin')->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments');
        Route::get('/departments/create', [DepartmentController::class, 'create']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    });

    Route::middleware(['module:pipelines', 'super_admin'])->group(function () {
        Route::get('/pipelines/import', [PipelineImportController::class, 'create'])->name('pipelines.import');
        Route::get('/pipelines/import/template', [PipelineImportController::class, 'template'])->name('pipelines.import.template');
        Route::post('/pipelines/import/upload', [PipelineImportController::class, 'upload'])->name('pipelines.import.upload');
        Route::post('/pipelines/import/preview', [PipelineImportController::class, 'preview'])->name('pipelines.import.preview');
        Route::post('/pipelines/import/confirm', [PipelineImportController::class, 'confirm'])->name('pipelines.import.confirm');
    });

    Route::middleware('module:pipelines')->group(function () {
        Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines');
        Route::get('/pipelines/create', [PipelineController::class, 'create'])->name('pipelines.create');
        Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
        Route::get('/pipelines/{pipeline}', [PipelineController::class, 'show'])->name('pipelines.show');
        Route::patch('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
        Route::patch('/pipelines/{pipeline}/assign-saq', [PipelineWorkflowController::class, 'assignSaq'])->name('pipelines.assign-saq');
        Route::post('/pipelines/{pipeline}/start-acquisition', [PipelineWorkflowController::class, 'startAcquisition'])->name('pipelines.start-acquisition');
        Route::post('/pipelines/{pipeline}/move-to-site-survey-pending', [PipelineWorkflowController::class, 'moveToSiteSurveyPending'])->name('pipelines.move-to-site-survey-pending');
        Route::post('/pipelines/{pipeline}/mark-active', [PipelineWorkflowController::class, 'markActive'])->name('pipelines.mark-active');
        Route::post('/pipelines/{pipeline}/mark-inactive', [PipelineWorkflowController::class, 'markInactive'])->name('pipelines.mark-inactive');
        Route::post('/pipelines/{pipeline}/mark-completed', [PipelineWorkflowController::class, 'markCompleted'])->name('pipelines.mark-completed');
        Route::post('/pipelines/{pipeline}/reopen-survey', [PipelineSurveyController::class, 'reopenSurvey'])->name('pipelines.reopen-survey');
        Route::patch('/pipelines/{pipeline}/set-commercials', [PipelineCommercialController::class, 'reviseCommercials'])->name('pipelines.revise-commercials');
        Route::post('/pipelines/{pipeline}/survey-findings', [PipelineSurveyController::class, 'saveSurveyFindings'])->name('pipelines.survey-findings');
        Route::post('/pipelines/{pipeline}/save-prospecting', [PipelineSurveyController::class, 'saveProspecting'])->name('pipelines.save-prospecting');
        Route::delete('/pipelines/{pipeline}/survey-documents/{document}', [PipelineSurveyController::class, 'deleteSurveyDocument'])->name('pipelines.survey-documents.delete');
        Route::post('/pipelines/{pipeline}/survey-documents/{document}/replace', [PipelineSurveyController::class, 'replaceSurveyDocument'])->name('pipelines.survey-documents.replace');
        Route::post('/pipelines/{pipeline}/save-commercials-progress', [PipelineCommercialController::class, 'saveCommercialsProgress'])->name('pipelines.save-commercials-progress');
        Route::delete('/pipelines/{pipeline}/commercial-documents/{document}', [PipelineCommercialController::class, 'deleteCommercialDocument'])->name('pipelines.commercial-documents.delete');
        Route::post('/pipelines/{pipeline}/commercial-documents/{document}/replace', [PipelineCommercialController::class, 'replaceCommercialDocument'])->name('pipelines.commercial-documents.replace');
        Route::post('/pipelines/{pipeline}/set-commercials', [PipelineCommercialController::class, 'setCommercials'])->name('pipelines.set-commercials');
        Route::post('/pipelines/{pipeline}/approvals/{pipelineApproval}/decide', [PipelineApprovalController::class, 'decideApproval'])->name('pipelines.approvals.decide');
        Route::post('/pipelines/{pipeline}/add-remark', [PipelineRemarkController::class, 'addRemark'])->name('pipelines.add-remark');
    });

    Route::middleware('module:site-planning')->prefix('site-planning')->name('site-planning.')->group(function () {
        Route::get('/', [SitePlanningController::class, 'index'])->name('index');
        Route::get('/reports/site-survey', [SitePlanningController::class, 'siteSurveyReport'])->name('reports.site-survey');
        Route::get('/{sitePlanningAssignment}', [SitePlanningController::class, 'show'])->name('show');
        Route::patch('/{sitePlanningAssignment}/schedule', [SitePlanningSurveyController::class, 'scheduleSurvey'])->name('schedule');
        Route::post('/{sitePlanningAssignment}/design-plan-type', [SitePlanningDesignController::class, 'updateDesignPlanType'])->name('design-plan-type.update');
        Route::post('/{sitePlanningAssignment}/inspection', [SitePlanningSurveyController::class, 'saveInspection'])->name('inspection.save');
        Route::post('/{sitePlanningAssignment}/inspection/submit', [SitePlanningSurveyController::class, 'submitInspection'])->name('inspection.submit');
        Route::post('/{sitePlanningAssignment}/towers', [SitePlanningSurveyController::class, 'declareTowers'])->name('towers.declare');
        Route::post('/{sitePlanningAssignment}/revert', [SitePlanningSurveyController::class, 'revertToProspecting'])->name('revert');
        Route::patch('/{sitePlanningAssignment}/survey-tasks/{siteSurveyTask}', [SitePlanningSurveyController::class, 'updateSurveyTaskNotes'])->name('survey-tasks.update');
        Route::post('/{sitePlanningAssignment}/survey-tasks/{siteSurveyTask}/documents', [SitePlanningSurveyController::class, 'uploadSurveyTaskDocument'])->name('survey-tasks.documents.store');
        Route::delete('/{sitePlanningAssignment}/survey-tasks/{siteSurveyTask}/documents/{document}', [SitePlanningSurveyController::class, 'deleteSurveyTaskDocument'])->name('survey-tasks.documents.delete');
        Route::post('/{sitePlanningAssignment}/survey-tasks/{siteSurveyTask}/documents/{document}/replace', [SitePlanningSurveyController::class, 'replaceSurveyTaskDocument'])->name('survey-tasks.documents.replace');
        Route::post('/{sitePlanningAssignment}/documents', [SitePlanningSurveyController::class, 'uploadDocument'])->name('documents.store');
        Route::delete('/{sitePlanningAssignment}/documents/{document}', [SitePlanningSurveyController::class, 'deleteDocument'])->name('documents.delete');
        Route::post('/{sitePlanningAssignment}/documents/{document}/replace', [SitePlanningSurveyController::class, 'replaceDocument'])->name('documents.replace');
        Route::patch('/{sitePlanningAssignment}/design-plan/assign', [SitePlanningDesignController::class, 'assignDesignPlan'])->name('design-plan.assign');
        Route::patch('/{sitePlanningAssignment}/design-plan/remarks', [SitePlanningDesignController::class, 'updateDesignPlanRemarks'])->name('design-plan.remarks.update');
        Route::post('/{sitePlanningAssignment}/design-plan/documents', [SitePlanningDesignController::class, 'uploadDesignPlanDocument'])->name('design-plan.documents.store');
        Route::delete('/{sitePlanningAssignment}/design-plan/documents/{document}', [SitePlanningDesignController::class, 'deleteDesignPlanDocument'])->name('design-plan.documents.delete');
        Route::post('/{sitePlanningAssignment}/design-plan/documents/{document}/replace', [SitePlanningDesignController::class, 'replaceDesignPlanDocument'])->name('design-plan.documents.replace');
        Route::patch('/{sitePlanningAssignment}/design-plan/tasks/complete-all', [SitePlanningDesignController::class, 'completeAllTasks'])->name('design-plan.tasks.complete-all');
        Route::patch('/{sitePlanningAssignment}/design-plan/tasks/{sitePlanningTask}', [SitePlanningDesignController::class, 'updateTaskStatus'])->name('design-plan.tasks.update');
        Route::post('/{sitePlanningAssignment}/design-plan/tasks/{sitePlanningTask}/documents', [SitePlanningDesignController::class, 'uploadTaskDocument'])->name('design-plan.tasks.documents.store');
        Route::delete('/{sitePlanningAssignment}/design-plan/tasks/{sitePlanningTask}/documents/{document}', [SitePlanningDesignController::class, 'deleteTaskDocument'])->name('design-plan.tasks.documents.delete');
        Route::post('/{sitePlanningAssignment}/design-plan/tasks/{sitePlanningTask}/documents/{document}/replace', [SitePlanningDesignController::class, 'replaceTaskDocument'])->name('design-plan.tasks.documents.replace');
        Route::patch('/{sitePlanningAssignment}/design-plan/complete', [SitePlanningDesignController::class, 'completeDesignPlan'])->name('design-plan.complete');
        Route::patch('/{sitePlanningAssignment}/project-type', [SitePlanningDesignController::class, 'updateProjectType'])->name('project-type.update');
        Route::patch('/{sitePlanningAssignment}/design-plan/approve', [SitePlanningDesignController::class, 'approveDesignPlan'])->name('design-plan.approve');
        Route::patch('/{sitePlanningAssignment}/design-plan/request-revision', [SitePlanningDesignController::class, 'requestDesignPlanRevision'])->name('design-plan.request-revision');
    });

    Route::middleware('module:network')->prefix('network')->name('network.')->group(function () {
        Route::get('/', [NetworkController::class, 'index'])->name('index');
        Route::get('/{networkSiteAssignment}', [NetworkController::class, 'show'])->name('show');
        Route::post('/{networkSiteAssignment}/feasibilities', [NetworkController::class, 'storeFeasibility'])->name('feasibilities.store');
        Route::patch('/{networkSiteAssignment}/feasibilities/{networkFeasibility}', [NetworkController::class, 'updateFeasibility'])->name('feasibilities.update');
        Route::delete('/{networkSiteAssignment}/feasibilities/{networkFeasibility}', [NetworkController::class, 'destroyFeasibility'])->name('feasibilities.delete');
        Route::post('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/documents', [NetworkController::class, 'uploadFeasibilityDocument'])->name('feasibilities.documents.store');
        Route::delete('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/documents/{document}', [NetworkController::class, 'deleteFeasibilityDocument'])->name('feasibilities.documents.delete');
        Route::post('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/documents/{document}/replace', [NetworkController::class, 'replaceFeasibilityDocument'])->name('feasibilities.documents.replace');
        Route::patch('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/tasks/{task}', [NetworkController::class, 'updateFeasibilityTaskStatus'])->name('feasibilities.tasks.update');
        Route::post('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/tasks/{task}/documents', [NetworkController::class, 'uploadFeasibilityTaskDocument'])->name('feasibilities.tasks.documents.store');
        Route::delete('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/tasks/{task}/documents/{document}', [NetworkController::class, 'deleteFeasibilityTaskDocument'])->name('feasibilities.tasks.documents.delete');
        Route::post('/{networkSiteAssignment}/feasibilities/{networkFeasibility}/tasks/{task}/documents/{document}/replace', [NetworkController::class, 'replaceFeasibilityTaskDocument'])->name('feasibilities.tasks.documents.replace');
        Route::post('/{networkSiteAssignment}/complete-feasibility', [NetworkController::class, 'completeFeasibility'])->name('complete-feasibility');
    });

    Route::middleware('module:projects')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

        Route::patch('/projects/{project}/stages/{stage}', [ProjectStageController::class, 'update'])->name('projects.stages.update');
        Route::post('/projects/{project}/stages/{stage}/complete', [ProjectStageController::class, 'complete'])->name('projects.stages.complete');
        Route::post('/projects/{project}/stages/{stage}/reopen', [ProjectStageController::class, 'reopen'])->name('projects.stages.reopen');
        Route::post('/projects/{project}/stages/{stage}/documents', [ProjectStageController::class, 'uploadDocument'])->name('projects.stages.documents.store');
        Route::delete('/projects/{project}/stages/{stage}/documents/{document}', [ProjectStageController::class, 'deleteDocument'])->name('projects.stages.documents.delete');
        Route::post('/projects/{project}/stages/{stage}/documents/{document}/replace', [ProjectStageController::class, 'replaceDocument'])->name('projects.stages.documents.replace');
        Route::patch('/projects/{project}/stages/{stage}/documents/{document}', [ProjectStageController::class, 'updateDocumentStatus'])->name('projects.stages.documents.update-status');

        Route::post('/projects/{project}/stages/{stage}/isps', [ProjectStageIspController::class, 'store'])->name('projects.stages.isps.store');
        Route::patch('/projects/{project}/stages/{stage}/isps/{isp}', [ProjectStageIspController::class, 'update'])->name('projects.stages.isps.update');
        Route::delete('/projects/{project}/stages/{stage}/isps/{isp}', [ProjectStageIspController::class, 'destroy'])->name('projects.stages.isps.delete');

        Route::patch('/projects/{project}/stages/{stage}/stage-tasks/complete-all', [StageTaskController::class, 'completeAll'])->name('projects.stages.stage-tasks.complete-all');
        Route::patch('/projects/{project}/stages/{stage}/stage-tasks/{stageTask}', [StageTaskController::class, 'updateStatus'])->name('projects.stages.stage-tasks.update');
        Route::post('/projects/{project}/stages/{stage}/stage-tasks/{stageTask}/documents', [StageTaskController::class, 'uploadDocument'])->name('projects.stages.stage-tasks.documents.store');
        Route::delete('/projects/{project}/stages/{stage}/stage-tasks/{stageTask}/documents/{document}', [StageTaskController::class, 'deleteDocument'])->name('projects.stages.stage-tasks.documents.delete');
        Route::post('/projects/{project}/stages/{stage}/stage-tasks/{stageTask}/documents/{document}/replace', [StageTaskController::class, 'replaceDocument'])->name('projects.stages.stage-tasks.documents.replace');
    });

    Route::get('/site-management', [SiteManagementController::class, 'index'])->middleware('module:projects')->name('site-management');
    Route::get('/site-management/{siteProfile}', [SiteManagementController::class, 'show'])->middleware('module:projects')->name('site-management.show');
    Route::middleware('module:projects')->group(function () {
        Route::post('/site-profiles', [SiteProfileController::class, 'store'])->name('site-profiles.store');
        Route::patch('/site-profiles/{siteProfile}', [SiteProfileController::class, 'update'])->name('site-profiles.update');
        Route::post('/site-contacts', [SiteContactController::class, 'store'])->name('site-contacts.store');
        Route::patch('/site-contacts/{siteContact}', [SiteContactController::class, 'update'])->name('site-contacts.update');
        Route::delete('/site-contacts/{siteContact}', [SiteContactController::class, 'destroy'])->name('site-contacts.destroy');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('module:users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::get('/users/create', [UserController::class, 'create']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}/edit', [UserController::class, 'edit']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    Route::middleware('module:settings')->group(function () {
        Route::get('/settings/approvals', [SettingsController::class, 'approvals'])->name('settings.approvals');
        Route::post('/settings/approvals/{step}', [SettingsController::class, 'updateApprovalStep'])->name('settings.approvals.update')->whereNumber('step');
        Route::delete('/settings/approvals/{step}', [SettingsController::class, 'removeApprovalStep'])->name('settings.approvals.remove')->whereNumber('step');
        Route::patch('/settings/alarm-thresholds', [SettingsController::class, 'updateAlarmThresholds'])->name('settings.alarm-thresholds.update');
        Route::post('/settings/stage-document-requirements', [SettingsController::class, 'storeStageDocumentRequirement'])->name('settings.stage-document-requirements.store');
        Route::patch('/settings/stage-document-requirements/{stageDocumentRequirement}', [SettingsController::class, 'updateStageDocumentRequirement'])->name('settings.stage-document-requirements.update');
        Route::delete('/settings/stage-document-requirements/{stageDocumentRequirement}', [SettingsController::class, 'destroyStageDocumentRequirement'])->name('settings.stage-document-requirements.destroy');
    });

    Route::middleware('super_admin')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
    });

    Route::middleware('module:roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
