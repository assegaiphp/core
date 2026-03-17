# API Docs and Clients

Assegai can now treat API description as a built-in capability rather than a separate setup task.

If your controllers and DTOs already describe request handling, the framework can derive a usable API contract from that metadata automatically.

## Runtime docs vs exported files

When you run:

```bash
assegai serve
```

your app can expose:

- `/openapi.json` for the generated OpenAPI document
- `/docs` for a Swagger UI view of that document

Those runtime endpoints do not require an exported file on disk.

The export commands are separate on purpose:

- `assegai api:export openapi` writes an OpenAPI file
- `assegai api:export postman` writes a Postman collection
- `assegai api:client typescript` generates a TypeScript client
- `assegai api:export typescript` is also accepted as an alias for the TypeScript client export

If you want `assegai serve` to refresh `generated/openapi.json` automatically, add this to `assegai.json`:

```json
{
  "apiDocs": {
    "enabled": true,
    "exportOnServe": true,
    "exportPath": "generated/openapi.json"
  }
}
```

Set `exportOnServe` to `false` if you prefer to keep exports manual.

That means a route like this:

```php
#[Post]
public function create(#[Body] CreatePostDTO $dto)
{
  return $this->postsService->create($dto);
}
```

and a DTO like this:

```php
class CreatePostDTO
{
  #[IsString]
  #[IsNotEmpty]
  public string $title;

  #[IsString]
  public string $body;
}
```

already produces:

- a request schema
- required field information
- validation-aware hints
- example request payloads
- an operation entry in Swagger UI

## Where the generated contract comes from

The generated OpenAPI document is based on the same runtime metadata the router already uses:

- controller prefixes from `#[Controller(...)]`
- handler methods from `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Head]`, `#[Options]`, and `#[Sse]`
- path params from route patterns like `:id<int>`
- request bodies from `#[Body]`
- query params from `#[Query(...)]`
- status overrides from `#[HttpCode(...)]` and `#[ResponseStatus(...)]`
- redirects from `#[Redirect(...)]`

DTO schemas are built from property types plus validation attributes such as:

- `#[IsString]`
- `#[IsNotEmpty]`
- `#[IsInt]`
- `#[IsNumber]`
- `#[IsNumeric]`
- `#[IsArray]`
- `#[IsEmail]`
- `#[IsUrl]`
- `#[IsDate]`
- `#[IsEnum(...)]`
- `#[IsBetween(...)]`
- `#[IsEqualTo(...)]`
- `#[IsOptional]`

## Forms are included too

If a handler binds a body DTO, the generated request body is exposed for:

- `application/json`
- `application/x-www-form-urlencoded`
- `multipart/form-data`

That keeps the docs aligned with the current request binding story instead of documenting JSON only.

## Export the contract

You can export OpenAPI directly:

```bash
assegai api:export openapi
```

Default output:

```text
generated/openapi.json
```

You can export a Postman collection from the same metadata:

```bash
assegai api:export postman
```

Default output:

```text
generated/assegai.postman.collection.json
```

Use `--output` if you want a different destination.

## Generate a TypeScript client

Assegai can also generate a fetch-based TypeScript client from the same OpenAPI document:

```bash
assegai api:client typescript
```

Or, if you prefer to keep all generated artifacts under `api:export`:

```bash
assegai api:export typescript
```

Default output:

```text
generated/assegai-api-client.ts
```

The current generator focuses on the common case:

- named interfaces for reflected DTO schemas
- typed path and query input objects
- typed JSON request bodies
- a small `createAssegaiClient(...)` factory built on `fetch`

## What Swagger UI shows

Swagger UI reads `/openapi.json` and shows:

- operations grouped by controller tag
- request schemas
- example payloads
- response metadata

Because the generated document includes example request bodies, Swagger UI can show useful example requests immediately instead of forcing you to invent them first.

## Suggested workflow

For a new resource, a smooth loop now looks like this:

```bash
assegai g r posts
assegai serve
```

Then:

1. open `/docs`
2. inspect the generated contract
3. send a request from Swagger UI or your client
4. export Postman or a TypeScript client when another team needs the contract
