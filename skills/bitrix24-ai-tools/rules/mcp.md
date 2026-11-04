# Документационный MCP `b24-dev-mcp`

Адрес: `https://mcp-dev.bitrix24.com/mcp`. Транспорт Streamable HTTP, без авторизации. Сервер возвращает только документацию: не читает данные портала и не вызывает методы. Клиент принимает `application/json` и `text/event-stream`, иначе возможен 406. Старый транспорт `/sse` не поддерживается.

| Инструмент | Когда применять |
| --- | --- |
| `bitrix-search` | Найти точное имя; `doc_type`: `method`, `event`, `other`, `app_development_docs`; `limit` ограничивает выдачу |
| `bitrix-method-details` | Параметры, обязательные поля, ответ, ошибки и примеры метода; `field`/`filter` сужают ответ |
| `bitrix-event-details` | Формат данных события по точному имени из поиска |
| `bitrix-article-details` | Авторизация, лимиты, batch, пагинация |
| `bitrix-app-development-doc-details` | Установка, типы приложений и встройки |

## Проверка вызова

1. При неизвестном имени выполни `bitrix-search`, затем `bitrix-method-details` для выбранного метода. Бери регистр параметров, обязательность, фильтр и scope из описания.
2. `not found` бывает из-за имени, индекса, версии API или отсутствия документации. Уточни имя одним поиском и открой официальную страницу в `https://apidocs.bitrix24.com/`. Если подтверждения нет, явно укажи пробел и не выдумывай контракт. Не повторяй поиск бесконечно.
3. Если MCP недоступен, сразу используй официальную страницу метода или исходники официального `bitrix24/b24restdocs`. Для коробки дополнительно проверь метод на нужном портале и версию модуля.
4. Не отправляй секреты и реальные данные в запросы к документации.

## Подключение

Codex:

```bash
codex mcp add b24-dev-mcp --url https://mcp-dev.bitrix24.com/mcp
codex mcp list
```

Cursor `.cursor/mcp.json`: `{"mcpServers":{"b24-dev-mcp":{"url":"https://mcp-dev.bitrix24.com/mcp","timeout":30000}}}`.

VS Code `.vscode/mcp.json`: `{"servers":{"b24-dev-mcp":{"url":"https://mcp-dev.bitrix24.com/mcp","type":"http"}}}`.

Проверка: запроси параметры `crm.item.add`; описание должно содержать `entityTypeId` и `fields`. Настройка клиента не разрешает вызывать API реального портала.

Готовые основы: `bitrix24/b24-ai-starter` (Nuxt 3, PHP/Python/Node, OAuth, Docker), `llms.txt` в b24gosdk. Отдельный `llms.txt` самой REST-документации не заявлен в проверенном описании.

Источник: официальная `b24restdocs/ai-tools/mcp.md` на 30.10.2026.
