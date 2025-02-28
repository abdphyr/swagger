<?php

namespace Abdphyr\Swagger\Console\Helpers;

use Abdphyr\Swagger\Attributes\DTO\QueryParam;
use Abdphyr\Swagger\Attributes\DTO\FileParam;
use Abdphyr\Swagger\Attributes\DTO\RequestParam;
use Abdphyr\Swagger\Attributes\Http\ActionMethod;
use Abdphyr\Swagger\Console\Exceptions\ConsoleCommandException;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\Validation\ValidatesWhenResolved;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Collection;

class RunAction
{
    public ActionMethod $actionMethodAttr;
    protected Request|FormRequest $request;
    protected JsonResponse|Response $response;
    protected \ReflectionMethod $reflectionMethod;

    public function __construct(
        public string $controller,
        public string $method,
        public ?string $optionQuery = '',
        public ?string $optionRoute = '',
        public ?string $optionRequest = '',
    ) {
        $this->setReflectionMethod();
        $this->setActionMethodAttrInstance();
        $this->setInitialRequestInstance();
        $this->setDescription();
        $this->setSummary();
        $this->setTags();
        $this->run();
    }

    public function run()
    {
        try {
            $reflectionMethodParameteres = $this->reflectionMethod->getParameters();
            $methodParameters = [];
            foreach ($reflectionMethodParameteres as $parameter) {
                $parameterName = $parameter->getName();
                if ($parameterType = $parameter->getType()) {
                    if ($parameterType->isBuiltin()) {
                        $this->resolveSimpleParameter($methodParameters, $parameterName);
                    } else {
                        $this->resolveComplicatedParameter($methodParameters, $parameter);
                    }
                } else $this->resolveSimpleParameter($methodParameters, $parameterName);
            }

            $result = $this->reflectionMethod->invoke(app()->make($this->controller), ...$methodParameters);

            if ($result instanceof SymfonyResponse) {
                $this->response = $result;
            } else if ($result instanceof Responsable) {
                $this->response = $result->toResponse(request());
            } else if (is_array($result)) {
                $this->response = new JsonResponse(data: $result);
            } else if ($result instanceof Collection) {
                $this->response = new JsonResponse(data: $result);
            } else {
                $this->response = new Response($result);
            }
        } catch (\Illuminate\Validation\ValidationException $th) {
            $message = $th->validator->errors()->first();
            $errors = $th->validator->errors()->toArray();
            $this->response = new JsonResponse(data: compact('message', 'errors'), status: 422);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $th) {
            $this->response = new Response($th->getResponse()->getContent(), 422);
        } catch (\Throwable $th) {
            $this->response = new JsonResponse(["message" => $th->getMessage() . ". File: " . $th->getFile() . ":" . $th->getLine()], 500);
        }
        $this->setResponse($this->response);
    }

    protected function resolveSimpleParameter(&$methodParameters, $parameterName)
    {
        if (isset($this->actionMethodAttr->route[$parameterName])) {
            $methodParameters[] = $this->actionMethodAttr->route[$parameterName];
        } else if (isset($this->actionMethodAttr->query[$parameterName])) {
            $methodParameters[] = $this->actionMethodAttr->query[$parameterName];
        } else throw new ConsoleCommandException('warn', "Url param <fg=red>\"$parameterName\"</> is not provided with <fg=green>#[ActionMethod]</> attribute to action");
    }

    protected function resolveComplicatedParameter(&$methodParameters, \ReflectionParameter $requestParameter)
    {
        $class = $requestParameter->getType()->getName();
        $implements = class_implements($class);
        if (in_array(ValidatesWhenResolved::class, $implements)) {
            $formRequest = $class::createFrom($this->request);
            $this->prepareFormRequest($formRequest, $class);
            $this->setRequest($formRequest);
            $formRequest->setContainer(app());
            $formRequest->setRedirector(redirect());
            $formRequest->validateResolved();
            foreach ($formRequest->validated() as $key => $value) {
                $formRequest->{$key} = $value;
            }
            $this->setRequest($formRequest);
            $methodParameters[] = $formRequest;
        } else if ($class == \Illuminate\Http\Request::class) {
            $methodParameters[] = $this->request;
        } else {
            $methodParameters[] = app()->make($class);
        }
    }

