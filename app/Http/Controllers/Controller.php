<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function paginatedResponse($data, $message = null, $code = 200)
    {
        $currentPage = $data->currentPage();
        $lastPage = $data->lastPage();
        $links = collect()
            ->push([
                'url' => $data->previousPageUrl(),
                'label' => '&laquo; Previous',
                'active' => false,
            ])
            ->merge(
                collect(range(1, $lastPage))->map(fn($page) => [
                    'url' => $data->url($page),
                    'label' => (string) $page,
                    'active' => $page === $currentPage,
                ])
            )
            ->push([
                'url' => $data->nextPageUrl(),
                'label' => 'Next &raquo;',
                'active' => false,
            ])
            ->all();

        return response()->json(
            [
                'success' => true,
                'message' => $message,
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $currentPage,
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $lastPage,
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                ],
                'links' => $links,
            ],
            $code
        );
    }
    protected function successResponse($data, $message = null, $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }
    protected function errorResponse($data = null, $message = null, $code)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data
        ], $code);
    }
}
