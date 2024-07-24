<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        // checks if token is present in the request.
        // checks if the token is valid, if not it throws an exception.
        // Also checks if the token is expired, if so it throws an exception.
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json(
                [
                    'error' => 'Unauthenticated: check if the token is present and valid.'
                ],
                401
            );
        }

        //return a JSON response for all other types of exceptions
        if ($request->expectsJson()) {
            return new JsonResponse(
                [
                    'error' => 'Something went wrong.'
                ],
                500
            );
        }

        if ($exception instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
            return response()->json(
                [
                    'error' => 'Route not found',
                ],
                404
            );
        }

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            $allowedMethods = $exception->getHeaders()['Allow'];
            return response()->json(
                [
                    'error' => 'Method not allowed',
                    'allowed_methods' => $allowedMethods,
                ],
                405
            );
        }

        return parent::render($request, $exception);
    }
}