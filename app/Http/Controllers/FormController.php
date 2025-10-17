<?php

namespace App\Http\Controllers;

use App\Enums\FormTypeEnum;
use App\Http\Requests\StoreFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FormController extends Controller
{
    /**
     * Display a listing of the forms by type.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(FormTypeEnum::values())],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = $validated['per_page'] ?? config('app.pagination.per_page');
        $type = $validated['type'];

        $forms = Form::query()
            ->where('type', $type)
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            FormResource::collection($forms),
            'Forms retrieved successfully',
            Response::HTTP_OK
        );
    }

    /**
     * Store a newly created form.
     */
    public function store(StoreFormRequest $request)
    {
        $form = Form::create($request->validated());
        return $this->successResponse(
            new FormResource($form),
            'Form submitted successfully',
            Response::HTTP_CREATED
        );
    }

    /**
     * Delete multiple forms at once.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:forms,id'],
        ]);

        Form::whereIn('id', $validated['ids'])->delete();

        return $this->successResponse(
            null,
            'Forms deleted successfully',
            Response::HTTP_NO_CONTENT
        );
    }

    /**
     * Mark multiple forms as completed.
     */
    public function markAsCompleted(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:forms,id'],
        ]);

        Form::whereIn('id', $validated['ids'])->update([
            'is_completed' => true,
        ]);

        return $this->successResponse(
            null,
            'Forms marked as completed successfully',
            Response::HTTP_OK
        );
    }
}
