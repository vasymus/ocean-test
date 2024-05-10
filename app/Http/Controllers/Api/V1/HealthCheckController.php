<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class HealthCheckController extends BaseApiController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $uuid = Str::uuid();

        try {
            DB::select('SELECT 1+1;');
            $dbHealthCheck = true;
        } catch (Throwable) {
            $dbHealthCheck = false;
        }

        try {
            Redis::connection();
            $redisHealthCheck = true;
        } catch (Throwable) {
            $redisHealthCheck = false;
        }

        $results = [
            'db' => $dbHealthCheck,
            'cache' => $redisHealthCheck,
        ];

        if ($dbHealthCheck) {
            HealthCheck::query()->create([
                'uuid' => $uuid,
                'results' => $results,
            ]);
        }

        return response($results)->header('X-Owner', $uuid);
    }
}
