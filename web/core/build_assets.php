<?php

use ScssPhp\ScssPhp\Compiler;

$local_scss = glob(__DIR__ . '/../src/scss/**.scss');
$scss_combined = '';
foreach ($local_scss as $file) {
    $scss_combined .= file_get_contents($file);
}

$style_checksum_generated = md5($scss_combined);

if (!file_exists(__DIR__ . '/../src/scss/checksum')) {
    $style_checksum = '';
} else {
    $style_checksum = file_get_contents(__DIR__ . '/../src/scss/checksum');
}

if ($style_checksum != $style_checksum_generated) {
    file_put_contents(__DIR__ . '/../src/scss/checksum', $style_checksum_generated);

    $compiler = new Compiler();
    $compiler->setImportPaths(__DIR__ . '/../src/scss/');
    file_put_contents(__DIR__ . '/../src/css/style.css', $compiler->compileFile(__DIR__ . '/../src/scss/style.scss')->getCss());
}

$css_list = [
    glob(__DIR__ . '/../node_modules/materialize-css/dist/css/*.css')
];
$js_list = [
    glob(__DIR__ . '/../node_modules/materialize-css/dist/js/*.js')
];
$dist_js = [];
$dist_css = [];

foreach ($css_list as $file) {
    array_merge($dist_css, $file);
}
foreach ($js_list as $file) {
    array_merge($dist_js, $file);
}

$local_css = glob(__DIR__ . '/../src/css/*.css');
$local_js = glob(__DIR__ . '/../src/js/*.js');

if (!array_search('materialize', $local_css) && !array_search('materialize', $local_js) != 0) {
    foreach ($dist_css as $file) {
        $file_split = explode('/', $file);
        copy($file, __DIR__ . '/../src/css/' . $file_split[count($file_split) - 1]);
    }
    foreach ($dist_js as $file) {
        $file_split = explode('/', $file);
        copy($file, __DIR__ . '/../src/js/' . $file_split[count($file_split) - 1]);
    }
}


if (!isset($GLOBALS['css'])) {
    $GLOBALS['css'] = array();
    foreach ($local_css as $file) {
        $file_split = explode('/', $file);
        $filename = $file_split[count($file_split) - 1];
        $obj = array(
            "name" => $filename,
            "href" => "/src/css/" . $filename,
            "rel" => "stylesheet",
            "type" => "text/css",
            "media" => "all",
            "tag" => '<link rel="stylesheet" href="/src/css/' . $filename . '">'
        );
        $GLOBALS['css'][] = $obj;
    }
}

if (!isset($GLOBALS['js'])) {
    $GLOBALS['js'] = array();
    foreach ($local_js as $file) {
        $file_split = explode('/', $file);
        $filename = $file_split[count($file_split) - 1];
        $obj = array(
            "name" => $filename,
            "src" => "/src/js/" . $filename,
            "type" => "text/javascript",
            "tag" => '<script type="text/javascript" src="/src/js/' . $filename . '"></script>'
        );
        $GLOBALS['js'][] = $obj;
    }
}
