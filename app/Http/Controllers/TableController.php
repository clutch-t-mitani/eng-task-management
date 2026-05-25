<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueIndexRequest;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    public function __construct(private readonly TableService $table) {}

    public function __invoke(IssueIndexRequest $request): JsonResponse
    {
        return response()->json($this->table->table($request->validated()));
    }
}
