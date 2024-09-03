<?php

namespace Abd\Swagger\Console\Commands;

use Abd\Swagger\Attributes\DocControllerAttr;
use Abd\Swagger\Attributes\DocActionAttr;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Symfony\Component\VarDumper\VarDumper;

class SwaggerDoc extends Command
{
    const OBJ = "object";
    const NUM = "integer";
    const ARR = "array";
    const STR = "string";
    const BOO = "boolean";

    protected $signature = "swagger:doc {--folder= : Folder which generated files are saved} {--group= : Main file which urls are saved}";
    protected $description = 'Command description';

    protected string $folder = "apidoc";
    protected string $group = "admin";

    public function handle()
    {
        if ($folder = $this->option('folder')) {
            $this->folder = public_path($folder);
        } else {
            $this->folder = public_path($this->folder);
        }
        if (!file_exists($this->folder)) {
            mkdir($this->folder, 0777, true);
        }

        if ($group = $this->option('group')) {
            $this->group = ($this->folder . '/' . $group . '.json');
        } else {
            $this->group = ($this->folder . '/' . $this->group . '.json');
        }
        if (!file_exists($this->group)) {
            file_put_contents($this->group, file_get_contents(__DIR__ . '/doc/template.json'));
        }
        $this->resolve();
    }

    protected function writeToGroupFile($group)
    {
        $group = $this->folder . '/' . $group . '.json';
        if (!file_exists($group)) {
            file_put_contents($group, file_get_contents(__DIR__ . '/doc/template.json'));
        }
        return $group;
    }

    protected function resolve()
    {
        $namespace = 'App\Http\\Controllers';
        $path = app_path('Http/Controllers');
        $filter = function ($filename) {
            if (! str_starts_with($filename, 'Controller')) {
                return true;
            }
        };
        $controllers = $this->getFiles(path: $path, namespace: $namespace, filter: $filter);
        foreach ($controllers as $controller) {
            $classname = pathinfo($controller, PATHINFO_FILENAME);
            $object = app()->make($classname);
            $reflectionController = new ReflectionClass($classname);
            if (! ($docControllerAttr = $this->isDocController($reflectionController))) {
                // VarDumper::dump($reflectionController);
                continue;
            }
            $reflectionMethods = $reflectionController->getMethods();
            foreach ($reflectionMethods as $reflectionMethod) {
                if (! ($docActionAttr = $this->isDocAction($reflectionMethod))) {
                    // VarDumper::dump($reflectionMethod);
                    continue;
                }
                $request = null;
                $response = new Response();
                /** @var DocActionAttr */
                $docActionAttrObj = $docActionAttr->newInstance();
                $HTTP_METHOD = $this->getHttpMethod($reflectionMethod, $docActionAttrObj);
                $parameters = $reflectionMethod->getParameters();
                $passedParameters = [];
                foreach ($parameters as $parameter) {
                    $parameterName = $parameter->getName();
                    if ($parameterType = $parameter->getType()) {
                        if ($parameterType->isBuiltin()) {
                            $passedParameters[] = $docActionAttrObj->getParameter($parameterName);
                        } else {
                            $parameterTypeName = $parameterType->getName();
                            try {
                                $implements = class_implements($parameterTypeName);
                                if (in_array(\Illuminate\Contracts\Validation\ValidatesWhenResolved::class, $implements)) {
                                    $data = $docActionAttrObj->getTypedParameter($parameterName);
                                    $data['server'] = $_SERVER;
                                    if ($queryparams = $docActionAttrObj->getQueryparams()) {
                                        if (isset($data['query'])) {
                                            $data['query'] = array_merge($data['query'], $queryparams);
                                        } else {
                                            $data['query'] = $queryparams;
                                        }
                                    }
                                    /** @var \Illuminate\Foundation\Http\FormRequest */
                                    $instance = new $parameterTypeName(...$data);
                                    $request = $instance;
                                    try {
                                        $instance->setContainer($this->laravel);
                                        $instance->setRedirector(redirect());
                                        $instance->validateResolved();
                                    } catch (\Illuminate\Validation\ValidationException $th) {
                                        $message = $th->validator->errors()->first();
                                        $errors = $th->validator->errors()->toArray();
                                        $response = new JsonResponse(data: compact('message', 'errors'), status: 422);
                                    } catch (\Illuminate\Http\Exceptions\HttpResponseException $th) {
                                        $response = $th->getResponse();
                                    } catch (\Throwable $th) {
                                        $message = $th->getMessage();
                                        $file = $th->getFile();
                                        $line = $th->getLine();
                                        $response = new JsonResponse(data: compact('message', 'file', 'line'), status: 500);
                                    }
                                } else if (\Illuminate\Http\Request::class == $parameterTypeName) {
                                    $data = $docActionAttrObj->getTypedParameter($parameterName);
                                    $data['server'] = $_SERVER;
                                    if ($queryparams = $docActionAttrObj->getQueryparams()) {
                                        if (isset($data['query'])) {
                                            $data['query'] = array_merge($data['query'], $queryparams);
                                        } else {
                                            $data['query'] = $queryparams;
                                        }
                                    }
                                    $instance = new $parameterTypeName(...$data);
                                    $request = $instance;
                                } else {
                                    $instance = app()->make($parameterTypeName, ...$docActionAttrObj->getTypedParameter($parameterName));
                                }
                            } catch (\Throwable $th) {
                                $instance = null;
                                $message = $th->getMessage();
                                $file = $th->getFile();
                                $line = $th->getLine();
                                $response = new JsonResponse(data: compact('message', 'file', 'line'), status: 500);
                            }
                            $passedParameters[] = $instance;
                        }
                    } else {
                        $passedParameters[] = $docActionAttrObj->getParameter($parameterName);
                    }
                }
                if (! in_array($response->getStatusCode(), [422, 500])) {
                    $response = $reflectionMethod->invoke($object, ...$passedParameters);
                    if ($response instanceof \Illuminate\Contracts\Support\Responsable) {
                        $response = $response->toResponse($request ?? request());
                    } else if (is_array($response)) {
                        $response = new JsonResponse(data: $response);
                    } else if (is_string($response) || is_numeric($response) || is_bool($response) || is_null($response)) {
                        $response = new Response($response);
                    } else if ($response instanceof \Illuminate\Support\Collection) {
                        $response = new JsonResponse(data: $response);
                    }
                }
                $action = $reflectionMethod->getName();
                $fileName = $this->generateFilenameForAction($action);
                $folderName = $this->folderNameForController($reflectionController->getName());
                $endpoint = $docActionAttrObj->getUrl() ?? "/$folderName";
                $group = $docActionAttrObj->getGroup() ? $this->writeToGroupFile($docActionAttrObj->getGroup()) : $this->group;

                $folder = $this->folder . '/' . $folderName;
                if (!file_exists($folder)) {
                    mkdir($folder, 0777, true);
                }
                $actionFilePath = $folder . '/' . $fileName;
                $this->changeGroupFile($group, $endpoint, $folderName, $fileName);
                $this->writeToDocFile($actionFilePath, $request, $response, $docActionAttrObj);
                $this->info($actionFilePath);
            }
        }
    }

