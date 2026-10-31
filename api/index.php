<?php
// Adaptador de entrada serverless para Vercel
$_SERVER['SCRIPT_NAME'] = '/index.php';
require_once __DIR__ . '/../public/index.php';

