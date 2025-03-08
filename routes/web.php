<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApplicationController;


Route::get('/', function () {
    return view('job_application');

});

Route::post('/submit-application',[ApplicationController::class,'submit'])->name('submit-application');
