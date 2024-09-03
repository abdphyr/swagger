<?php

namespace Abd\Swagger\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class DocActionAttr
{
    public ?string $url = null;
    public ?string $group = null;
    public ?string $method = null;
    public array $queryparams = [];
    public array $urlparams = [];
    public array $config = [];
    public array $typedParameters = [];

    public function __construct(
        $group = null,
        $url = null,
        $method = null,
        $urlparams = [],
        $queryparams = [],
        $config = [],
        ...$args
    ) {
        $this->group = $group;
        $this->url = $url;
        $this->method = $method;
        $this->urlparams = $urlparams;
        $this->queryparams = $queryparams;
        $this->config = $config;
        $this->typedParameters = $args;
    }

    public function getUrl()
    {
        return $this->getConfigItem('url', $this->url);
    }

    public function getGroup()
    {
        return $this->getConfigItem('group', $this->group);
    }

    public function getMethod()
    {
        return $this->getConfigItem('method', $this->method);
    }

    public function getUrlparams()
    {
        return $this->getConfigItem('urlparams', $this->urlparams);
    }

    public function getQueryparams()
    {
        return $this->getConfigItem('queryparams', $this->queryparams);
    }

    public function getConfigItem($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    public function getParameter($key, $default = null)
    {
        if (isset($this->urlparams[$key])) {
            return $this->urlparams[$key];
        } else if (isset($this->getUrlparams()[$key])) {
            return $this->getUrlparams()[$key];
        }

        if (isset($this->queryparams[$key])) {
            return $this->queryparams[$key];
        } else if (isset($this->getQueryparams()[$key])) {
            return $this->getQueryparams()[$key];
        }

        return $default;
    }

    public function getTypedParameter($key, $default = [])
    {
        if (isset($this->typedParameters[$key])) {
            return $this->typedParameters[$key] ?? [];
        }
        return $default;
    }
}
