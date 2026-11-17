<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'price_asc', 'price_desc', 'duration_asc', 'duration_desc', 'newest', 'oldest'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $query = Service::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $services) use ($search): void {
                $services->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        match ($filters['status'] ?? 'all') {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        match ($filters['sort'] ?? 'newest') {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'duration_asc' => $query->orderBy('duration_minutes')->orderBy('name'),
            'duration_desc' => $query->orderByDesc('duration_minutes')->orderBy('name'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = (int) ($filters['per_page'] ?? 10);

        $services = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'is_active' => $service->is_active,
                'created_at' => $service->created_at,
            ]);

        $serviceStats = Service::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive')
            ->selectRaw('AVG(price) as average_price')
            ->first();

        return Inertia::render('Services/Index', [
            'services' => $services,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'sort' => $filters['sort'] ?? 'newest',
                'per_page' => $perPage,
            ],
            'summary' => [
                'total' => (int) $serviceStats->total,
                'active' => (int) $serviceStats->active,
                'inactive' => (int) $serviceStats->inactive,
                'average_price' => (float) ($serviceStats->average_price ?? 0),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::query()->create($request->validate($this->serviceRules()));

        return back()->with('toast', [
            'type' => 'success',
            'message' => $service->name.' berhasil ditambahkan.',
        ]);
    }

    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
        ]);
        $service = Service::query()->create([
            ...$validated,
            'description' => null,
            'is_active' => true,
        ]);

        return response()->json([
            'service' => $service->only(['id', 'name', 'price', 'duration_minutes', 'is_active']),
        ], 201);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($request->validate($this->serviceRules()));

        return back()->with('toast', [
            'type' => 'success',
            'message' => $service->name.' berhasil diperbarui.',
        ]);
    }

    public function destroy(Service $service): RedirectResponse
    {
        if ($service->appointments()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Layanan dalam riwayat janji temu tidak dapat dihapus.',
            ]);
        }

        $service->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Layanan berhasil dihapus.',
        ]);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:services,id'],
        ]);

        $deletableServices = Service::query()
            ->whereIn('id', $validated['ids'])
            ->doesntHave('appointments');

        $deletedCount = (clone $deletableServices)->count();
        $deletableServices->delete();

        if ($deletedCount < count($validated['ids'])) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $deletedCount > 0
                    ? $deletedCount.' layanan dihapus. Layanan dalam riwayat janji temu tetap disimpan.'
                    : 'Layanan dalam riwayat janji temu tidak dapat dihapus.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deletedCount.' layanan berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
