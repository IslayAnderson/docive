<?php
include_once __DIR__ . '/core/build_assets.php';
require_once __DIR__ . '/vendor/autoload.php';

// Define directories
$loader = new \Twig\Loader\FilesystemLoader('templates');
//$twig = new \Twig\Environment($loader, [
//    'cache' => 'compilation_cache',
//]);
$twig = new \Twig\Environment($loader);

// Sample data
$head = $twig->load('head.twig');
$template = $twig->load('index.twig');
$foot = $twig->load('foot.twig');
echo $head->render(['css' => $GLOBALS['css'], 'title' => "hello world"]);
echo $template->render(['test' => "test"]);
echo $foot->render(['js' => $GLOBALS['js']]);
