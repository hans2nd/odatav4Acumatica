<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PiutangIndexController extends Controller
{
    public function index()
    {
        return view('piutang.index.index');
    }
}