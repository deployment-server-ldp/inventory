<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemController extends Controller
{
    /** Public liveness probe. Detailed output only with the configured health token. */
    public function ping(Request $request): JsonResponse
    {
        $dbOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbOk = false;
        }
        $payload = ['status' => $dbOk ? 'ok' : 'error', 'time' => now()->toIso8601String()];
        $token = (string) config('spims.health_token');
        if ($token !== '' && hash_equals($token, (string) $request->query('token'))) {
            $payload['checks'] = collect($this->checks())->map(fn ($c) => $c['ok'])->all();
        }

        return response()->json($payload, $dbOk ? 200 : 503);
    }

    public function health(StockService $stock): View
    {
        return view('admin.system.health', ['checks' => $this->checks(), 'issues' => $stock->verify()]);
    }

    public function logs(): View
    {
        $files = collect(glob(storage_path('logs/*.log')) ?: [])->sortDesc()->values();
        $file = request('file');
        $path = $files->first(fn ($f) => basename($f) === $file) ?? $files->first();
        $content = '';
        if ($path && is_readable($path)) {
            $size = filesize($path);
            $fh = fopen($path, 'r');
            fseek($fh, max(0, $size - 200_000)); // last ~200 KB
            $content = stream_get_contents($fh);
            fclose($fh);
        }

        return view('admin.system.logs', ['files' => $files->map(fn ($f) => basename($f)), 'current' => $path ? basename($path) : null, 'content' => $content]);
    }

    private function checks(): array
    {
        $checks = [];
        try {
            $version = DB::selectOne('select version() as v')->v;
            $checks['database'] = ['label' => 'Database connection', 'ok' => true, 'detail' => $version];
        } catch (\Throwable $e) {
            $checks['database'] = ['label' => 'Database connection', 'ok' => false, 'detail' => 'Cannot connect'];
        }
        $checks['php'] = ['label' => 'PHP version ≥ 8.2', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'detail' => PHP_VERSION];
        foreach (['pdo_mysql', 'gd', 'mbstring', 'fileinfo', 'zip', 'xml'] as $ext) {
            $checks['ext_'.$ext] = ['label' => "PHP extension: {$ext}", 'ok' => extension_loaded($ext), 'detail' => extension_loaded($ext) ? 'loaded' : 'missing'];
        }
        $checks['gd_webp'] = ['label' => 'GD WebP support', 'ok' => function_exists('imagewebp'), 'detail' => function_exists('imagewebp') ? 'yes' : 'no'];
        foreach (['storage/app/private' => storage_path('app/private'), 'storage/logs' => storage_path('logs'), 'storage/framework' => storage_path('framework'), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $dir) {
            $checks['w_'.$label] = ['label' => "Writable: {$label}", 'ok' => is_dir($dir) && is_writable($dir), 'detail' => is_dir($dir) ? (is_writable($dir) ? 'writable' : 'NOT writable') : 'missing'];
        }
        $checks['debug'] = ['label' => 'Debug mode disabled in production', 'ok' => ! (app()->isProduction() && config('app.debug')), 'detail' => 'APP_ENV='.app()->environment().', APP_DEBUG='.(config('app.debug') ? 'true' : 'false')];
        $checks['key'] = ['label' => 'Application key set', 'ok' => filled(config('app.key')), 'detail' => filled(config('app.key')) ? 'set' : 'missing — run php artisan key:generate'];
        $checks['setup_token'] = ['label' => 'Setup token removed after install', 'ok' => blank(config('spims.setup_token')), 'detail' => blank(config('spims.setup_token')) ? 'not set' : 'SPIMS_SETUP_TOKEN still set in .env'];
        $checks['https'] = ['label' => 'Secure session cookie', 'ok' => ! app()->isProduction() || (bool) config('session.secure'), 'detail' => config('session.secure') ? 'enabled' : 'disabled'];
        try {
            $imgs = count(Storage::disk('local')->allFiles('parts'));
            $checks['images'] = ['label' => 'Image storage readable', 'ok' => true, 'detail' => $imgs.' file(s)'];
        } catch (\Throwable) {
            $checks['images'] = ['label' => 'Image storage readable', 'ok' => false, 'detail' => 'error'];
        }
        try {
            $pending = DB::table('migrations')->count();
            $checks['migrations'] = ['label' => 'Migrations applied', 'ok' => $pending > 0, 'detail' => $pending.' migration(s)'];
        } catch (\Throwable) {
            $checks['migrations'] = ['label' => 'Migrations applied', 'ok' => false, 'detail' => 'migrations table missing'];
        }

        return $checks;
    }
}
