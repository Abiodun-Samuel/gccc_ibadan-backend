<?php

namespace App\Http\Controllers;

use App\Models\ClientErrorLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientErrorLogController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'nullable|string',
            'stack' => 'nullable|string',
            'componentStack' => 'nullable|string',
            'errorId' => 'nullable|string',
            'url' => 'nullable|string',
            'userAgent' => 'nullable|string',
        ]);

        $log = ClientErrorLog::create([
            'message' => $validated['message'] ?? null,
            'stack' => $validated['stack'] ?? null,
            'component_stack' => $validated['componentStack'] ?? null,
            'error_id' => $validated['errorId'] ?? null,
            'url' => $validated['url'] ?? null,
            'user_agent' => $validated['userAgent'] ?? null,
        ]);
        return $this->successResponse($log, 'The error has been reported — our team will look into it', Response::HTTP_OK);
    }
}
