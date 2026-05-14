<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function swagger(): View
    {
        return view('documentation.swagger');
    }

    public function openapi(): JsonResponse
    {
        return response()->json(json_decode(File::get(public_path('openapi.json')), true));
    }

    public function graphqlPlayground(): View
    {
        return view('documentation.graphql-playground');
    }
}
