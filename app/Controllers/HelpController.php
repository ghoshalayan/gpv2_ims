<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Static "How it works" tutorial. No DB access, no special permission —
 * available to every authenticated user from the top navbar.
 */
class HelpController
{
    public function index(): string
    {
        return view('help/index', [
            'title' => 'How It Works — ' . env('APP_NAME'),
        ]);
    }
}
