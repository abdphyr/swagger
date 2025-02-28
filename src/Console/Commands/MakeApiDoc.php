<?php

namespace Abdphyr\Swagger\Console\Commands;

use Abdphyr\Swagger\Attributes\Http\ActionMethod;
use Abdphyr\Swagger\Console\Exceptions\ConsoleCommandException;
use Abdphyr\Swagger\Console\Helpers\RunAction;
use Abdphyr\Swagger\Traits\ConsoleColorWrapping;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MakeApiDoc extends Command
{
    use ConsoleColorWrapping;

    const OBJ = "object";
    const NUM = "integer";
    const ARR = "array";
    const STR = "string";
    const BOO = "boolean";
    const BIN = "binary";

    protected $signature = 'generate:swagger {--controller= : Controller name}';

    protected $description = 'Generates API data openAPI format and save cache';

    public function handle()
    {
        $result = $this->getCacheData();
        $controllers = $this->getControllersList();
        foreach ($controllers as $controller) {
            $methods = $this->getMethods($controller);
            foreach ($methods as $method) {
                $this->info($controller . "::" . $this->yellow($method . '()'));
                try {
                    $action = new RunAction($controller, $method);
                    $actionMethodAttr = $action->actionMethodAttr;
                    if (isset($result[$actionMethodAttr->uri])) {
                        $result[$actionMethodAttr->uri][strtolower($actionMethodAttr->method)] = $this->resolveResponse($action);
                    } else {
                        $result[$actionMethodAttr->uri] = [
                            strtolower($actionMethodAttr->method) => $this->resolveResponse($action)
                        ];
                    }
                } catch (ConsoleCommandException $th) {
                    $th->output($this);
                }
            }
        }
        $this->setCacheData($result);
    }

    protected function getCacheData($data = null, $path = null)
    {
        $path = $path ?? base_path('bootstrap/cache/swagger.php');
        if (!file_exists($path)) {
            file_put_contents($path, "<?php\nreturn " . var_export($data ?? [], true) . ";");
        }
        $data = require_once $path;
        return is_array($data) ? $data : [];
    }

    protected function setCacheData($data, $path = null)
    {
        $path = $path ?? base_path('bootstrap/cache/swagger.php');
        file_put_contents($path, "<?php\nreturn " . var_export($data ?? [], true) . ";");
        return $data;
    }

    protected function getMethods(string $controller)
    {
        $reflectionController = new \ReflectionClass($controller);
        $publicMethods = $reflectionController->getMethods(\ReflectionMethod::IS_PUBLIC);
        $methods = array_filter($publicMethods, fn($m) => $m->getAttributes(ActionMethod::class));
        return array_map(fn($m) => $m->getName(), $methods);
    }

    protected function getControllersList()
    {
        $namespace = "App\\Http\\Controllers";
        $path = app_path('Http/Controllers');
        return $this->getFiles($path, $namespace, '');
    }

    protected function getFiles($path, $namespace, $subnamespace)
    {
        $result = [];
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            if (is_dir("$path/$file")) {
                $sub = $this->getFiles("$path/$file", "$namespace\\$file", ($subnamespace ? "$subnamespace\\$file" : $file));
                $result = [...$result, ...$sub];
            } else {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if ($option = $this->option('controller')) {
                    if ($option == ($subnamespace ? "$subnamespace\\$name" : $name)) {
                        $result[] = "$namespace\\$name";
                    }
                } else $result[] = "$namespace\\$name";
            }
        }
        return $result;
    }

    protected function resolveResponse(RunAction $action)
    {
        $actionMethodAttr = $action->actionMethodAttr;
        $result['tags'] = $actionMethodAttr->tags;
        $result['description'] = $actionMethodAttr->description;
        $result['summary'] = $actionMethodAttr->summary;
        $this->setParameters($parameters, $actionMethodAttr->route, 'path');
        $this->setParameters($parameters, $actionMethodAttr->query, 'query');
        $this->setParameters($parameters, $actionMethodAttr->headers, 'header');
        $result['parameters'] = $parameters;
        if ($actionMethodAttr->hasAuth) $result['security'] = [['bearerAuth' => []]];
        $this->setRequestBody($result, $actionMethodAttr->request, $actionMethodAttr->files);
        $this->setResponseContent($result, $action->getResponse());
        return $result;
    }

    protected function setParameters(&$parameters, $params, $location)
    {
        if (!$parameters) $parameters = [];
        foreach ($params as $key => $value) {
            $parameters[] = [
                'name' => $key,
                'in' => $location,
                'required' => false,
                'schema' => ['type' => $this->getType($value), 'example' => $value]
            ];
        }
    }

    protected function setRequestBody(&$result, $request = [], $files = [])
    {
        $contentType = $files ? 'multipart/form-data' : 'application/json';
        if ($request || $files) {
            $result['requestBody'] = ['content' => [$contentType => ['schema' => $this->convertDataToDocFormat($request + $files)]]];
        }
    }

    protected function setResponseContent(&$result, JsonResponse|Response $response)
    {
        $status = $response->getStatusCode();
        $contentType = $response->headers->get('content-type', 'application/json');
        $data = json_decode($response->getContent(), true);

        $result['responses'] = [
            $status => [
                'description' => $response->statusText(),
                'content' => [$contentType => ['schema' => $this->convertDataToDocFormat($data ? $data : $response->getContent())]]
            ]
        ];
    }

    protected function convertDataToDocFormat($data)
    {
        $type = $this->getType($data);
        if ($type == self::OBJ) {
            foreach ($data as $key => $value) $properties[$key] = $this->convertDataToDocFormat($value);
            return ["type" => self::OBJ, "properties" => $properties];
        } else if ($type == self::ARR) {
            return ["type" => self::ARR, "items" => $this->convertDataToDocFormat(isset($data[0]) ? $data[0] : null)];
        } else if ($type == self::BIN) return ["type" => self::STR, "format" => self::BIN];
        else if ($type == self::STR) return ["type" => self::STR, "example" => $data];
        else if ($type == self::NUM) return ["type" => self::NUM, "example" => $data];
        else if ($type == self::BOO) return ["type" => self::BOO, "example" => $data];
        else if ($type == NULL) return ["type" => NULL, "example" => NULL];
    }

    protected function getType($value)
    {
        if ($value instanceof UploadedFile) return self::BIN;
        if ($this->is_object_array($value)) return self::OBJ;
        if (is_numeric($value)) return self::NUM;
        if (is_bool($value)) return self::BOO;
        if (is_string($value)) return self::STR;
        if (is_array($value)) return self::ARR;
        if (is_null($value)) return null;
    }

    protected function is_object_array($array)
    {
        if (!is_array($array)) return false;
        foreach ($array as $key => $value) if (is_string($key)) return true;
        return false;
    }

    protected function outputInfo($text, SymfonyResponse $response)
    {
        $this->info($response->isSuccessful() ? $this->green($text) : $this->red($text));
    }
}
