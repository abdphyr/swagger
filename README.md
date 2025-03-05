### If there is any question or issue, please contact with [developer](https://t.me/abd_ceo).


# Installation

```sh
composer require abdphyr/swagger
```
```sh
php artisan vendor:publish --provider="Abdphyr\Swagger\SwaggerServiceProvider"
```
Publish command copies "swagger.php" to config folder. This file contains multiple page configurations.
```php
// pages
return [
    'default' => [
        ...,
        'document' => [...] // This properties matches fully `OPEN API`s configuration.
    ],
    'admin' => [...],
    'otherpage' => [...]
];
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
use Abdphyr\Swagger\Attributes\Http\ActionController;
use App\Services\BookService;

// Absolute path: http://localhost:8000/api/book
// This controller API data visible `default` page
#[ActionController(uri: 'book', pages: ['defaut'])]
class BookController extends Controller
{
    public function __construct(protected BookService $service) {}

    // Path: http://localhost:8000/api/book?name=Rich...
    // "auth: false" means this endpoint don't require authentication.
    // "query: ['name' => 'Rich..']" for query params
    #[ActionMethod(uri: '', method: 'GET', query: ['name' => 'Rich..'], auth: false)]
    public function index()
    {
        return $this->service->index();
    }

    // Path: http://localhost:8000/api/book/{id}
    // This API on both `default` and `admin` pages because of "pages: ['admin']"
    #[ActionMethod(uri: '{id}', method: 'GET', route: ['id' => 1], pages: ['admin'])]
    public function show($id)
    {
        return $this->service->show($id);
    }

    // Path: http://localhost:8000/api/book
    #[ActionMethod(uri: '', method: 'POST', summary: 'Creating Book')]
    public function store(BookUpsertRequest $request)
    {
        return $this->service->create($request->validated());
    }

    // Path: http://localhost:8000/api/book-top-all
    // Absolute path is not used beacuse of `uri` starting with `/` that marks this `uri` is `ABSOLUTE PATH`
    #[ActionMethod(uri: '/book-top-all', method: 'GET', , summary: 'Top books')]
    public function topBooks()
    {
        return $this->service->topBooks();
    }
}
```

## We can generate API document for each "page" respectively. For example: 
```sh
php artisan generate:swagger default
```

## If we want to generate API document page's certain [controller]():
```sh
php artisan generate:swagger default --c=ExampleController
php artisan generate:swagger default --controller=ExampleController
```
## If we want to generate API document page's certain [controller's action]():
```sh
php artisan generate:swagger default --c=ExampleController --method=show
php artisan generate:swagger default --controller=ExampleController --m=show
```
## If we want to [remove]() API document page's certain [controler] or [controller's action]():

```sh
php artisan generate:swagger default --rm --c=ExampleController
php artisan generate:swagger default --rm --controller=ExampleController --m=show
```

## If we want to [clear]() page document:

```sh
php artisan generate:swagger default --clear
```

# [Config key]() and [page]() and [url]() matches. 

#### Config
```php
// config/swagger.php
return [
    // page name
    'default' => [...]
];

// PAGE NAME: `default`
// COMMAND: php artisan generate:swagger `default`
// UI: http://localhost:8000/swagger/default-ui
// DATA: http://localhost:8000/swagger/default-data
```

# [UI](http://localhost:8000/swagger/default-ui) 
# [DATA](http://localhost:8000/swagger/default-data)



