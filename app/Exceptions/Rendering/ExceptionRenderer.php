<?php

declare(strict_types=1);

namespace App\Exceptions\Rendering;

use Illuminate\Http\Request;
use Throwable;

/**
 * Contract for exception renderers in the exception rendering pipeline.
 */
interface ExceptionRenderer
{
    /**
     * Attempt to render the exception.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse|null
     */
    public function render(Throwable $e, Request $request): mixed;
}
