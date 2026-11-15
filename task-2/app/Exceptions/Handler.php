<?php

namespace App\Exceptions;

use App\Http\Resources\ErrorResource;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
        $this->renderable(function (ValidationException $e, $request) {
            return $e->getResponse()
                ?? (new ErrorResource('Invalid token', 403))->response();
        });
        $this->renderable(function (HttpExceptionInterface $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }
            return (new ErrorResource('Invalid token', 403))->response();
        });
        $this->renderable(function (Throwable $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }
            return (new ErrorResource('Invalid token', 403))->response();
        });
    }
}
