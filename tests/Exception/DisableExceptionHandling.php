<?php

namespace Propaganistas\LaravelFakeId\Tests\Exception;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;

class DisableExceptionHandling extends Handler
{
    public function __construct()
    {
    }

    public function report(Exception $e)
    {
    }

    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }
}