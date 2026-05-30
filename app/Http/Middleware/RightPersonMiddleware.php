<?php

namespace App\Http\Middleware;

use Closure;
use App\Traits\ResponseTrait;
use App\Rules\RightPersonRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RightPersonMiddleware
{
    use ResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $type
     * @param  string|null  $field
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $type, ?string $field = null)
    {
        // If field is explicitly passed, use it. Otherwise, look for common person ID fields.
        $keys = $field ? [$field] : ['person_id', 'supplier_id', 'driver_id', 'dealer_id', 'vendor_id'];
        $attribute = null;

        foreach ($keys as $key) {
            if ($request->has($key)) {
                $attribute = $key;
                break;
            }
        }

        if ($attribute) {
            $validator = Validator::make($request->all(), [
                $attribute => [new RightPersonRule($type)],
            ]);

            if ($validator->fails()) {
                return $this->responseError(
                    $validator->errors(),
                    $validator->errors()->first(),
                    JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        return $next($request);
    }
}
