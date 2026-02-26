<?php

namespace App\Http\Controllers;

use App\Config\PointRewards;
use App\Enums\FormTypeEnum;
use App\Http\Requests\StoreFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FormController extends Controller
{
    public function __construct(
        private PointService $pointService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(FormTypeEnum::values())],
        ]);

        $type = $validated['type'];

        $forms = Form::with('user')
            ->where('type', $type)
            ->latest()
            ->get();

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
        $user =  $request->user();
        $form = Form::create($request->validated());
        $this->pointService->award($user, PointRewards::FORM_SUBMITTED);

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
