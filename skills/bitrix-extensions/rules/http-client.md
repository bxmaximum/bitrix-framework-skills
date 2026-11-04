# HTTP и асинхронный JS

**Since main 26.900** `HttpClient` из `main.core` (глобальный `BX.HttpClient`) вызывает Engine через нативный fetch. `action()` возвращает `data`; `actionRaw()` — успешную оболочку `{status, data, errors}`. **Оба** бросают `ControllerError` при ошибке контроллера / HTTP; `actionRaw()` не отключает проверку статуса. На старом ядре используй `BX.ajax.runAction(...).then(response => response.data)`.

```js
import { HttpClient } from 'main.core';

try
{
    const report = await HttpClient.action('vendor:reports.report.get', {
        data: { id: 42 },
        timeout: 5000,
    });
    renderReport(report);
}
catch (error)
{
    if (error instanceof HttpClient.ControllerError)
    {
        showErrors(error.errors);
    }
    else
    {
        showNetworkError();
    }
}
```

- `action()` передаёт CSRF и загружает ресурсы ответа. `get()` / `post()` работают с обычным HTTP-ответом, это другой контракт.
- Не включай повторы записи без доказанной идемпотентности. `retry.idempotent` задаётся для отдельного запроса / action; значение общей конфигурации экземпляра игнорируется.
- `signal` принимает AbortSignal, `timeout` задаётся в миллисекундах. Отменяй запросы при уничтожении виджета.
- `crossTabLockKey` использует navigator.locks для координации вкладок. Это не гарантия однократной записи сервером: проверяй бизнес-ключ на сервере.
- BX.ajax остаётся рабочим API; не переписывай существующие вызовы только ради новой обёртки.
- **Since main 26.900** BX.Promise и BX.ResizeObserver устарели: новый код использует нативные Promise и window.ResizeObserver. На старом браузере проверь доступность и поставляй свой полифил, не рассчитывай на удалённые полифилы ядра.
