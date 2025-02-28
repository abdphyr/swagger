<?php

namespace Abdphyr\Swagger\Traits;

trait ConsoleColorWrapping
{
    protected function prepare(mixed $text)
    {
        if (is_object($text) || is_array($text)) {
            return json_encode($text, JSON_PRETTY_PRINT);
        }
        return $text;
    }

    function yellow(mixed $text)
    {
        return '<fg=yellow>' . $this->prepare($text) . '</>';
    }

    function white(mixed $text)
    {
        return '<fg=white>' . $this->prepare($text) . '</>';
    }

    function blue(mixed $text)
    {
        return '<fg=blue>' . $this->prepare($text) . '</>';
    }

    function cyan(mixed $text)
    {
        return '<fg=cyan>' . $this->prepare($text) . '</>';
    }

    function green(mixed $text)
    {
        return '<fg=green>' . $this->prepare($text) . '</>';
    }

    function gray(mixed $text)
    {
        return '<fg=gray>' . $this->prepare($text) . '</>';
    }

    function red(mixed $text)
    {
        return '<fg=red>' . $this->prepare($text) . '</>';
    }
}
