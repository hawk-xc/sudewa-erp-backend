<?php

namespace App\Exceptions;

use App\Traits\ResponseTrait;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ResponseTrait;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = ['password', 'password_confirmation'];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*') || $request->is('wapi/*') || $request->expectsJson()) {

            if ($exception instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return $exception->getResponse();
            }

            if ($exception instanceof \Illuminate\Auth\AuthenticationException || 
                $exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException || 
                $exception instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException || 
                $exception instanceof \Tymon\JWTAuth\Exceptions\JWTException ||
                $exception instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException) {
                return $this->responseError(null, 'Unauthenticated. Your session has expired or is invalid.', 403);
            }

            if ($exception instanceof NotFoundHttpException) {
                return $this->responseError(null, 'The requested resource or endpoint was not found.', 404);
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                return $this->responseError(null, 'Method not allowed for this endpoint.', 405);
            }

            if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException || 
                $exception instanceof \Illuminate\Auth\AccessDeniedException || 
                $exception instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                return $this->responseError(null, 'You do not have the required permissions to access this resource.', 401);
            }

            if ($exception instanceof \TypeError) {
                return $this->responseError((object) ['error' => $exception->getMessage()], 'A type error occurred.', 500);
            }

            if ($exception instanceof ValidationException) {
                return $this->responseError(
                    (object) $exception->errors(), 
                    'The given data was invalid.', 
                    422
                );
            }

            return $this->responseError((object) ['error' => $exception->getMessage()], 'An unexpected server error occurred.', 500);
        }

        return parent::render($request, $exception);
    }
}
