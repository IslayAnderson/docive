<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/core/wizard.php';
require_once __DIR__ . '/core/paperless.php';
include_once __DIR__ . '/core/build_assets.php';

$path = isset($_SERVER['REQUEST_URI']) ? trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';

if ($path === 'preview-scan') {
    $pages = $_SESSION['wizard']['scan-preview']['scanned_pages'] ?? [];
    $file = end($pages) ?: null;
    if ($file && file_exists($file)) {
        try {
            $image = wizard_pdf_preview_image($file);
            header('Content-Type: image/png');
            readfile($image);
            exit;
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo $e->getMessage();
            exit;
        }
    }
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain');
        echo "session_id: " . session_id() . "\n";
        echo "pages: " . var_export($pages, true) . "\n";
        echo "file: " . var_export($file, true) . "\n";
        echo "file_exists: " . var_export($file ? file_exists($file) : null, true) . "\n";
        exit;
    }
    http_response_code(404);
    exit;
}

if (isset($_GET['restart'])) {
    unset($_SESSION['wizard']);
    header('Location: /');
    exit;
}

$loader = new \Twig\Loader\FilesystemLoader('templates');
//$twig = new \Twig\Environment($loader, [
//    'cache' => 'compilation_cache',
//]);
$twig = new \Twig\Environment($loader);

$stage = wizard_stage_by_slug($path) ?? wizard_stage_by_slug('');

$stages = wizard_stages();
$currentIndex = $stage['stage'] - 1;
$prev = $stages[$currentIndex - 1] ?? null;
$next = $stages[$currentIndex + 1] ?? null;

$scanError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($stage['slug'] === 'scan-preview' && in_array($action, ['scan', 'rescan', 'remove-last'], true)) {
        $pages = $_SESSION['wizard']['scan-preview']['scanned_pages'] ?? [];

        if ($action === 'rescan' || $action === 'remove-last') {
            array_pop($pages);
        }

        if ($action === 'scan' || $action === 'rescan') {
            $settings = $_SESSION['wizard']['scanner-settings'] ?? [];
            try {
                $file = wizard_run_scan($settings['scan_mode'] ?? 'lineart', (int) ($settings['scan_resolution'] ?? 600));
                $pages[] = $file;
            } catch (\RuntimeException $e) {
                $scanError = $e->getMessage();
            }
        }

        $_SESSION['wizard']['scan-preview']['scanned_pages'] = $pages;
    } else {
        foreach ($_POST as $key => $value) {
            if ($key === 'next') {
                continue;
            }
            $_SESSION['wizard'][$stage['slug']][$key] = $value;
        }

        $requirementMet = empty($stage['requires']) || !empty($_SESSION['wizard'][$stage['slug']][$stage['requires']]);
        if ($requirementMet && $next) {
            header('Location: ' . wizard_stage_url($next));
            exit;
        }
    }
}

$uploadError = null;

if ($stage['slug'] === 'process-preview'
    && empty($_SESSION['wizard']['process-preview']['document_id'])
    && !empty($_SESSION['wizard']['scan-preview']['scanned_pages'])
) {
    $pages = $_SESSION['wizard']['scan-preview']['scanned_pages'];
    $typeLabel = $_SESSION['wizard']['document-type']['document_type'] ?? null;
    $sizeLabel = wizard_document_size_label($_SESSION['wizard']['document-size']['document_size'] ?? null);
    $title = trim(($typeLabel ?: 'Scan') . ($sizeLabel ? ' - ' . $sizeLabel : '') . ' - ' . date('Y-m-d H:i'));

    try {
        $mergedFile = wizard_merge_pdfs($pages);
        $documentTypeId = $typeLabel ? paperless_get_or_create_document_type($typeLabel) : null;
        $taskId = paperless_upload_document($mergedFile, $title, $documentTypeId);
        $_SESSION['wizard']['process-preview']['document_id'] = $taskId;
    } catch (\RuntimeException $e) {
        $uploadError = $e->getMessage();
    }
}

$head = $twig->load('/components/head.twig');
$template = $twig->load('/pages/' . $stage['template'] . '.twig');
$foot = $twig->load('/components/foot.twig');

echo $head->render(['css' => $GLOBALS['css'], 'title' => 'Docive - ' . $stage['title']]);
echo '<form method="post">';
echo $template->render([
    'document_types' => $stage['slug'] === 'document-type' ? wizard_resolve_document_types() : [],
    'document_sizes' => wizard_document_sizes(),
    'scan_modes' => wizard_scan_modes(),
    'scan_resolutions' => wizard_scan_resolutions(),
    'selected' => $_SESSION['wizard'][$stage['slug']] ?? [],
    'scan_error' => $scanError,
    'upload_error' => $uploadError,
    'document_id' => $_SESSION['wizard']['process-preview']['document_id'] ?? null,
]);
echo $foot->render([
    'js' => $GLOBALS['js'],
    'stages' => $stages,
    'current_stage' => $stage['stage'],
    'prev_url' => $prev ? wizard_stage_url($prev) : null,
    'next_url' => $next ? wizard_stage_url($next) : null,
]);
echo '</form>';
