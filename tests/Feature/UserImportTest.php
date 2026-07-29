<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_users_and_receives_a_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = User::factory()->create(['role' => 'student', 'email' => 'existing@example.com']);

        $file = $this->makeSpreadsheetUpload([
            ['Name', 'Email', 'Role', 'Gender', 'Phone', 'Student ID', 'Class Name', 'Generation', 'Province', 'Educator ID'],
            ['New Student', 'new.student@example.com', 'student', 'male', '012345678', '2001', '10A', '2026', 'Phnom Penh', ''],
            ['Duplicate In File', 'new.student@example.com', 'student', '', '', '', '', '', '', ''],
            ['Already Exists', 'existing@example.com', 'student', '', '', '', '', '', '', ''],
            ['Bad Role', 'bad.role@example.com', 'not-a-role', '', '', '', '', '', '', ''],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users/import', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.skipped', 2)
            ->assertJsonPath('summary.failed', 1);

        $this->assertDatabaseHas('users', ['email' => 'new.student@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'bad.role@example.com']);
        // The pre-existing user should still be the only record with that email (no duplicate created).
        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
    }

    public function test_import_rejects_a_file_missing_required_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $file = $this->makeSpreadsheetUpload([
            ['Full Name', 'Role'],
            ['No Email Column', 'student'],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users/import', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_non_admin_cannot_import_users(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $file = $this->makeSpreadsheetUpload([
            ['Name', 'Email'],
            ['Someone', 'someone@example.com'],
        ]);

        Sanctum::actingAs($student);

        $this->postJson('/api/users/import', ['file' => $file])->assertForbidden();
    }

    public function test_admin_can_download_the_import_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/users/import/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    protected function makeSpreadsheetUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'import') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
