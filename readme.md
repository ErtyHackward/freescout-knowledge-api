# Knowledge base API module for FreeScout
This module adds the option to add a public API for the [FreeScout](https://freescout.net) knowledge base (module).

## Requirements
- [FreeScout](https://freescout.net) installed 
- FreeScout [Knowledge base module](https://freescout.net/module/knowledge-base/) 

## Installation

1. Download the latest module zip file via the releases card on the right.
2. Transfer the zip file to the server in the Modules folder of FreeScout.
3. Unpack the zip file.
4. Remove the zip file.
5. Activate the module via the Modules page in FreeScout.

## Update instructions

1. Download the latest module zip file via the releases card on the right.
2. Transfer the zip file to the server in the Modules folder of FreeScout.
3. Remove the folder KnowledgeBaseApiModule
4. Unpack the zip file.
5. Remove the zip file.

## Contributing

Feel free to add your own features by sending a pull request.

## Get knowledge base categories in a mailbox

```
curl "https://example.com/api/knowledgebase/1/categories?locale=en" \
-H 'Accept: application/json' \
-H 'Content-Type: application/json; charset=utf-8' \
-d $'{}'
```

## Get articles in a category

```
curl "https://example.com/api/knowledgebase/1/categories/1?locale=en" \
     -H 'Accept: application/json' \
     -H 'Content-Type: application/json; charset=utf-8' \
     -d $'{}'
```

## Admin API (v1.1.0+)

Authenticated CRUD endpoints for managing the knowledge base programmatically.
They reuse the API key from the [API and Webhooks](https://github.com/freescout-helpdesk/freescout/tree/dist/Modules/ApiWebhooks) module — install and activate that module, then copy the key from its settings page.

Pass the key in either:
- `X-FreeScout-API-Key: <key>` header, or
- `?api_key=<key>` query parameter.

All payloads are JSON unless stated otherwise. Translatable fields (`name`, `description` on categories; `title`, `slug`, `text` on articles) accept either a plain string (written to the locale specified by `?locale=` or the mailbox default) or a `<field>_i18n` object map `{ "en": "...", "ru": "..." }`.

### Categories

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/knowledgebase/{mailboxId}/categories/full` | List all categories of a mailbox (incl. private) with all translations |
| `GET` | `/api/knowledgebase/categories/{categoryId}` | Show one category with all translations |
| `POST` | `/api/knowledgebase/{mailboxId}/categories` | Create. Body: `name`/`name_i18n`, `description`/`description_i18n`, `parent_id`, `visibility` (1=public,2=private), `expand`, `articles_order`, `sort_order` |
| `PUT` | `/api/knowledgebase/categories/{categoryId}` | Update — any subset of the above fields |
| `DELETE` | `/api/knowledgebase/categories/{categoryId}` | Delete. Articles are detached (not deleted); subcategories are re-parented to root. |
| `PUT` | `/api/knowledgebase/{mailboxId}/categories/reorder` | Body: `{"categories":[{"id":1,"sort_order":1,"parent_id":null}, ...]}` |

### Articles

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/knowledgebase/{mailboxId}/articles?category_id=&status=` | List articles in a mailbox (optionally filtered by category and/or status) |
| `GET` | `/api/knowledgebase/articles/{articleId}` | Show one article with all translations |
| `POST` | `/api/knowledgebase/{mailboxId}/articles` | Create. Body: `title`/`title_i18n`, `text`/`text_i18n`, `slug`/`slug_i18n` (optional — auto-generated from default-locale title), `status` (`draft`/`published`), `sort_order`, `category_ids` |
| `PUT` | `/api/knowledgebase/articles/{articleId}` | Update — any subset |
| `DELETE` | `/api/knowledgebase/articles/{articleId}` | Delete (pivot rows removed automatically) |
| `PUT` | `/api/knowledgebase/articles/{articleId}/categories` | Body: `{"category_ids":[1,2]}` — replace article's categories without touching anything else |

### Attachments

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/knowledgebase/{mailboxId}/attachments` | `multipart/form-data` with `file=@…`. Returns `{url, path, size, mime}`. Allowed: jpg, jpeg, png, gif, webp, svg, pdf. Max 10 MB. |

### Examples

```sh
# Create a category
curl -X POST "https://example.com/api/knowledgebase/1/categories" \
  -H "X-FreeScout-API-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Getting Started","description":"First steps","visibility":1}'

# Create an article in two locales, attach to category 5, publish immediately
curl -X POST "https://example.com/api/knowledgebase/1/articles" \
  -H "X-FreeScout-API-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "title_i18n": {"en":"How to reset","ru":"Как сбросить"},
        "text_i18n":  {"en":"<p>…</p>","ru":"<p>…</p>"},
        "status": "published",
        "category_ids": [5]
      }'

# Upload an image and embed it
curl -X POST "https://example.com/api/knowledgebase/1/attachments" \
  -H "X-FreeScout-API-Key: $KEY" \
  -F "file=@screenshot.png"
```

## LICENSE

MIT