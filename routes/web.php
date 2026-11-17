<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ServiceController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Inertia::render('Catalog/Index'))->name('catalog');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth:admin')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [AdminSettingsController::class, 'updatePassword'])->name('settings.password.update');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/export/{format}', [ReportExportController::class, 'export'])->name('reports.export');
    Route::get('/reports/print', [ReportExportController::class, 'print'])->name('reports.print');
    Route::get('/incomes', [IncomeController::class, 'index'])->name('incomes');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/bulk', [ExpenseController::class, 'destroySelected'])->name('expenses.destroy-selected');
    Route::post('/expenses/categories', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
    Route::post('/expenses/categories/quick', [ExpenseCategoryController::class, 'quickStore'])->name('expenses.categories.quick-store');
    Route::put('/expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expenses.categories.update');
    Route::delete('/expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::delete('/inventory/bulk', [InventoryController::class, 'destroySelected'])->name('inventory.destroy-selected');
    Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
    Route::post('/inventory/categories/quick', [InventoryCategoryController::class, 'quickStore'])->name('inventory.categories.quick-store');
    Route::put('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
    Route::delete('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'destroy'])->name('inventory.categories.destroy');
    Route::get('/inventory/{inventoryItem}/history', [InventoryController::class, 'history'])->name('inventory.history');
    Route::post('/inventory/{inventoryItem}/adjustments', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
    Route::get('/inventory/{inventoryItem}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::put('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventoryItem}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments');
    Route::get('/appointments/lookups', [AppointmentController::class, 'lookups'])->name('appointments.lookups');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::post('/appointments/customers/quick', [CustomerController::class, 'quickStore'])->name('appointments.customers.quick-store');
    Route::post('/appointments/services/quick', [ServiceController::class, 'quickStore'])->name('appointments.services.quick-store');
    Route::delete('/appointments/bulk', [AppointmentController::class, 'destroySelected'])->name('appointments.destroy-selected');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.update-status');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

    Route::get('/services', [ServiceController::class, 'index'])->name('services');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::delete('/services/bulk', [ServiceController::class, 'destroySelected'])->name('services.destroy-selected');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::delete('/customers/bulk', [CustomerController::class, 'destroySelected'])->name('customers.destroy-selected');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
});
