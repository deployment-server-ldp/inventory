<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\Cnc\CompletionController;
use App\Http\Controllers\Cnc\CncDashboardController;
use App\Http\Controllers\Cnc\CncStockController;
use App\Http\Controllers\Cnc\MachineController;
use App\Http\Controllers\Cnc\ProductionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Imported\AssemblyController;
use App\Http\Controllers\Imported\ImportedDashboardController;
use App\Http\Controllers\Imported\ImportedStockController;
use App\Http\Controllers\Imported\TransactionController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockAdjustmentController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- public
Route::get('/health', [SystemController::class, 'ping'])->name('health');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:5,1');
});

// ---------------------------------------------------------------- authenticated
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.change.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/media/part-image/{image}/{variant}', [MediaController::class, 'partImage'])->whereIn('variant', ['full', 'thumb'])->name('media.part-image');

    // AJAX lookups (JSON)
    Route::get('/lookup/parts/{type}', [LookupController::class, 'parts'])->whereIn('type', ['cnc', 'imported'])->name('lookup.parts');
    Route::get('/lookup/completion-pool/{part}', [LookupController::class, 'completionPool'])->middleware('perm:cnc.completion.create')->name('lookup.completion-pool');

    // ------------------------------------------------ CNC
    Route::prefix('cnc')->name('cnc.')->group(function () {
        Route::get('/dashboard', [CncDashboardController::class, 'index'])->middleware('perm:cnc.dashboard')->name('dashboard');

        Route::middleware('perm:cnc.production.view')->group(function () {
            Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
            Route::get('/production/create', [ProductionController::class, 'create'])->middleware('perm:cnc.production.create')->name('production.create');
            Route::get('/production/{record}', [ProductionController::class, 'show'])->name('production.show');
            Route::get('/progress', [ProductionController::class, 'progress'])->name('progress');
            Route::get('/machines', [MachineController::class, 'index'])->name('machines.index');
            Route::get('/machines/{machine}', [MachineController::class, 'show'])->name('machines.show');
            Route::get('/machines/{machine}/monthly', [MachineController::class, 'monthly'])->name('machines.monthly');
        });
        Route::post('/production', [ProductionController::class, 'store'])->middleware('perm:cnc.production.create')->name('production.store');
        Route::post('/production/{record}/finish', [ProductionController::class, 'finish'])->middleware('perm:cnc.production.create')->name('production.finish');
        Route::get('/production/{record}/edit', [ProductionController::class, 'edit'])->middleware('perm:cnc.production.edit')->name('production.edit');
        Route::put('/production/{record}', [ProductionController::class, 'update'])->middleware('perm:cnc.production.edit')->name('production.update');
        Route::post('/production/{record}/cancel', [ProductionController::class, 'cancel'])->middleware('perm:cnc.production.cancel')->name('production.cancel');

        Route::get('/completions', [CompletionController::class, 'index'])->middleware('perm:cnc.completion.create|cnc.completion.approve')->name('completions.index');
        Route::get('/completions/create', [CompletionController::class, 'create'])->middleware('perm:cnc.completion.create')->name('completions.create');
        Route::post('/completions', [CompletionController::class, 'store'])->middleware('perm:cnc.completion.create')->name('completions.store');
        Route::get('/completions/{completion}', [CompletionController::class, 'show'])->middleware('perm:cnc.completion.create|cnc.completion.approve')->name('completions.show');
        Route::middleware('perm:cnc.completion.approve')->group(function () {
            Route::post('/completions/{completion}/approve', [CompletionController::class, 'approve'])->name('completions.approve');
            Route::post('/completions/{completion}/reject', [CompletionController::class, 'reject'])->name('completions.reject');
            Route::post('/completions/{completion}/reverse', [CompletionController::class, 'reverse'])->name('completions.reverse');
        });

        Route::middleware('perm:cnc.inventory.view')->group(function () {
            Route::get('/stock', [CncStockController::class, 'index'])->name('stock.index');
            Route::get('/stock/ledger', [CncStockController::class, 'ledger'])->name('stock.ledger');
            Route::get('/stock/issue', [CncStockController::class, 'issueForm'])->middleware('perm:cnc.inventory.issue')->name('stock.issue');
            Route::post('/stock/issue', [CncStockController::class, 'issue'])->middleware('perm:cnc.inventory.issue')->name('stock.issue.store');
            Route::post('/stock/transactions/{transaction}/reverse', [CncStockController::class, 'reverse'])->middleware('perm:cnc.inventory.reverse')->name('stock.reverse');
        });
    });

    // ------------------------------------------------ Parts master (shared controller; inventory type derived from the route name)
    foreach (['cnc' => ['cnc/parts', 'cnc.parts', 'cnc.inventory.view', 'cnc.parts.manage'], 'imported' => ['imported/products', 'imported.products', 'imported.inventory.view', 'imported.products.manage']] as $type => [$prefix, $name, $viewPerm, $managePerm]) {
        Route::prefix($prefix)->name($name.'.')->group(function () use ($viewPerm, $managePerm) {
            Route::get('/', [PartController::class, 'index'])->middleware("perm:{$viewPerm}|{$managePerm}")->name('index');
            Route::middleware("perm:{$managePerm}")->group(function () {
                Route::get('/create', [PartController::class, 'create'])->name('create');
                Route::post('/', [PartController::class, 'store'])->name('store');
                Route::post('/quick', [PartController::class, 'quickStore'])->name('quick-store');
                Route::get('/{part}/edit', [PartController::class, 'edit'])->name('edit');
                Route::put('/{part}', [PartController::class, 'update'])->name('update');
                Route::post('/{part}/images', [PartController::class, 'addImage'])->name('images.store');
                Route::post('/{part}/images/{image}/primary', [PartController::class, 'primaryImage'])->name('images.primary');
                Route::delete('/{part}/images/{image}', [PartController::class, 'deleteImage'])->name('images.destroy');
            });
            Route::get('/{part}', [PartController::class, 'show'])->middleware("perm:{$viewPerm}|{$managePerm}")->name('show');
        });
    }

    // ------------------------------------------------ Imported
    Route::prefix('imported')->name('imported.')->group(function () {
        Route::get('/dashboard', [ImportedDashboardController::class, 'index'])->middleware('perm:imported.dashboard')->name('dashboard');
        Route::get('/in/create', [TransactionController::class, 'createIn'])->middleware('perm:imported.in.create')->name('in.create');
        Route::post('/in', [TransactionController::class, 'storeIn'])->middleware('perm:imported.in.create')->name('in.store');
        Route::get('/out/create', [TransactionController::class, 'createOut'])->middleware('perm:imported.out.create')->name('out.create');
        Route::post('/out', [TransactionController::class, 'storeOut'])->middleware('perm:imported.out.create')->name('out.store');
        Route::middleware('perm:imported.inventory.view')->group(function () {
            Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
            Route::get('/stock', [ImportedStockController::class, 'index'])->name('stock.index');
            Route::get('/stock/ledger', [ImportedStockController::class, 'ledger'])->name('stock.ledger');
        });
        Route::post('/transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->middleware('perm:imported.transactions.reverse')->name('transactions.reverse');

        Route::get('/assemblies', [AssemblyController::class, 'index'])->middleware('perm:imported.assembly.view')->name('assemblies.index');
        Route::middleware('perm:imported.assembly.manage')->group(function () {
            Route::get('/assemblies/create', [AssemblyController::class, 'create'])->name('assemblies.create');
            Route::post('/assemblies', [AssemblyController::class, 'store'])->name('assemblies.store');
            Route::get('/assemblies/{assembly}/edit', [AssemblyController::class, 'edit'])->name('assemblies.edit');
            Route::put('/assemblies/{assembly}', [AssemblyController::class, 'update'])->name('assemblies.update');
            Route::post('/assemblies/{assembly}/items', [AssemblyController::class, 'storeItem'])->name('assemblies.items.store');
            Route::put('/assemblies/{assembly}/items/{item}', [AssemblyController::class, 'updateItem'])->name('assemblies.items.update');
            Route::post('/assemblies/{assembly}/issue', [AssemblyController::class, 'issue'])->middleware('perm:imported.out.create')->name('assemblies.issue');
        });
        Route::get('/assemblies/{assembly}', [AssemblyController::class, 'show'])->middleware('perm:imported.assembly.view')->name('assemblies.show');
    });

    // ------------------------------------------------ Adjustments
    Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->middleware('perm:cnc.stock.adjust|imported.stock.adjust')->name('adjustments.index');
    Route::get('/adjustments/create/{type}', [StockAdjustmentController::class, 'create'])->whereIn('type', ['cnc', 'imported'])->name('adjustments.create');
    Route::post('/adjustments/{type}', [StockAdjustmentController::class, 'store'])->whereIn('type', ['cnc', 'imported'])->name('adjustments.store');

    // ------------------------------------------------ Reports
    Route::get('/reports', [ReportController::class, 'index'])->middleware('perm:reports.cnc|reports.imported')->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');

    // ------------------------------------------------ Settings (generic master data CRUD)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/{entity}', [SettingsController::class, 'index'])->name('index');
        Route::get('/{entity}/create', [SettingsController::class, 'create'])->name('create');
        Route::post('/{entity}', [SettingsController::class, 'store'])->name('store');
        Route::get('/{entity}/{id}/edit', [SettingsController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{entity}/{id}', [SettingsController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('/{entity}/{id}/toggle', [SettingsController::class, 'toggle'])->whereNumber('id')->name('toggle');
    });

    // ------------------------------------------------ Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('perm:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        });
        Route::middleware('perm:roles.manage')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
        Route::get('/activity', [ActivityLogController::class, 'index'])->middleware('perm:audit.view')->name('activity.index');
        Route::get('/activity/{log}', [ActivityLogController::class, 'show'])->middleware('perm:audit.view')->name('activity.show');
        Route::middleware('perm:system.health')->group(function () {
            Route::get('/system/health', [SystemController::class, 'health'])->name('system.health');
            Route::get('/system/logs', [SystemController::class, 'logs'])->name('system.logs');
        });
        Route::get('/company', [CompanySettingsController::class, 'edit'])->middleware('perm:settings.company')->name('company.edit');
        Route::put('/company', [CompanySettingsController::class, 'update'])->middleware('perm:settings.company')->name('company.update');
    });
});
