<?php
return [
 'name'=>env('APP_NAME','PService'),
 'env'=>env('APP_ENV','production'),
 'debug'=>(bool) env('APP_DEBUG',false),
 'url'=>env('APP_URL','http://localhost'),
 'timezone'=>'America/Sao_Paulo',
 'locale'=>env('APP_LOCALE','pt_BR'),
 'fallback_locale'=>env('APP_FALLBACK_LOCALE','pt_BR'),
 'key'=>env('APP_KEY'),
 'cipher'=>'AES-256-CBC',
];
