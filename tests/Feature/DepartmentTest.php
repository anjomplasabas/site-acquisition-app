<?php

use App\Models\Department;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// --- Authentication ---

it('redirects unauthenticated users from departments index', function () {
    $this->get('/departments')->assertRedirect('/');
});

it('redirects unauthenticated users from department create page', function () {
    $this->get('/departments/create')->assertRedirect('/');
});

it('redirects unauthenticated users from department edit page', function () {
    $department = Department::factory()->create();

    $this->get("/departments/{$department->id}/edit")->assertRedirect('/');
});

it('forbids non-super-admin from accessing departments', function () {
    $this->actingAs(User::factory()->create())
        ->get('/departments')
        ->assertForbidden();
});

// --- Index ---

it('shows the departments list', function () {
    $department = Department::factory()->create();
    Department::factory()->count(2)->create();

    $this->actingAs(User::factory()->superAdmin()->create(['department_id' => $department->id]))
        ->get('/departments')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Departments/Index')
            ->has('departments.data', 3)
            ->has('filters')
        );
});

it('filters departments by search', function () {
    Department::factory()->create(['name' => 'Engineering']);
    Department::factory()->create(['name' => 'Marketing']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/departments?search=Engineering')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Departments/Index')
            ->has('departments.data', 1)
            ->where('departments.data.0.name', 'Engineering')
        );
});

it('filters departments by status', function () {
    Department::factory()->create(['status' => 'ACTIVE']);
    Department::factory()->inactive()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/departments?status=INACTIVE')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Departments/Index')
            ->has('departments.data', 1)
            ->where('departments.data.0.status', 'INACTIVE')
        );
});

// --- Create ---

it('shows the create department page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/departments/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Departments/Create'));
});

// --- Store ---

it('creates a department with valid data', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/departments', ['name' => 'Finance', 'status' => 'ACTIVE'])
        ->assertRedirect('/departments');

    expect(Department::where('name', 'Finance')->exists())->toBeTrue();
});

it('fails to create a department with invalid data', function (array $data) {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/departments', $data)
        ->assertSessionHasErrors();
})->with([
    'missing name' => [['name' => '', 'status' => 'ACTIVE']],
    'invalid status' => [['name' => 'Finance', 'status' => 'UNKNOWN']],
    'missing status' => [['name' => 'Finance']],
]);

it('fails to create a department with a duplicate name', function () {
    Department::factory()->create(['name' => 'Engineering']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/departments', ['name' => 'Engineering', 'status' => 'ACTIVE'])
        ->assertSessionHasErrors('name');
});

// --- Edit ---

it('shows the edit department page', function () {
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get("/departments/{$department->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Departments/Edit')
            ->has('department')
            ->where('department.id', $department->id)
        );
});

it('returns 404 for a non-existent department on edit', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/departments/9999/edit')
        ->assertNotFound();
});

// --- Update ---

it('updates a department with valid data', function () {
    $department = Department::factory()->create(['name' => 'Old Name']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put("/departments/{$department->id}", ['name' => 'New Name', 'status' => 'INACTIVE'])
        ->assertRedirect('/departments');

    expect($department->fresh()->name)->toBe('New Name')
        ->and($department->fresh()->status)->toBe('INACTIVE');
});

it('fails to update a department with a duplicate name', function () {
    Department::factory()->create(['name' => 'Engineering']);
    $department = Department::factory()->create(['name' => 'Marketing']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put("/departments/{$department->id}", ['name' => 'Engineering', 'status' => 'ACTIVE'])
        ->assertSessionHasErrors('name');
});

it('allows updating a department with its own name', function () {
    $department = Department::factory()->create(['name' => 'Engineering']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put("/departments/{$department->id}", ['name' => 'Engineering', 'status' => 'INACTIVE'])
        ->assertRedirect('/departments');
});

it('returns 404 when updating a non-existent department', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->put('/departments/9999', ['name' => 'Finance', 'status' => 'ACTIVE'])
        ->assertNotFound();
});

// --- Destroy ---

it('deletes a department', function () {
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete("/departments/{$department->id}")
        ->assertRedirect();

    expect(Department::find($department->id))->toBeNull();
});

it('returns 404 when deleting a non-existent department', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete('/departments/9999')
        ->assertNotFound();
});
