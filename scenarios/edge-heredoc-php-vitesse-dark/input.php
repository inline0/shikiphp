<?php

$name = "World";
$items = ['a', 'b', 'c'];

$html = <<<HTML
<div class="greeting">
    <h1>Hello, {$name}!</h1>
    <ul>
        <li>Count: {${'name'}}</li>
    </ul>
</div>
HTML;

$raw = <<<'NOWDOC'
This is a nowdoc, $name is NOT interpolated.
Literal backslash \n stays.
NOWDOC;

echo $html . PHP_EOL . $raw;
