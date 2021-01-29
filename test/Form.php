<?php

require dirname(__DIR__).'/vendor/autoload.php';

$rules = [
    [
        'field' => 'id',
        'label' => 'ID',
        'rules' => 'required|is_array',
    ],
    [
        'field' => 'id[]',
        //此处的中括号表示匹配id下的每一个数组元素，rules会循环作用于每一个元素
        'label' => 'ID',
        'rules' => 'required',
        //这里如果没写 is_类型 函数，则默认为is_string，这里的 required，只用来限制元素值不能是空字符串
    ],
];
$obj = new \Ms100\FormValidation\FormValidation([],'zh');

$obj->setRules($rules);
$data = ['id' => ['a', 'b', 'c']]; //通过
try {
    var_dump($obj->verify($data));
} catch (\Ms100\FormValidation\FormException $e) {
    var_dump($e->getErrors());
}

$data = ['id' => ['a' => 'a', 'b' => 'b', 'c' => 'c']]; //通过
try {
    var_dump($obj->verify($data));
} catch (\Ms100\FormValidation\FormException $e) {
    var_dump($e->getErrors());
}

$data = ['id' => [1, 2, 3]]; //不能通过验证，因为 id 的元素必须是字符串
try {
    var_dump($obj->verify($data));
} catch (\Ms100\FormValidation\FormException $e) {
    var_dump($e->getErrors());
}

$data = ['id' => ['', 'a', 'b']]; //不能通过验证，因为 id 的元素不能是空字符串
try {
    var_dump($obj->verify($data));
} catch (\Ms100\FormValidation\FormException $e) {
    var_dump($e->getErrors());
}

//不能通过验证，因为 id 的 required 限制它不能为空数组，将 required 换成 isset 则可以通过验证
$data = ['id' => []];
try {
    var_dump($obj->verify($data));
} catch (\Ms100\FormValidation\FormException $e) {
    var_dump($e->getErrors());
}

$data = []; //不能通过验证，id 字段必填
try {
    var_dump($obj->verify($data));
} catch (\Ms100\FormValidation\FormException $e) {
    var_dump($e->getErrors());
}