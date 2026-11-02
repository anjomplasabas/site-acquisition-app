<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmPipelineImportRequest;
use App\Http\Requests\PreviewPipelineImportRequest;
use App\Http\Requests\UploadPipelineImportRequest;
use App\Support\PipelineCsvImport;
use App\Support\PipelineFileReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PipelineImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Pipelines/Import', [
            'previewRows' => session('previewRows'),
            'summary' => session('summary'),
            'columns' => session('columns', []),
            'suggestions' => session('suggestions', []),
            'tempPath' => session('tempPath'),
            'fileExtension' => session('fileExtension'),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, PipelineFileReader::IMPORTABLE_FIELDS);
            fclose($handle);
        }, 'pipeline-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function upload(UploadPipelineImportRequest $request, PipelineFileReader $reader): RedirectResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'csv';
        $filename = Str::uuid().'.'.$extension;

        $file->storeAs('imports/temp', $filename);
        $tempPath = 'imports/temp/'.$filename;

        $columns = $reader->extractColumns(Storage::path($tempPath), $extension);

        return redirect()->route('pipelines.import')
            ->with('columns', $columns)
            ->with('suggestions', $reader->suggestMapping($columns))
            ->with('tempPath', $tempPath)
            ->with('fileExtension', $extension);
    }

    public function preview(PreviewPipelineImportRequest $request, PipelineFileReader $reader, PipelineCsvImport $importer): RedirectResponse
    {
        $tempPath = $request->validated('temp_path');
        $extension = $request->validated('file_extension');
        $mapping = array_filter($request->validated('mapping') ?? []);

        $rows = $reader->readRows(Storage::path($tempPath), $extension, $mapping);

        Storage::delete($tempPath);

        return redirect()->route('pipelines.import')
            ->with('previewRows', $importer->preview($rows));
    }

    public function confirm(ConfirmPipelineImportRequest $request, PipelineCsvImport $importer): RedirectResponse
    {
        return redirect()->route('pipelines.import')
            ->with('summary', $importer->confirm($request->validated('rows')))
            ->with('success', 'Import completed.');
    }
}
