### If there is any question or issue, please contact with [developer](https://t.me/abd_ceo).


# Installation

```sh
composer require abdphyr/swagger
```
```sh
php artisan vendor:publish --provider="Abdphyr\Swagger\SwaggerServiceProvider"
```
```sh
npm i swagger-ui
```

```sh
npm run build
```

# Using

#### All controllers
```sh
php artisan generate:swagger
```

#### Spesific controller
```sh
php artisan generate:swagger BookController
```

# Debugging certain controller's action 

```sh
php artisan debug:action BookController show --route=id:2
```

```sh
php artisan debug:action BookController store --request=name:Book,series:DEIS
```

# Note http://localhost:8000/swagger-document give you API data #openAPI# format. You can use it in your custom swagger ui page. Default http://localhost:8000/swagger-ui 


### Vite config and swagger.blade.php
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```
```html
<!DOCTYPE html>
<html lang="en">

<head>
    <title>API Documentation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div id="swagger"></div>
</body>

</html>

```

# Using swagger-ui in swagger.js 
```js
import SwaggerUI from 'swagger-ui';
import 'swagger-ui/dist/swagger-ui.css';

// IMPORT THIS IN "app.js" file
// Install "npm i swagger-ui"
// Build "npm run build"

SwaggerUI({
    dom_id: "#swagger", // HTML ROOT ELEMENT ID in the "swagger.blade.php" in views section 
    url: "/swagger-document" //DON'T CHANGE THIS. To see this api endpoint run "php artisan route:list" or open "http://localhost:8000/swagger-document"
})
```

## Request class

```php
namespace App\Http\Requests;

use Abdphyr\Swagger\Attributes\DTO\FileParam;
use Abdphyr\Swagger\Attributes\DTO\RequestParam;
use Illuminate\Http\UploadedFile;

#[RequestParam(key: 'name', value: 'Rich dad and poor dad')]
#[RequestParam(key: 'year', value: '1997')]
#[RequestParam(key: 'series', value: 'DWAR')]
#[FileParam(key: 'file', value: new UploadedFile('path/to/file'))]
class BookUpsertRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'year' => ['required', 'string'],
            'series' => ['required', 'string'],
            'file' => ['nullable']
        ];
    }
}

```

## Controller

```php
namespace App\Http\Controllers;

use Abdphyr\Swagger\Attributes\Http\ActionMethod;
use App\Services\BookService;

class BookController extends Controller
{
    public function __construct(protected BookService $service) {}

    #[ActionMethod(uri: '/book', method: 'GET', query: ['name' => 'Rich..'])]
    public function index()
    {
        return $this->service->index();
    }

    #[ActionMethod(uri: '/book/{id}', method: 'GET', route: ['id' => 1])]
    public function show($id)
    {
        return $this->service->show($id);
    }

    #[ActionMethod(uri: '/book', method: 'POST', summary: 'Creating Book')]
    public function store(BookUpsertRequest $request)
    {
        return $this->service->create($request->validated());
    }
}
```


