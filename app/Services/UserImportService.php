<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

class UserImportService
{
    private const COLUMN_MAP = [
        'name' => 'name',
        'full name' => 'name',
        'email' => 'email',
        'email address' => 'email',
        'role' => 'role',
        'gender' => 'gender',
        'phone' => 'phone',
        'phone number' => 'phone',
        'student id' => 'student_id',
        'class name' => 'class_name',
        'class' => 'class_name',
        'generation' => 'generation',
        'province' => 'province',
        'educator id' => 'educator_id',
    ];

    private const REQUIRED_FIELDS = ['name', 'email'];

    private const TEMPLATE_HEADERS = [
        'Name', 'Email', 'Role', 'Gender', 'Phone', 'Student ID', 'Class Name', 'Generation', 'Province', 'Educator ID',
    ];

    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if (empty($rows)) {
            throw new InvalidArgumentException('The uploaded file is empty.');
        }

        $header = array_shift($rows);
        $fieldsByColumn = $this->mapHeader($header);

        $missingRequired = array_diff(self::REQUIRED_FIELDS, array_values($fieldsByColumn));
        if (! empty($missingRequired)) {
            throw new InvalidArgumentException(
                'The uploaded file is missing required column(s): '
                . implode(', ', $missingRequired)
                . '. Please use the provided template.'
            );
        }

        $successful = [];
        $failed = [];
        $skipped = [];
        $seenEmails = [];
        $seenStudentIds = [];
        $processedRows = 0;

        $defaultPassword = config('auth.default_new_user_password', '12345678');

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // account for zero-based index + header row

            if ($this->isBlankRow($row)) {
                continue;
            }

            $processedRows++;
            $data = $this->extractRowData($row, $fieldsByColumn);
            $email = strtolower(trim((string) ($data['email'] ?? '')));

            if ($email === '') {
                $failed[] = [
                    'row' => $rowNumber,
                    'email' => null,
                    'errors' => ['Email is required.'],
                ];
                continue;
            }

            if (isset($seenEmails[$email])) {
                $skipped[] = [
                    'row' => $rowNumber,
                    'email' => $data['email'],
                    'reason' => 'Duplicate email within the uploaded file.',
                ];
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $seenEmails[$email] = true;
                $skipped[] = [
                    'row' => $rowNumber,
                    'email' => $data['email'],
                    'reason' => 'A user with this email already exists.',
                ];
                continue;
            }

            $effectiveRole = $data['role'] ?: 'student';
            $studentId = $data['student_id'] ?? null;

            if ($effectiveRole === 'student' && $studentId !== null) {
                $generation = $data['generation'] ?? null;
                $studentIdKey = $studentId . '|' . ($generation ?? '');

                if (isset($seenStudentIds[$studentIdKey])) {
                    $skipped[] = [
                        'row' => $rowNumber,
                        'email' => $data['email'],
                        'reason' => "Duplicate student ID \"{$studentId}\" within the uploaded file for this generation.",
                    ];
                    continue;
                }

                $duplicateExists = User::where('role', 'student')
                    ->where('student_id', $studentId)
                    ->where('generation', $generation)
                    ->exists();

                if ($duplicateExists) {
                    $seenStudentIds[$studentIdKey] = true;
                    $skipped[] = [
                        'row' => $rowNumber,
                        'email' => $data['email'],
                        'reason' => "Student ID \"{$studentId}\" is already used by another student in this generation.",
                    ];
                    continue;
                }

                $seenStudentIds[$studentIdKey] = true;
            }

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'role' => ['nullable', Rule::in(['admin', 'educator', 'student'])],
                'gender' => ['nullable', Rule::in(['male', 'female'])],
                'phone' => ['nullable', 'string', 'max:50'],
                'educator_id' => ['nullable', 'integer', 'exists:users,id'],
                'student_id' => ['nullable', 'string', 'max:50'],
                'class_name' => ['nullable', 'string', 'max:255'],
                'generation' => ['nullable', 'string', 'max:255'],
                'province' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $seenEmails[$email] = true;
                $failed[] = [
                    'row' => $rowNumber,
                    'email' => $data['email'],
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $seenEmails[$email] = true;
            $validated = $validator->validated();
            $validated['role'] = $validated['role'] ?? 'student';
            $validated['password'] = Hash::make($defaultPassword);

            try {
                $user = User::create($validated);

                $defaultAvatar = Avatar::fallbackFor($validated['gender'] ?? null);
                if ($defaultAvatar) {
                    $user->avatar_id = $defaultAvatar->id;
                    $user->save();
                }

                $successful[] = [
                    'row' => $rowNumber,
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ];
            } catch (Throwable $e) {
                $failed[] = [
                    'row' => $rowNumber,
                    'email' => $data['email'],
                    'errors' => ['Could not create this user. Please try again.'],
                ];
            }
        }

        return [
            'summary' => [
                'total_rows' => $processedRows,
                'successful' => count($successful),
                'failed' => count($failed),
                'skipped' => count($skipped),
            ],
            'results' => [
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
        ];
    }

    public function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->fromArray([
            'Sokha Chan', 'sokha.chan@example.com', 'student', 'male', '012345678', '1001', '10A', '2026', 'Phnom Penh', '',
        ], null, 'A2');

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        return $spreadsheet;
    }

    private function readRows(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            Log::error('User import: uploaded file path is missing or unreadable.', [
                'client_name' => $file->getClientOriginalName(),
                'real_path' => $file->getRealPath(),
            ]);

            throw new InvalidArgumentException('Could not read the uploaded file. Please make sure it is a valid Excel or CSV file.');
        }

        try {

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $e) {
            Log::error('User import: failed to read uploaded spreadsheet.', [
                'client_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            $message = 'Could not read the uploaded file. Please make sure it is a valid Excel or CSV file.';

            if (config('app.debug')) {
                $message .= ' (' . get_class($e) . ': ' . $e->getMessage() . ')';
            }

            throw new InvalidArgumentException($message);
        }

        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    private function mapHeader(array $header): array
    {
        $map = [];

        foreach ($header as $column => $label) {
            $normalized = strtolower(trim((string) $label));
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            if (isset(self::COLUMN_MAP[$normalized])) {
                $map[$column] = self::COLUMN_MAP[$normalized];
            }
        }

        return $map;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function extractRowData(array $row, array $fieldsByColumn): array
    {
        $data = [];

        foreach ($fieldsByColumn as $column => $field) {
            $value = $row[$column] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if (in_array($field, ['class_name', 'generation'], true)) {
                    $value = preg_replace('/\s+/', ' ', $value);
                }
            }
            $data[$field] = ($value === '' ? null : $value);
        }

        if (! empty($data['role'])) {
            $data['role'] = strtolower((string) $data['role']);
        }

        if (! empty($data['gender'])) {
            $data['gender'] = strtolower((string) $data['gender']);
        }

        if (array_key_exists('student_id', $data) && $data['student_id'] !== null) {
            $data['student_id'] = (string) $data['student_id'];
        }

        if (array_key_exists('educator_id', $data) && $data['educator_id'] !== null && $data['educator_id'] !== '') {
            $data['educator_id'] = (int) $data['educator_id'];
        } else {
            $data['educator_id'] = null;
        }

        return $data;
    }
}
