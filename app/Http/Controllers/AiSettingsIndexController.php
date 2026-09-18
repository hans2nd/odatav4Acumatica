<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AiSettingsIndexController extends Controller
{
    public function index()
    {
        return view('ai-settings.index.index');
    }
}