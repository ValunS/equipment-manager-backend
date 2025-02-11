<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\EquipmentService;
use Illuminate\Http\Request;

// Для транзакций

class EquipmentController extends Controller
{
    /**
     * @var EquipmentService
     */
    protected $equipmentService;

    /**
     * EquipmentController constructor.
     *
     * @param EquipmentService $equipmentService
     */
    public function __construct(EquipmentService $equipmentService)
    {
        $this->equipmentService = $equipmentService;
    }

    /**
     * Вывод списка оборудования.
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $equipment = Equipment::with('type');

        $equipment->where(function ($query) use ($request) {
            if ($request->filled('q')) {
                $searchTerm = $request->input('q');
                $query->where(function ($subquery) use ($searchTerm) {
                    $subquery->where('serial_number', 'like', "%$searchTerm%")
                        ->orWhere('desc', 'like', "%$searchTerm%")
                        ->orWhereHas('type', function ($subquery) use ($searchTerm) {
                            $subquery->where('name', 'like', "%$searchTerm%")
                                ->orWhere('mask', 'like', "%$searchTerm%");
                        });
                });
            }

            if ($request->filled('serial_number')) {
                $query->where('serial_number', 'like', '%' . $request->input('serial_number') . '%');
            }

            if ($request->filled('mask')) {
                $query->WhereHas('type', function ($subquery) use ($request) {
                    $subquery->where('mask', 'like', '%' . $request->input('mask') . '%');
                });
            }

            if ($request->filled('desc')) {
                $query->where('desc', 'like', '%' . $request->input('desc') . '%');
            }

            if ($request->filled('equipment_type_id')) {
                $query->where('equipment_type_id', $request->input('equipment_type_id'));
            }
        });

        return EquipmentResource::collection($equipment->paginate(10));
    }

    /**
     * Вывод информации об оборудовании.
     *
     * @param Equipment $equipment
     * @return EquipmentResource
     */
    public function show(Equipment $equipment)
    {
        return new EquipmentResource($equipment->load('type')); // Загрузка отношения "тип оборудования"
    }

    /**
     * Создание новой записи оборудования.
     *
     * @param StoreEquipmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        //раз вам надо без выхода из цикла, то без валидации, хотя реквест на стор есть
        $data = $request->all(); //->validated();

        $results = $this->equipmentService->createEquipment($data);

        return response()->json($results);
    }

    /**
     * Обновление информации об оборудовании.
     *
     * @param UpdateEquipmentRequest $request
     * @param Equipment $equipment
     * @return EquipmentResource
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment)
    {
        $data = $request->validated();
        $equipment = $this->equipmentService->updateEquipment($equipment, $data);

        return new EquipmentResource($equipment->refresh()->load('type')); // Обновление и загрузка отношения
    }

    /**
     * Удаление оборудования.
     *
     * @param Equipment $equipment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Equipment $equipment)
    {
        $equipment->delete();

        return response()->json(['message' => 'Оборудование успешно удалено.'], 204); // 204 No Content
    }
}
