# AI generation

[Documentation home](README.md) · [Prompts and replacers](prompts-and-replacers.md)

Generate text or schema-shaped data inside a task. The helpers use the flow's system instructions and the consuming application's Laravel AI defaults. Calls run inside the queued task, so allow enough worker time for generation.

## Text

```php
$text = $this->generateText('Introduce our stationery catalog.', usePlan: true);
$this->setStatus(true, ['introduction' => $text]);
```

The result is a string. Plans are excluded by default for text; opt in with `usePlan: true`. Calls do not share conversation memory automatically. Supply earlier output explicitly when it is needed.

## Structured output

Use structured output when your application needs fields or records instead of prose to parse:

```php
$product = $this->generateStructured(
    schema: [
        'type' => 'object',
        'required' => ['title', 'summary'],
        'properties' => [
            'title' => ['type' => 'string'],
            'summary' => ['type' => 'string'],
        ],
    ],
    prompt: 'Describe a recycled-paper notebook.',
);

$this->setStatus(true, $product);
```

The package supplies a strict structured-output agent and returns a PHP array. Structured helpers include the step and task plans by default. Pass `usePlan: false` to omit those plans.

Use a provider and model supporting structured output. A schema defines the requested shape; validate business requirements and generated content before saving or publishing it.

## Schema files and lists

For a reusable schema, create `products.schema.json` beside your task:

```json
{
    "type": "array",
    "minItems": 1,
    "maxItems": 3,
    "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["title"],
        "properties": {
            "title": {
                "type": "string",
                "description": "A product title in {{ language | English }}"
            }
        }
    }
}
```

Then call it inside `handle()`:

```php
$products = $this->generateStructuredFromFile(
    __DIR__.'/products.schema.json',
    prompt: 'Generate stationery products.',
);

$this->setStatus(true, ['products' => $products]);
```

An array-root schema returns the list directly, such as `[['title' => 'Notebook']]`. An object-root schema returns an associative array. You do not need to unwrap a provider response envelope.

Use object or array roots for these array-returning helpers. Object schemas need properties. Schema string values receive parameter replacement automatically; [replacer rules](prompts-and-replacers.md) explain the details. An unexpected non-structured response returns `[]`; check required output before accepting it as a successful business result.

## Choosing a provider or model for one call

The convenience helpers use application defaults. For a task-specific override, use the agent directly:

```php
$response = $this->agent()->textAgent()->prompt(
    'Write a catalog introduction.',
    provider: 'openai',
    model: $this->param('model'),
);

$this->setStatus(true, ['text' => $response->text]);
```

Configure the provider in your application and supply a supported model. Direct agent calls do not automatically prepend step/task plans.

## Images and other work

Tasks can also call Laravel AI directly for images:

```php
use Laravel\Ai\Image;

public function handle(): void
{
    $path = Image::of('A clean stationery product photograph')
        ->generate()
        ->store();

    $this->setStatus(true, ['image_path' => $path]);
}
```

There is no separate package image helper. Store an asset path or record ID in the result for later tasks. Tasks can also perform ordinary application work without making an AI call.
