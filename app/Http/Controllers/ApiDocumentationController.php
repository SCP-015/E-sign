<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocumentationController extends Controller
{
    public function index()
    {
        $markdown = file_get_contents(base_path('API_DOCUMENTATION.md'));
        
        return view('api-docs', [
            'markdown' => $markdown
        ]);
    }
}