    public function writeToDocFile($actionFilePath, $request, $response, $docActionAttrObj)
    {
        $data = json_decode($response->getContent(), true);
        $method = strtolower($docActionAttrObj->getMethod());
        $status = $response->getStatusCode();
        $document = [];
        $docJson = $this->convertValuesToDocFormat($request, $response, $docActionAttrObj);
        if (file_exists($actionFilePath) && $filedata = json_decode(file_get_contents($actionFilePath), true)) {
            foreach ($filedata as $key => $value) {
                if ($key == $method) {
                    $responses = $filedata[$method]['responses'];
                    $responses["$status"] = $docJson['responses']["$status"];
                    $docJson['responses'] = $responses;
                    $document[$method] = $docJson;
                } else {
                    $document[$key] = $value;
                }
            }
            if (!isset($document[$method])) {
                $document[$method] = $docJson;
            }
        } else {
            $document[$method] = $docJson;
        }
        $data = Str::remove('\\', json_encode($document));
        file_put_contents($actionFilePath, $data, JSON_PRETTY_PRINT);
    }

    public function generateFilenameForAction($action)
    {
        $basePathActions = ['index', 'store'];
        $singlePathActions = ['show', 'update', 'destroy'];
        $filename = '';
        if (in_array($action, $basePathActions)) {
            $filename = 'get_list_store';
        } else if (in_array($action, $singlePathActions)) {
            $filename = 'get_one_update_delete';
        } else {
            $filename = $action;
        }
        return $filename . '.json';
    }

    public function changeGroupFile($group, $endpoint, $folderName, $fileName)
    {
        try {
            $baseDocValue = json_decode(file_get_contents($group), true);
        } catch (\Throwable $th) {
            dd($group);
        }
        $endpoint = Str::startsWith($endpoint, '/') ? $endpoint : "/$endpoint";
        if (!isset($baseDocValue['paths'][$endpoint])) {
            $baseDocValue['paths'][$endpoint] = [
                '$ref' => $folderName . '/' . $fileName
            ];
            file_put_contents($group, Str::remove('\\', json_encode($baseDocValue)), JSON_PRETTY_PRINT);
        }
    }

    protected function folderNameForController($controllerName)
    {
        $explodedName = explode('\\', $controllerName);
        $realName = end($explodedName);
        $realName = Str::replace('Controller', '', $realName);
        $realName = Str::snake($realName);
        return $realName;
    }

