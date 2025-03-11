<?php

namespace Abdphyr\Swagger\Console\Commands;

use Abdphyr\Swagger\Console\Exceptions\ConsoleCommandException;
use Abdphyr\Swagger\Console\Helpers\RunAction;
use Abdphyr\Swagger\Traits\ConsoleColorWrapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    protected function prepareDBConnection()
    {
        DB::setDefaultConnection('swagger');
    }

    public function handle()
    {
        try {
            $this->prepareDBConnection();
            $controller = $this->namespace . $this->argument('controller');
            $method = $this->argument('method');
            $action = new RunAction(
                controller: $controller,
                method: $method,
                optionRoute: $this->option('route'),
                optionRequest: $this->option('request'),
                optionQuery: $this->option('query')
            );
            $response = $action->getResponse();
            $content = json_decode($response->getContent(), true);
            $data = json_encode($content, JSON_PRETTY_PRINT);
            $this->outputInfo('URL: ' . $this->white($action->getResolvedUri()), $response);
            $this->outputInfo('HttpMethod: ' . $this->blue($action->getHttpMethod()), $response);
            $this->outputInfo('HttpStatus: ' . $this->blue($action->getHttpStatus()), $response);
            $this->outputInfo('HttpResponse: ' . $this->yellow($data), $response);
            $this->outputInfo('RequestBody: ' . $this->white(json_encode($action->actionMethodAttr->request, JSON_PRETTY_PRINT)), $response);
            $this->outputInfo('RequestParams: ' . $this->white(json_encode($action->actionMethodAttr->query, JSON_PRETTY_PRINT)), $response);
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
