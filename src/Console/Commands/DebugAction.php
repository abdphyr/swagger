<?php

namespace Abdphyr\Swagger\Console\Commands;

use Abdphyr\Swagger\Console\Exceptions\ConsoleCommandException;
use Abdphyr\Swagger\Console\Helpers\RunAction;
use Abdphyr\Swagger\Traits\ConsoleColorWrapping;
use Illuminate\Console\Command;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DebugAction extends Command
{
    use ConsoleColorWrapping;

    protected $signature = 'debug:action {controller} {method} 
    {--route= : Route parameter} 
    {--request= : Request body parameter} 
    {--query= : Query parameter}';

    protected $description = "Debugging ceration controller's action";

    protected $namespace = 'App\\Http\\Controllers\\';

    public function handle()
    {
        try {
            $controller = $this->namespace . $this->argument('controller');
            $method = $this->argument('method');
            $action = new RunAction($controller, $method, $this->option('query'), $this->option('route'), $this->option('request'));
            $response = $action->getResponse();
            $content = json_decode($response->getContent(), true);
            $data = json_encode($content, JSON_PRETTY_PRINT);
            $this->outputInfo('URL: ' . $this->white($action->getUrl()), $response);
            $this->outputInfo('HttpMethod: ' . $this->blue($action->httpMethod()), $response);
            $this->outputInfo('HttpStatus: ' . $this->blue($response->getStatusCode()), $response);
            $this->outputInfo('HttpResponse: ' . $this->yellow($data), $response);
            $this->outputInfo('RequestBody: ' . $this->white(json_encode($action->requestBody(), JSON_PRETTY_PRINT)), $response);
            $this->outputInfo('RequestParams: ' . $this->white(json_encode($action->queryParams(), JSON_PRETTY_PRINT)), $response);
        } catch (ConsoleCommandException $th) {
            $th->output($this);
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
        }
    }

    protected function outputInfo($text, SymfonyResponse $response)
    {
        $this->info($response->isSuccessful() ? $this->green($text) : $this->red($text));
    }
}
