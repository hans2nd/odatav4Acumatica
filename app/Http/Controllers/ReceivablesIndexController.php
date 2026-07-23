<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReceivablesIndexController extends Controller
{
    public function index()
    {
        return view('receivables.index.index');
    }
}