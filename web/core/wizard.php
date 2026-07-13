<?php

function wizard_stages(): array
{
    return [
        ['slug' => '', 'template' => 'index', 'title' => 'Index', 'stage' => 1],
        ['slug' => 'document-type', 'template' => 'document-type', 'title' => 'Document Type', 'stage' => 2, 'requires' => 'document_type'],
        ['slug' => 'document-size', 'template' => 'document-size', 'title' => 'Document Size', 'stage' => 3, 'requires' => 'document_size'],
        ['slug' => 'scanner-settings', 'template' => 'scanner-settings', 'title' => 'Scanner Settings', 'stage' => 4, 'requires' => 'scan_mode'],
        ['slug' => 'scan-preview', 'template' => 'scan-preview', 'title' => 'Scan Preview', 'stage' => 5, 'requires' => 'scanned_pages'],
        ['slug' => 'process-preview', 'template' => 'process-preview', 'title' => 'Document Process Preview', 'stage' => 6],
        ['slug' => 'results', 'template' => 'results', 'title' => 'Results', 'stage' => 7],
    ];
}

function wizard_stage_by_slug(string $slug): ?array
{
    foreach (wizard_stages() as $stage) {
        if ($stage['slug'] === $slug) {
            return $stage;
        }
    }
    return null;
}

function wizard_stage_url(array $stage): string
{
    return '/' . $stage['slug'];
}

function wizard_fallback_document_types(): array
{
    // Used only if Paperless can't be reached when rendering the document-type stage.
    return ['Invoice', 'Letter', 'Receipt', 'ID Document', 'Other'];
}

function wizard_resolve_document_types(): array
{
    try {
        $names = paperless_list_document_types();
        if (!empty($names)) {
            return $names;
        }
    } catch (\RuntimeException $e) {
        // fall through to the offline fallback list
    }
    return wizard_fallback_document_types();
}

function wizard_document_sizes(): array
{
    return [
        ['id' => 'a4', 'label' => 'A4', 'dims' => '210 x 297mm'],
        ['id' => 'a5', 'label' => 'A5', 'dims' => '148 x 210mm'],
        ['id' => 'letter', 'label' => 'US Letter', 'dims' => '216 x 279mm'],
        ['id' => 'legal', 'label' => 'US Legal', 'dims' => '216 x 356mm'],
    ];
}

function wizard_scan_modes(): array
{
    return [
        ['id' => 'lineart', 'label' => 'Black & White', 'sane_mode' => 'Lineart'],
        ['id' => 'gray', 'label' => 'Greyscale', 'sane_mode' => 'Gray'],
        ['id' => 'color', 'label' => 'Colour', 'sane_mode' => 'Color'],
    ];
}

function wizard_scan_resolutions(): array
{
    return [150, 300, 600, 1200];
}

function wizard_document_size_label(?string $id): ?string
{
    foreach (wizard_document_sizes() as $size) {
        if ($size['id'] === $id) {
            return $size['label'];
        }
    }
    return null;
}

function wizard_sane_mode(string $id): string
{
    foreach (wizard_scan_modes() as $mode) {
        if ($mode['id'] === $id) {
            return $mode['sane_mode'];
        }
    }
    return 'Lineart';
}

function wizard_run_scan(string $modeId, int $resolution): string
{
    $script = realpath(__DIR__ . '/../../system/scanner.sh');
    $command = sprintf(
        'bash %s --mode %s --resolution %d 2>&1',
        escapeshellarg($script),
        escapeshellarg(wizard_sane_mode($modeId)),
        $resolution
    );
    exec($command, $outputLines, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(implode("\n", $outputLines));
    }
    $lastLine = end($outputLines);
    $segments = preg_split('/[\r\n]+/', $lastLine, -1, PREG_SPLIT_NO_EMPTY);
    return trim(end($segments));
}

function wizard_pdf_preview_image(string $pdfPath): string
{
    $output = sys_get_temp_dir() . '/docive-preview-' . md5($pdfPath) . '.png';
    $command = sprintf(
        'gs -dBATCH -dNOPAUSE -q -sDEVICE=png16m -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>&1',
        escapeshellarg($output),
        escapeshellarg($pdfPath)
    );
    exec($command, $outputLines, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(implode("\n", $outputLines));
    }
    return $output;
}

function wizard_merge_pdfs(array $files): string
{
    if (count($files) === 1) {
        return $files[0];
    }

    $output = dirname($files[0]) . '/merged-' . date('Ymd-His') . '.pdf';
    $command = sprintf(
        'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=%s %s 2>&1',
        escapeshellarg($output),
        implode(' ', array_map('escapeshellarg', $files))
    );
    exec($command, $outputLines, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(implode("\n", $outputLines));
    }
    return $output;
}
