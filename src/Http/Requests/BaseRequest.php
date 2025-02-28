<?php

namespace Abdphyr\Swagger\Http\Requests;

use Abdphyr\Swagger\Attributes\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    protected function passedValidation()
    {
        foreach ($this->validated() as $key => $value) {
            $this->{$key} = $value;
        }
    }

    protected function failedValidation(Validator $validator)
    {
        $error = $validator->errors()->first();
        throw new HttpResponseException(response()->json(['message' => $error, 'errors' => $validator->errors()->toArray()], 422));
    }

    public function rules()
    {
        $requestClass = new \ReflectionClass(static::class);
        $publicProperties = $requestClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        $properties = array_filter($publicProperties, fn($property) => $property->getAttributes(Rule::class));
        $rules = [];
        foreach ($properties as $property) {
            $param = $property->getAttributes(Rule::class)[0];
            $rules[$property->getName()] = $param->newInstance()->rule;
        }
        return $rules;
    }
}
