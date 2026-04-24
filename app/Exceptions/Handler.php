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

    /**
     * Convert an exception into an HTTP response.
     */
    // public function render($request, Throwable $exception)
    // {
    //     if ($exception instanceof NotFoundHttpException) {
    //         return $this->responseError(null, 'API endpoint not found', 404);
    //     }

    //     if ($exception instanceof MethodNotAllowedHttpException) {
    //         return $this->responseError(null, 'Method not allowed for this endpoint', 405);
    //     }

    //     if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
    //         return $this->responseError(null, 'You do not have the required role/permission', 403);
    //     }

    //     if ($exception instanceof \TypeError) {
    //         return $this->responseError(['error' => $exception->getMessage()], 'A type error occurred', 500);
    //     }

    //     if ($request->expectsJson()) {
    //         return $this->responseError(['error' => $exception->getMessage()], 'Unexpected server error', 500);
    //     }

    //     if ($exception instanceof ValidationException) {
    //         return $this->responseError(
    //             $exception->errors(), 
    //             'Validation failed', 
    //             422
    //         );
    //     }

    //     return parent::render($request, $exception);
    // }

    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*') || $request->is('wapi/*') || $request->expectsJson()) {

            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return $this->responseError(null, 'Unauthenticated. Your session has expired or is invalid.', 401);
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
                return $this->responseError(null, 'You do not have the required permissions to access this resource.', 403);
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
