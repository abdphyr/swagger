<?php

namespace Abdphyr\Swagger\Console\Commands;

use Abdphyr\Swagger\Attributes\Http\ActionController;
use Abdphyr\Swagger\Attributes\Http\ActionMethod;
use Abdphyr\Swagger\Console\Exceptions\ConsoleCommandException;
use Abdphyr\Swagger\Console\Helpers\RunAction;
use Abdphyr\Swagger\Traits\ConsoleColorWrapping;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    protected $signature = 'generate:swagger 
        {page?}
        {--controller= : Controller name} 
        {--c= : Controller name} 
        {--method= : Method name} 
        {--m= : Method name} 
        {--rm : Removes controller\'s action} 
        {--clear : Clear cache}
        {--force : Overrides existing actions}
        {--route= : Route parameter} 
        {--request= : Request body parameter} 
        {--query= : Query parameter}';

    protected $description = 'Generates API data openAPI format and save cache';

    protected string $page;

    protected function validatePageArgument()
    {
        $pages = array_keys(config('swagger'));
        $page = $this->argument('page');
        if (! $page) {
            $this->info($this->white('Please enter page name! Exist pages: ') . $this->yellow(implode(',', $pages)));
            exit(self::FAILURE);
        } else if (! in_array($page, $pages)) {
            $this->info($this->white($this->red($page) . ' page is not found! Exist pages: ') . $this->yellow(implode(',', $pages)));
            exit(self::FAILURE);
        }
        $this->page = $this->argument('page');
    }

    public function getPages(ActionController $actionControllerAttr, ActionMethod $actionMethodAttr, $controllerName)
    {
        $pages = array_merge($actionControllerAttr->pages, $actionMethodAttr->pages);
        $configPages = array_keys(config('swagger'));
        foreach ($pages as $page) {
            if (! in_array($page, $configPages)) {
                $this->info($this->white("<fg=yellow>$page</> page is not found. See <fg=cyan>$controllerName</> and " . $this->cyan(config_path('swagger.php'))));
                exit(self::FAILURE);
            }
        }
        return $pages;
    }

    protected function getActions()
    {
        $actions = [];
        $controllers = $this->getControllersList($con = ($this->option('controller') ?? $this->option('c')));
        if (empty($controllers)) {
            $this->info($this->white('Controller ' . $this->yellow($con) . ' not found!'));
            exit(self::FAILURE);
        }
        foreach ($controllers as $controller) {
            $reflectionController = new \ReflectionClass($controller);
            if ($reflectionController->isAbstract()) continue;
            if (!($attrs = $reflectionController->getAttributes(ActionController::class))) {
                $this->info("<fg=red>Add attribute <fg=yellow>#[ActionController(uri: '/absolute/endpoint')]</></> to " . $reflectionController->getName());
                exit(self::FAILURE);
            }
            $actionControllerAttr = $attrs[0]->newInstance();

            if ($con && $met = ($this->option('method') ?? $this->option('m'))) {
                if (! $reflectionController->hasMethod($met)) {
                    $this->info($this->white('Target method ') . $this->yellow($reflectionController->getName() . "::<fg=red>$met()") . $this->white(' is not found!'));
                    exit(self::FAILURE);
                }
                $reflectionMethod = $reflectionController->getMethod($met);
                if (! ($attr = $reflectionMethod->getAttributes(ActionMethod::class))) {
                    $this->info("<fg=white>None action method. Add attribute like <fg=yellow>#[ActionMethod(uri: 'relative/endpoint', method: 'Get')]</></>");
                    exit(self::FAILURE);
                }
                $actionMethodAttr = $attr[0]->newInstance();
                if (in_array($this->page, $this->getPages($actionControllerAttr, $actionMethodAttr, $reflectionController->getName()))) {
                    $actions[] = ['controller' => $reflectionController->getName(), 'method' => $reflectionMethod->getName()];
                }
                return $actions;
            }

            $publicMethods = $reflectionController->getMethods(\ReflectionMethod::IS_PUBLIC);
            $methods = array_filter($publicMethods, function ($method) use ($actionControllerAttr, $reflectionController) {
                if ($attrs = $method->getAttributes(ActionMethod::class)) {
                    if (in_array($this->page, $this->getPages($actionControllerAttr, $attrs[0]->newInstance(), $reflectionController->getName()))) return true;
                    else return false;
                }
            });
            usort($methods, function($m1, $m2) {
                $a1 = $m1->getAttributes(ActionMethod::class)[0]->newInstance();
                $a2 = $m2->getAttributes(ActionMethod::class)[0]->newInstance();
                return $a2->ord < $a1->ord;
            });

            foreach ($methods as $key => $method) {
                $actions[] = ['controller' => $reflectionController->getName(), 'method' => $method->getName()];
            }
        }
        return $actions;
    }

    protected function getControllersList($search = '')
    {
        $namespace = "App\\Http\\Controllers";
        $path = app_path('Http/Controllers');
        return $this->getFiles($path, $namespace, '', $search);
    }

    protected function prepareDBConnection()
    {
        DB::setDefaultConnection('swagger');
    }

    public function handle()
    {
        $this->prepareDBConnection();
        $this->validatePageArgument();
        if ($this->option('clear')) {
            $this->clearCacheData();
            $this->info('Data is cleared successfully !');
            return;
        }
        $cache = $this->getCacheData();
        $actions = $this->getActions();
        if (empty($actions)) {
            $this->info('No action');
        }
        foreach ($actions as $action) {
            try {
                $actionResult = new RunAction(
                    controller: $action['controller'],
                    method: $action['method'],
                    optionRoute: $this->option('route'),
                    optionRequest: $this->option('request'),
                    optionQuery: $this->option('query')
                );

                $uri = $actionResult->getUri();
                $httpMethod = $actionResult->getHttpMethod();
                $httpStatus = $actionResult->getHttpStatus();

                if ($this->option('rm')) {
                    $this->remove($cache, $uri, $httpMethod);
                    continue;
                }

                if (isset($cache[$uri])) {
                    if (isset($cache[$uri][$httpMethod])) {
                        if ($this->option('force')) {
                            $responses = $cache[$uri][$httpMethod]['responses'];
                            $cache[$uri][$httpMethod] = $this->resolveResponse($actionResult);
                            $responses[$httpStatus] = $cache[$uri][$httpMethod]['responses'][$httpStatus];
                            $cache[$uri][$httpMethod]['responses'] = $responses;
                            $this->info($this->white($this->page) . ' ' . $this->cyan(strtoupper($httpMethod)) . ' -> ' . $this->yellow($uri) . $this->green(' updated!'));
                        }
                    } else {
                        $cache[$uri][$httpMethod] = $this->resolveResponse($actionResult);
                        $this->info($this->white($this->page) . ' ' . $this->cyan(strtoupper($httpMethod)) . ' -> ' . $this->yellow($uri) . $this->green(' generated!'));
                    }
                } else {
                    $cache[$uri] = [$httpMethod => $this->resolveResponse($actionResult)];
                    $this->info($this->white($this->page) . ' ' . $this->cyan(strtoupper($httpMethod)) . ' -> ' . $this->yellow($uri) . $this->green(' generated!'));
                }
            } catch (ConsoleCommandException $th) {
                $th->output($this);
            }
        }
        $this->setCacheData($cache);
    }

    protected function remove(&$cache, $uri, $httpMethod)
    {
        if (isset($cache[$uri])) {
            if (isset($cache[$uri][$httpMethod])) {
                $this->info($this->white($this->page) . ' ' . $this->cyan(strtoupper($httpMethod)) . ' -> ' . $this->yellow($uri) . $this->red(' removed!'));
                unset($cache[$uri][$httpMethod]);
            } else unset($cache[$uri]);
        }
    }

    protected function getCacheData()
    {
        if (! is_dir(base_path("bootstrap/cache/swagger"))) {
            mkdir(base_path("bootstrap/cache/swagger"));
        }
        $path = base_path("bootstrap/cache/swagger/$this->page.php");
        if (!file_exists($path)) {
            file_put_contents($path, "<?php\nreturn " . var_export($data ?? [], true) . ";");
        }
        $data = require_once $path;
        return is_array($data) ? $data : [];
    }

    protected function setCacheData($cache = [])
    {
        $path = base_path("bootstrap/cache/swagger/$this->page.php");
        file_put_contents($path, "<?php\nreturn " . var_export($cache ?? [], true) . ";");
        return $cache;
    }

    protected function clearCacheData($data = [])
    {
        $path = base_path("bootstrap/cache/swagger/$this->page.php");
        file_put_contents($path, "<?php\nreturn " . var_export([], true) . ";");
        return $data;
    }

    protected function getFiles($path, $namespace, $subnamespace, $search = '')
    {
        $result = [];
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            if (is_dir("$path/$file")) {
                $sub = $this->getFiles("$path/$file", "$namespace\\$file", ($subnamespace ? "$subnamespace\\$file" : $file), $search);
                $result = [...$result, ...$sub];
            } else {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if ($search) {
                    if ($search == ($subnamespace ? "$subnamespace\\$name" : $name)) {
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
        if ($actionMethodAttr->auth) $result['security'] = [['bearerAuth' => []]];
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
