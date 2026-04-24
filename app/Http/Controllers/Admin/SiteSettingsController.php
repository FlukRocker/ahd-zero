<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

use function app;
use function config;
use function response;

class SiteSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/SiteSettings', [
            'settings' => [
                'maintenance' => app()->isDownForMaintenance(),
                'registration' => SiteSettings::registrationEnabled(),
                'registrationLockedByEnv' => ! (bool) config('site.registration_enabled', true),
            ],
        ]);
    }

    public function toggleMaintenance(Request $request): JsonResponse
    {
        $enable = $request->boolean('enable');

        if ($enable) {
            Artisan::call('down');
        } else {
            Artisan::call('up');
        }

        return response()->json([
            'maintenance' => app()->isDownForMaintenance(),
            'message' => $enable ? 'เปิดโหมดซ่อมบำรุง' : 'ปิดโหมดซ่อมบำรุง',
        ]);
    }

    public function toggleRegistration(Request $request): JsonResponse
    {
        // Env can hard-disable: refuse to flip on if the env says no.
        if (! (bool) config('site.registration_enabled', true) && $request->boolean('enable')) {
            return response()->json([
                'registration' => false,
                'message' => 'ปิดการสมัครสมาชิกในไฟล์ตั้งค่า env',
            ], 422);
        }

        $enable = $request->boolean('enable');
        SiteSettings::set('registration_enabled', $enable);

        return response()->json([
            'registration' => SiteSettings::registrationEnabled(),
            'message' => $enable ? 'เปิดรับสมาชิกใหม่' : 'ปิดรับสมาชิกใหม่',
        ]);
    }

    public function clearCache(): JsonResponse
    {
        // IMPORTANT: this Redis instance is shared with the kurokami app on the
        // same database. A naive Cache::flush() would call FLUSHDB and wipe
        // kurokami's caches as collateral. Instead, scan-and-delete only keys
        // owned by this app (Laravel prefixes every cache key with the
        // configured cache prefix). Falls back to a full flush if the cache
        // store isn't Redis (e.g. file/array in tests).
        $store = Cache::getStore();
        $deleted = 0;

        if ($store instanceof \Illuminate\Cache\RedisStore) {
            try {
                $prefix = (string) config('cache.prefix');
                $connectionName = (string) config('cache.stores.redis.connection', 'cache');
                $connection = Redis::connection($connectionName);

                // Use SCAN (cursor-based) to avoid blocking the server. The
                // Laravel Redis facade proxies underlying client methods.
                $cursor = null;
                do {
                    /** @var array{0: int|string, 1: array<int, string>}|false $result */
                    $result = $connection->scan($cursor, ['match' => $prefix.'*', 'count' => 1000]);
                    if (! is_array($result)) {
                        break;
                    }

                    $cursor = $result[0];
                    $keys = $result[1];
                    if ($keys !== []) {
                        $connection->del($keys);
                        $deleted += count($keys);
                    }
                } while ((int) $cursor !== 0);
            } catch (Throwable) {
                // If scan misbehaves on this client, fall through to nothing
                // rather than nuke the shared db.
                return response()->json([
                    'message' => 'ไม่สามารถล้างแคชได้ — ตรวจสอบบันทึก',
                    'deleted' => 0,
                ], 500);
            }
        } else {
            Cache::flush();
        }

        return response()->json([
            'message' => 'ล้างแคชเรียบร้อยแล้ว',
            'deleted' => $deleted,
        ]);
    }
}
