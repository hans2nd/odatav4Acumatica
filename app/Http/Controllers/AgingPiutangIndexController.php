<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AgingPiutangIndexController extends Controller
{
    public function index()
    {
        return view('aging-piutang.index.index');
    }
}