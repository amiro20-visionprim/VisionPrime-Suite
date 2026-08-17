<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function agency(): Response
    {
        return Inertia::render('App/Training');
    }

    public function client(): Response
    {
        return Inertia::render('Client/Training');
    }
}