    /** 
     * @param \Illuminate\Foundation\Http\FormRequest $formRequest 
     */
    public function prepareFormRequest(&$formRequest, $formRequestClass): void
    {
        $formRequestClassReflection = new \ReflectionClass($formRequestClass);
        $properties = $formRequestClassReflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        /** ALL REQUEST PARAMS */
        $requestAtrributes = $formRequestClassReflection->getAttributes(RequestParam::class);
        foreach ($requestAtrributes as $reqAttr) {
            $attr = $reqAttr->newInstance();
            if ($attr->key) $this->actionMethodAttr->request[$attr->key] = $attr->value; 
        }
        $requestProperties = array_filter($properties, fn($property) => $property->getAttributes(RequestParam::class));
        foreach ($requestProperties as $property) {
            $param = $property->getAttributes(RequestParam::class)[0];
            $this->actionMethodAttr->request[$property->getName()] = $param->newInstance()->value;
        }
        $this->setRequestParamsFromOption();

        /** ALL QUERY PARAMS */
        $queryAtrributes = $formRequestClassReflection->getAttributes(QueryParam::class);
        foreach ($queryAtrributes as $queryAttr) {
            $attr = $queryAttr->newInstance();
            if ($attr->key) $this->actionMethodAttr->query[$attr->key] = $attr->value; 
        }
        $queryProperties = array_filter($properties, fn($property) => $property->getAttributes(QueryParam::class));
        foreach ($queryProperties as $property) {
            $param = $property->getAttributes(QueryParam::class)[0];
            $this->actionMethodAttr->query[$property->getName()] = $param->newInstance()->value;
        }
        $this->setQueryParamsFromOption();

        /** ALL FILES */
        $fileAtrributes = $formRequestClassReflection->getAttributes(FileParam::class);
        foreach ($fileAtrributes as $fileAttr) {
            $attr = $fileAttr->newInstance();
            if ($attr->key) $this->actionMethodAttr->files[$attr->key] = $attr->value; 
        }
        $fileProperties = array_filter($properties, fn($property) => $property->getAttributes(FileParam::class));
        foreach ($fileProperties as $property) {
            $param = $property->getAttributes(FileParam::class)[0];
            $this->actionMethodAttr->files[$property->getName()] = $param->newInstance()->value;
        }

        $formRequest->query->add($this->actionMethodAttr->query);
        $formRequest->request->add($this->actionMethodAttr->request);
        $formRequest->files->add($this->actionMethodAttr->files);
    }

    protected function setReflectionMethod()
    {
        if (! class_exists($this->controller)) {
            throw new ConsoleCommandException('warn', "Target controller <fg=red>$this->controller</> is not found!");
        }
        if (! method_exists($this->controller, $this->method)) {
            throw new ConsoleCommandException('warn', "Target method $this->controller::<fg=red>$this->method()</> is not found!");
        }
        $this->reflectionMethod = new \ReflectionMethod($this->controller, $this->method);
    }

    protected function setActionMethodAttrInstance()
    {
        $actionMethod = $this->reflectionMethod->getAttributes(ActionMethod::class);
        if (! $actionMethod) {
            throw new ConsoleCommandException('info', "<fg=red>None action method. Add attribute like <fg=yellow>#[ActionMethod(uri: '/api/endpoint', method: 'Get')]</></>");
        }
        $this->actionMethodAttr = $actionMethod[0]->newInstance();
        $this->setRouteParamsFromOption();
        $this->setRequestParamsFromOption();
        $this->setQueryParamsFromOption();
    }

