<?php

require dirname(__DIR__).'/vendor/autoload.php';
$loader = new \Illuminate\Translation\FileLoader(new \Illuminate\Filesystem\Filesystem(),dirname(__DIR__).'/src/language/');



$lang = new Illuminate\Translation\Translator($loader,'zh');
echo $lang->get('form_validation_required');
