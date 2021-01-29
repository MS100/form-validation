<?php

namespace Ms100\FormValidation;


class FormException extends \Exception
{
    protected array $errors = [];

    public function __construct(array $errors = [])
    {
        parent::__construct('invalid form data', 1);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}