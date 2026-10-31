<?php

use App\Controllers\HomeController;
use App\Controllers\RegisterController;
use App\Controllers\JudgeController;
use App\Controllers\AdminController;
use App\Controllers\BoardController;
use App\Controllers\ResultsController;
use App\Core\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);

$router->get('/register', [RegisterController::class, 'form']);
$router->post('/register', [RegisterController::class, 'store']);

$router->get('/judge/login', [JudgeController::class, 'loginForm']);
$router->post('/judge/login', [JudgeController::class, 'login']);
$router->get('/judge/logout', [JudgeController::class, 'logout']);
$router->get('/judge', [JudgeController::class, 'dashboard']);
$router->get('/judge/protocol', [JudgeController::class, 'protocol']);
$router->get('/judge/arm', [JudgeController::class, 'arm']);
$router->post('/judge/arm/generate', [JudgeController::class, 'armGenerate']);
$router->post('/judge/arm/winner', [JudgeController::class, 'armWinner']);
$router->post('/judge/arm/current', [JudgeController::class, 'armCurrent']);
$router->post('/judge/attempt', [JudgeController::class, 'saveAttempt']);
$router->post('/judge/lift', [JudgeController::class, 'judgeLift']);
$router->post('/judge/order', [JudgeController::class, 'orderAttempt']);
$router->post('/judge/defer', [JudgeController::class, 'deferLift']);
$router->get('/judge/weigh-in', [JudgeController::class, 'weighInForm']);
$router->get('/judge/weigh-in/{id}', [JudgeController::class, 'weighInCard']);
$router->post('/judge/weigh-in', [JudgeController::class, 'weighIn']);

$router->get('/admin/login', [AdminController::class, 'loginForm']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->get('/admin/logout', [AdminController::class, 'logout']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/participants/{id}', [AdminController::class, 'editForm']);
$router->post('/admin/participants/{id}', [AdminController::class, 'update']);
$router->post('/admin/participants/{id}/status', [AdminController::class, 'setStatus']);
$router->post('/admin/participants/{id}/delete', [AdminController::class, 'delete']);

$router->get('/admin/users', [AdminController::class, 'users']);
$router->get('/admin/users/new', [AdminController::class, 'userCreateForm']);
$router->post('/admin/users', [AdminController::class, 'userStore']);
$router->get('/admin/users/{role}/{id}', [AdminController::class, 'userEditForm']);
$router->post('/admin/users/{role}/{id}', [AdminController::class, 'userUpdate']);
$router->post('/admin/users/{role}/{id}/delete', [AdminController::class, 'userDelete']);

$router->get('/results', [ResultsController::class, 'index']);
$router->get('/results/pdf', [ResultsController::class, 'pdf']);

$router->get('/live', [BoardController::class, 'index']);
$router->get('/live/data', [BoardController::class, 'data']);
