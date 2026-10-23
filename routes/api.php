<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::post('invoice/generate', [InvoiceController::class, 'generate']);
