<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\SitePlanningAssignment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Capture the acting user before the request runs, so logout actions
        // are still attributed even after the session is cleared.
        $request->attributes->set('_audit_user_id', auth()->id());
        $request->attributes->set('_audit_actor_name', auth()->user()?->full_name);
        $request->attributes->set('_audit_actor_email', auth()->user()?->email);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return;
        }

        if ($response->getStatusCode() >= 500) {
            return;
        }

        if ($response instanceof RedirectResponse && $this->carriesValidationErrors($request)) {
            return;
        }

        // Controllers that detect a no-op (e.g. a profile "update" with no actual field
        // changes) set this to opt out — otherwise this middleware logs every successful
        // non-GET request purely from the route/controller name, with no visibility into
        // whether the controller's business logic actually did anything.
        if (request()->attributes->get('_audit_skip')) {
            return;
        }

        // For login, auth()->id() resolves after authentication completes.
        // For logout, the _audit_* attributes captured the user before session was cleared.
        $userId = $request->attributes->get('_audit_user_id') ?? auth()->id();

        if (! $userId) {
            return;
        }

        $actorName = $request->attributes->get('_audit_actor_name') ?? auth()->user()?->full_name;
        $actorEmail = $request->attributes->get('_audit_actor_email') ?? auth()->user()?->email;

        $routeName = $request->route()?->getName();
        $action = $routeName
            ? str_replace('.', '_', $routeName)
            : $this->deriveActionFromController($request);

        if (! $action) {
            return;
        }

        [$subject, $subjectType, $subjectId] = $this->resolveSubject($request);
        $metadata = $this->buildMetadata($request);

        AuditLog::create([
            'user_id' => $userId,
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $this->buildDescription($routeName ?? $action, $request, $subject),
            'ip_address' => $request->ip(),
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    private function carriesValidationErrors(Request $request): bool
    {
        $errors = $request->session()->get('errors');

        if ($errors instanceof ViewErrorBag) {
            return $errors->any();
        }

        // Session serialization can round-trip the ViewErrorBag into a plain
        // array by the time terminate() runs; check that shape too.
        if (is_array($errors)) {
            foreach ($errors as $bag) {
                $messages = is_array($bag) ? ($bag['messages'] ?? $bag) : [];
                if (! empty($messages)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveSubject(Request $request): array
    {
        // Controllers stash a freshly created model on the currently-bound request
        // (which may be a FormRequest instance distinct from the $request this
        // middleware originally received), so resolve via the container instead
        // of the injected $request to see attributes set after FormRequest swap-in.
        $subject = request()->attributes->get('_audit_subject');
        if ($subject instanceof Model) {
            return [$subject, $subject::class, $subject->getKey()];
        }

        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Model) {
                return [$value, $value::class, $value->getKey()];
            }
        }

        return [null, null, null];
    }

    private function buildMetadata(Request $request): array
    {
        $metadata = [];

        $subject = request()->attributes->get('_audit_subject');
        if ($subject instanceof Pipeline) {
            $metadata['pipeline_ref'] = $subject->pipeline_id;
        }

        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Pipeline) {
                $metadata['pipeline_ref'] = $value->pipeline_id;
            }
        }

        $excluded = ['_token', '_method', 'password', 'password_confirmation', 'current_password'];
        $fields = $request->except($excluded);

        // Replace UploadedFile instances with just the original filename
        array_walk_recursive($fields, function (mixed &$value): void {
            if ($value instanceof UploadedFile) {
                $value = $value->getClientOriginalName();
            }
        });

        // pipelines.import.confirm submits the full mapped CSV payload (every column of
        // every row) as `rows` — logging it verbatim renders as an unreadable blob (or
        // "[object Object]" once the frontend tries to stringify it). Reduce it to a
        // compact, human-readable summary per row instead.
        if (is_array($fields['rows'] ?? null)) {
            $fields['rows'] = array_map(
                fn (mixed $row): array => [
                    'row_number' => $row['row_number'] ?? null,
                    'site_name' => $row['data']['site_name'] ?? $row['data']['pipeline_name'] ?? null,
                    'site_action' => $row['site_action'] ?? null,
                    'pipeline_action' => $row['pipeline_action'] ?? null,
                ],
                $fields['rows']
            );
        }

        if (! empty($fields)) {
            $metadata['fields'] = $fields;
        }

        return $metadata;
    }

    private function buildDescription(string $routeOrAction, Request $request, ?Model $subject): string
    {
        $pipeline = match (true) {
            $subject instanceof Pipeline => $subject,
            $subject instanceof NetworkSiteAssignment => $subject->pipeline,
            $subject instanceof SitePlanningAssignment => $subject->pipeline,
            default => null,
        };
        $ref = $pipeline ? " '{$pipeline->name}' ({$pipeline->pipeline_id})" : '';

        return match ($routeOrAction) {
            // Auth
            'logout' => 'Logged out',

            // Pipelines
            'pipelines.store' => "Created a new pipeline{$ref}",
            'pipelines.update' => "Updated pipeline{$ref}",
            'pipelines.assign-saq' => "Assigned SAQ specialist to{$ref}",
            'pipelines.start-acquisition' => "Started acquisition for{$ref}",
            'pipelines.move-to-site-survey-pending' => "Moved{$ref} to Site Survey Pending",
            'pipelines.survey-findings' => "Saved survey findings for{$ref}",
            'pipelines.save-prospecting' => "Saved prospecting details for{$ref}",
            'pipelines.save-commercials-progress' => "Saved commercials progress for{$ref}",
            'pipelines.set-commercials' => "Submitted commercials for{$ref}",
            'pipelines.revise-commercials' => "Revised commercials for{$ref}",
            'pipelines.reopen-survey' => "Reopened survey for{$ref}",
            'pipelines.approvals.decide' => "Decided on approval for{$ref}",
            'pipelines.mark-active' => "Marked{$ref} as Ready for Service",
            'pipelines.mark-inactive' => "Marked{$ref} as Inactive",
            'pipelines.mark-completed' => "Marked{$ref} as Completed",
            'pipelines.survey-documents.delete' => "Deleted survey document from{$ref}",
            'pipelines.commercial-documents.delete' => "Deleted commercial document from{$ref}",
            'pipelines.add-remark' => "Added remark to{$ref}",
            'pipelines.import.confirm' => 'Imported pipelines from CSV',

            // Site Planning
            'site-planning.schedule' => "Scheduled site survey for{$ref}",
            'site-planning.inspection.save' => "Saved survey findings for{$ref}",
            'site-planning.complete' => "Completed site survey for{$ref}",
            'site-planning.revert' => "Reverted{$ref} to Prospecting",
            'site-planning.documents.store' => "Uploaded document for{$ref}",
            'site-planning.documents.delete' => "Deleted document from{$ref}",
            'site-planning.design-plan.store' => "Uploaded design plan for{$ref}",
            'site-planning.design-plan.delete' => "Deleted design plan from{$ref}",

            // Network
            'network.feasibilities.store' => "Added network feasibility to{$ref}",
            'network.feasibilities.update' => "Updated network feasibility for{$ref}",
            'network.feasibilities.delete' => "Removed network feasibility from{$ref}",
            'network.feasibilities.documents.store' => "Uploaded network feasibility document for{$ref}",
            'network.feasibilities.documents.delete' => "Deleted network feasibility document from{$ref}",
            'network.complete-feasibility' => "Completed network feasibility for{$ref}",

            // Roles
            'roles.store' => 'Created a new role',
            'roles.update' => 'Updated role',
            'roles.destroy' => 'Deleted role',

            // Settings
            'settings.approvals.update' => 'Updated approval step configuration',
            'settings.approvals.remove' => 'Removed approval step configuration',

            // Site management
            'site-profiles.store' => 'Created a new site profile',
            'site-profiles.update' => 'Updated site profile',
            'site-contacts.store' => 'Created a new site contact',
            'site-contacts.update' => 'Updated site contact',
            'site-contacts.destroy' => 'Deleted site contact',

            default => ucwords(str_replace('_', ' ', $routeOrAction)),
        };
    }

    private function deriveActionFromController(Request $request): string
    {
        $actionName = $request->route()?->getActionName() ?? '';
        $parts = explode('@', $actionName);

        if (count($parts) !== 2) {
            return '';
        }

        $controller = strtolower(str_replace('Controller', '', class_basename($parts[0])));
        $method = strtolower($parts[1]);

        return "{$controller}_{$method}";
    }
}