    protected function getHttpMethod(ReflectionMethod $reflectionMethod, DocActionAttr $docActionAttrObj)
    {
        if ($docActionAttrObj->getMethod()) {
            return $_SERVER['REQUEST_METHOD'] = $docActionAttrObj->getMethod();
        }
        return $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function isDocAction(ReflectionMethod $reflectionMethod)
    {
        if ($attributes = $reflectionMethod->getAttributes(DocActionAttr::class)) {
            return $attributes[0];
        }
    }

    protected function isDocController(ReflectionClass $reflectionController)
    {
        if ($attributes = $reflectionController->getAttributes(DocControllerAttr::class)) {
            return $attributes[0];
        }
    }

    function getFiles($path = null, $namespace = null, $filter = null)
    {
        $items = scandir($path);
        $results = [];
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            if (is_dir("$path/$item")) {
                $dirResults = $this->fileFinder(path: "$path/$item", namespace: ($namespace ? "$namespace\\$item" : $item), filter: $filter);
                $results = array_merge($results, $dirResults);
            } else {
                if ($filter && $filter instanceof \Closure) {
                    if ($filter($item)) {
                        $results[] = $namespace ? "$namespace\\$item" : $item;
                    }
                } else {
                    $results[] = $namespace ? "$namespace\\$item" : $item;
                }
            }
        }
        return $results;
    }

    /**
     * @param Request|null $request
     * @param Response|JsonResponse $response
     * @param DocActionAttr $docActionAttrObj
     */
    protected function convertValuesToDocFormat($request, $response, $docActionAttrObj)
    {
        $body = [];
        // $body['tags'] = getterValue($route, 'tags');
        // $body['description'] = getterValue($route, 'description');
        $body['parameters'] = [];
        if ($urlparams = $docActionAttrObj->getUrlparams()) {
            foreach ($urlparams as $key => $value) {
                $body['parameters'][] = [
                    'name' => $key,
                    'in' => 'url',
                    'required' => false,
                    'schema' => [
                        'type' => $this->getType($value),
                        'example' => $value
                    ]
                ];
            }
        }
        if ($queryparams = $docActionAttrObj->getQueryparams()) {
            foreach ($queryparams as $key => $value) {
                $body['parameters'][] = [
                    'name' => $key,
                    'in' => 'url',
                    'required' => false,
                    'schema' => [
                        'type' => $this->getType($value),
                        'example' => $value
                    ]
                ];
            }
        }
        if (!empty($response->headers->all())) {
            if (!isset($body['parameters'])) {
                $body['parameters'] = [];
            }
            foreach ($response->headers->all() as $key => $value) {
                if ($key === "Authorization") continue;
                $body['parameters'][] = [
                    'name' => $key,
                    'in' => 'header',
                    'required' => false,
                    'schema' => [
                        'type' => $this->getType($value),
                        'example' => $value
                    ]
                ];
            }
        }
        if (!empty($request->request->all())) {
            $contentType = $request->headers->get('CONTENT_TYPE', 'application/json');
            $body['requestBody'] = [
                // 'description' => getterValue($route, 'description'),
                'content' => [
                    $contentType => [
                        'schema' => $this->convertDataToDocFormat($request->request->all())
                    ]
                ]
            ];
        }
        $status = $response->status();
        $statusText = $response->statusText();
        $contentType = $response->headers->get('content-type', '');
        try {
            $data = json_decode($response->getContent(), true);
        } catch (\Throwable $th) {
            $data = [];
        }
        $body['responses'] = [
            "$status" => [
                'description' => "$statusText",
                'content' => [
                    $contentType => [
                        'schema' => $this->convertDataToDocFormat($data)
                    ]
                ]
            ]
        ];
        if ($request->headers->get('Authorization')) {
            $body['security'] = [
                [
                    'bearerAuth' => []
                ]
            ];
        }
        return $body;
    }

    protected function getType($value)
    {
        if ($this->maybeObject($value)) return self::OBJ;
        if (is_numeric($value)) return self::NUM;
        if (is_bool($value)) return self::BOO;
        if (is_string($value)) return self::STR;
        if (is_array($value)) return self::ARR;
        if (is_null($value)) return null;
    }

    protected function convertDataToDocFormat($data)
    {
        switch ($this->getType($data)) {
            case self::OBJ:
                $obj["type"] = self::OBJ;
                $obj['properties'] = [];
                foreach ($data as $key => $value) {
                    $obj['properties'][$key] = $this->convertDataToDocFormat($value);
                }
                return $obj;
                break;
            case self::ARR:
                $arr["type"] = self::ARR;
                $arr['items'] = [];
                if (!empty($data)) {
                    $arr['items'] = $this->convertDataToDocFormat($data[0]);
                } else {
                    $arr['items'] = $this->convertDataToDocFormat(null);
                }
                return $arr;
                break;
            case self::NUM:
                return [
                    "type" => self::NUM,
                    "example" => $data
                ];
                break;
            case self::STR:
                return [
                    "type" => self::STR,
                    "example" => $data
                ];
                break;
            case self::BOO:
                return [
                    "type" => self::BOO,
                    "example" => $data
                ];
                break;
            case null:
                return [
                    "type" => null,
                    "example" => null
                ];
                break;
        }
    }

    protected function maybeObject($array)
    {
        if (!is_array($array)) return false;
        foreach ($array as $key => $value) {
            if (is_string($key)) return true;
        }
        return false;
    }
}