    protected function setInitialRequestInstance()
    {
        $request = Request::create(
            uri: $this->actionMethodAttr->uri,
            method: $this->actionMethodAttr->method,
            server: array_merge(['HTTP_ACCEPT' => 'application/json'], $this->actionMethodAttr->server),
            content: $this->actionMethodAttr->content
        );
        $request->query->add($this->actionMethodAttr->query);
        $request->request->add($this->actionMethodAttr->request);
        $request->attributes->add($this->actionMethodAttr->attributes);
        $request->cookies->add($this->actionMethodAttr->cookies);
        $request->files->add($this->actionMethodAttr->files);
        $this->setRequest($request);
    }

    protected function setTags()
    {
        $tags = $this->actionMethodAttr->tags;
        $array = explode('\\', $this->controller);
        $controller = strtolower(str_replace('Controller', '', array_pop($array)));
        $tags = array_merge($tags, [$controller]);
        $this->actionMethodAttr->tags = $tags;
    }

    protected function setDescription()
    {
        if (!$this->actionMethodAttr->description) {
            $array = explode('\\', $this->controller);
            $controller = str_replace('Controller', '', array_pop($array));
            $this->actionMethodAttr->description = $controller . ' ' . $this->method;
        }
    }

    protected function setSummary()
    {
        if (!$this->actionMethodAttr->summary) {
            $array = explode('\\', $this->controller);
            $controller = str_replace('Controller', '', array_pop($array));
            $this->actionMethodAttr->summary = $controller . ' ' . $this->method;
        }
    }

    protected function setQueryParamsFromOption()
    {
        if ($queryParams = $this->optionQuery) {
            $queryParamsArray = explode(',', trim($queryParams));
            foreach ($queryParamsArray as $qp) {
                $key_value = explode(':', $qp);
                if (isset($key_value[0]) && isset($key_value[1])) {
                    $this->actionMethodAttr->query[$key_value[0]] = $key_value[1];
                }
            }
        }
    }

    protected function setRouteParamsFromOption()
    {
        if ($routeParams = $this->optionRoute) {
            $routeParamsArray = explode(',', trim($routeParams));
            foreach ($routeParamsArray as $rp) {
                $key_value = explode(':', $rp);
                if (isset($key_value[0]) && isset($key_value[1])) {
                    $this->actionMethodAttr->route[$key_value[0]] = $key_value[1];
                }
            }
        }
    }

    protected function setRequestParamsFromOption()
    {
        if ($requestParams = $this->optionRequest) {
            $requestParamsArray = explode(',', trim($requestParams));
            foreach ($requestParamsArray as $rp) {
                $key_value = explode(':', $rp);
                if (isset($key_value[0]) && isset($key_value[1])) {
                    $this->actionMethodAttr->request[$key_value[0]] = $key_value[1];
                }
            }
        }
    }

    public function getRequest(): Request|FormRequest
    {
        return $this->request;
    }

    public function setRequest(Request|FormRequest $request): void
    {
        app()->instance('request', $request);
        $this->request = $request;
    }

    public function getResponse(): JsonResponse|Response
    {
        return $this->response;
    }

    public function setResponse(JsonResponse|Response $response): void
    {
        app()->instance('response', $response);
        $this->response = $response;
    }

    public function requestBody()
    {
        return $this->actionMethodAttr->request;
    }

    public function queryParams()
    {
        return $this->actionMethodAttr->query;
    }

    public function httpMethod()
    {
        return $this->actionMethodAttr->method;
    }

    public function getActionMethodAttrInstance()
    {
        return $this->actionMethodAttr;
    }

    public function getUrl()
    {
        $url = 'api' . $this->actionMethodAttr->uri;
        foreach ($this->actionMethodAttr->route as $key => $value) {
            $url = str_replace("{{$key}}", $value, $url);
        }
        if ($this->actionMethodAttr->query) {
            if (str_contains($url, '?')) {
                $url = $url . '&' . http_build_query($this->actionMethodAttr->query);
            } else {
                $url = $url . '?' . http_build_query($this->actionMethodAttr->query);
            }
        }
        return $url;
    }
}
