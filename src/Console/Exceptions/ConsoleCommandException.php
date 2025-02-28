<?php

namespace Abdphyr\Swagger\Console\Exceptions;

use Illuminate\Console\Command;

class ConsoleCommandException extends \Exception
{
    public function __construct(public string $severity, string $message = "", int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function output(Command $command)
    {
        if (isset($command->{$this->severity})) {
            $command->{$this->severity}($this->message);
        } else {
            $command->info($this->message);
        }
    }
}
