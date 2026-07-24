<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportUsersRequest;
use App\Services\UserImportService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    public function __construct(private readonly UserImportService $importService)
    {
    }

    public function import(ImportUsersRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->import($request->file('file'));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Import completed.',
            'summary' => $result['summary'],
            'results' => $result['results'],
        ]);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = $this->importService->generateTemplate();
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'user-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}