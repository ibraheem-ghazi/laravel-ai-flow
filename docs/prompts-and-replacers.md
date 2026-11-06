# Prompts and variable replacers

[Documentation home](README.md) · Next: [AI generation](ai-generation.md)

Separate shared instructions from task-specific plans so a flow stays consistent while each task has a clear purpose.

## Prompt layers

| Layer | Define it on | Purpose |
| --- | --- | --- |
| System prompt | Flow: `getSystemPrompt()` | Shared agent instructions inherited by all tasks |
| Step plan | Step: `getPlanPrompt()` | Guidance shared by tasks in this step |
| Task plan | Task: `getPlanPrompt()` | This task's objective |
| Per-call prompt | Argument to a generation method | Details for this particular call |

When plans are enabled, the call combines the step plan, task plan, and per-call prompt with blank lines between them. The system prompt is supplied separately as agent instructions.

Text generation excludes plans by default. Structured generation includes them by default:

```php
$text = $this->generateText('Write an introduction.', usePlan: true);
$data = $this->generateStructured($schema, prompt: 'Use the current catalog.');
```

## Load a prompt file

Create a Markdown file beside your application flow:

```text
You write catalog content for {{ brand | our store }}.
Language: {{ locale.language | English }}
Products: {{ products }}
```

Load it in the flow:

```php
public function getSystemPrompt(): string
{
    return $this->promptFromFile(__DIR__.'/system-prompt.md');
}
```

`promptFromFile()` reads the file and replaces its placeholders with flow parameters. You can also pass an override array as its second argument; those values take precedence over parameters with the same top-level key.

## Replace inline placeholders explicitly

```php
$prompt = $this->replacePromptVariables(
    'Write about {{ product.name }} for {{ audience | general readers }}.',
    ['audience' => 'teachers'],
);

$text = $this->generateText($prompt);
```

Inline prompts are not automatically expanded by `generateText()` or `generateStructured()`. Call `replacePromptVariables()` yourself, or use `promptFromFile()`. Likewise, a literal string returned from `getSystemPrompt()` or `getPlanPrompt()` needs explicit replacement if it contains placeholders.

## Placeholder rules

| Template | Example input | Rendered value |
| --- | --- | --- |
| `{{ brand }}` | `Northwind` | `Northwind` |
| `{{ locale.language }}` | Nested language value `English` | `English` |
| `{{ missing \| default text }}` | Missing value | `default text` |
| `{{ missing }}` | Missing value | Empty string |
| `{{ languages }}` | `['Arabic', 'English']` | `Arabic,English` |
| `{{ colors }}` | `['primary' => '#123456']` | `primary=#123456` |

Spaces around the key and fallback are allowed. Fallbacks apply to missing values, `null`, empty strings, and empty arrays. Zero is preserved. Scalars are converted to strings; lists are comma-separated, and maps use comma-separated `key=value` pairs. These replacements produce prompt text, not JSON encoding or HTML escaping.

## Placeholders in JSON Schema

Schema arrays and schema files receive replacement automatically in their string values:

```json
{
    "type": "object",
    "required": ["title"],
    "properties": {
        "title": {
            "type": "string",
            "description": "A title in {{ locale.language | English }}"
        }
    }
}
```

Keys are not replaced. Numbers and booleans remain their original types. A placeholder that renders `"5"` is still a string, so set numeric schema constraints as actual numbers rather than numeric placeholders.

For standalone formatting, the public `TemplateVariableReplacer::replaceInString($text, $params)` and `replaceInArray($schema, $params)` methods provide the same replacement rules.
