<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $perPage = request()->query('per_page', config('app.pagination.per_page'));
        $forms = Form::latest()->paginate($perPage);

        return $this->paginatedResponse(
            FormResource::collection($forms),
            'Forms retrieved successfully',
            Response::HTTP_OK
        );
    }

    /**
     * Store a newly created resource in storage.
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
     * Display the specified resource.
     */
    public function show(Form $form)
    {
        return $this->successResponse(
            new FormResource($form),
            'Form retrieved successfully',
            Response::HTTP_OK
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormRequest $request, Form $form)
    {
        $form->update($request->validated());

        return $this->successResponse(
            new FormResource($form),
            'Form updated successfully',
            Response::HTTP_OK
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Form $form)
    {
        $form->delete();

        return $this->successResponse(
            null,
            'Form deleted successfully',
            Response::HTTP_OK
        );
    }
}
