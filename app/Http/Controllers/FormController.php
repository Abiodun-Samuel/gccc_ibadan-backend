<?php

namespace App\Http\Controllers;

use App\Enums\FormTypeEnum;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', config('app.pagination.per_page'));
        $type = $request->query('type', FormTypeEnum::PRAYER->value);
        $forms = Form::where('type', $type)->latest()->paginate($perPage);

        return $this->paginatedResponse(
            FormResource::collection($forms),
            'Forms retrieved successfully',
            Response::HTTP_OK
        );
    }
    public function store(StoreFormRequest $request)
    {
        $form = Form::create($request->validated());
        return $this->successResponse(
            new FormResource($form),
            'Form submitted successfully',
            Response::HTTP_CREATED
        );
    }

    public function destroy($formId)
    {
        $form = Form::findOrFail($formId);
        $form->delete();
        return $this->successResponse(
            $form,
            'Form deleted successfully',
            Response::HTTP_NO_CONTENT
        );
    }
}
