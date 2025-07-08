<?php
require_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/core/build_assets.php';


$loader = new \Twig\Loader\FilesystemLoader('templates');
//$twig = new \Twig\Environment($loader, [
//    'cache' => 'compilation_cache',
//]);
$twig = new \Twig\Environment($loader);

$path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;;
if (empty($path) || $path === '/' || !file_exists(__DIR__ . '/templates/pages' . $path . '.twig')) {
    $path = 'index';
}

$head = $twig->load('/components/head.twig');
$template = $twig->load('/pages/' . $path . '.twig');
$foot = $twig->load('/components/foot.twig');
echo $head->render(['css' => $GLOBALS['css'], 'title' => "Docive"]);
echo $template->render(['test' => "test"]);
echo $foot->render(['js' => $GLOBALS['js']]);
